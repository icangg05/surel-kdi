<?php

namespace Tests\Feature\Mail;

use App\Enums\DeliveryStatus;
use App\Enums\MessageStatus;
use App\Mail\OutgoingMail;
use App\Models\Message;
use App\Models\Thread;
use App\Services\Outbox;
use App\Services\QuotaManager;
use App\Support\Settings;
use App\Support\SmtpStatus;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

class OutgoingMailTest extends MailTestCase
{
    private function draft(array $to): Message
    {
        return Message::create([
            'mailbox_id' => $this->mailbox->id,
            'user_id' => $this->admin->id,
            'direction' => Message::OUT,
            'status' => MessageStatus::Draft,
            'from_email' => $this->mailbox->address,
            'to' => $to,
            'subject' => 'Undangan rapat',
            'body_html' => '<p>Mohon hadir.</p>',
        ]);
    }

    public function test_send_sets_tracking_headers_and_reply_token(): void
    {
        Mail::fake();
        $message = $this->draft([['email' => 'budi@example.com', 'name' => 'Budi']]);

        $this->actingAs($this->admin)->post(route('drafts.send', $message))->assertRedirect();

        $message->refresh();
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertStringStartsWith('kominfo+t', (string) $message->reply_to);
        $this->assertStringEndsWith('@in.ilmifaizan.cloud', (string) $message->reply_to);
        $this->assertSame(DeliveryStatus::Sent, $message->deliveries()->sole()->status);

        Mail::assertSent(OutgoingMail::class, function (OutgoingMail $mail) use ($message) {
            $headers = $mail->headers();

            return $mail->hasTo('budi@example.com')
                && $mail->hasFrom('kominfo@ilmifaizan.cloud')
                && $mail->hasReplyTo($message->reply_to)
                && $headers->messageId === $message->message_id_header
                && $headers->text['X-Mailin-custom'] === 'm:'.$message->uuid;
        });
    }

    public function test_quota_exhausted_keeps_message_waiting(): void
    {
        Mail::fake();
        Settings::set('daily_limit', 1);
        $message = $this->draft([['email' => 'a@example.com'], ['email' => 'b@example.com']]);

        $this->actingAs($this->admin)->post(route('drafts.send', $message));

        $this->assertSame(MessageStatus::WaitingQuota, $message->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_scheduled_message_is_released_by_pump(): void
    {
        Mail::fake();
        $message = $this->draft([['email' => 'budi@example.com']]);
        app(Outbox::class)->submit($message, now()->addHour());
        $this->assertSame(MessageStatus::Scheduled, $message->fresh()->status);

        $this->travel(61)->minutes();
        $this->artisan('mail:pump')->assertSuccessful();

        $this->assertSame(MessageStatus::Sent, $message->fresh()->status);
    }

    public function test_send_requires_valid_recipient(): void
    {
        $message = $this->draft([['email' => 'bukan-email']]);

        $this->actingAs($this->admin)->post(route('drafts.send', $message))->assertSessionHasErrors('to');
    }

    public function test_reply_prefills_thread_and_headers(): void
    {
        Mail::fake();
        $sent = $this->draft([['email' => 'budi@example.com']]);
        app(Outbox::class)->submit($sent);
        $sent->refresh();

        $incoming = Message::create([
            'mailbox_id' => $this->mailbox->id, 'thread_id' => $sent->thread_id, 'direction' => Message::IN,
            'status' => MessageStatus::Received, 'from_email' => 'budi@example.com', 'from_name' => 'Budi',
            'subject' => 'Re: Undangan rapat', 'message_id_header' => 'balasan-1@example.com', 'body_html' => '<p>Siap</p>',
        ]);

        $draft = $this->actingAs($this->admin)->postJson(route('drafts.store'), [
            'mailbox_id' => $this->mailbox->id, 'mode' => 'reply', 'source_id' => $incoming->id,
        ])->assertOk()->json();

        $this->assertSame($sent->thread_id, $draft['thread_id']);
        $this->assertSame('budi@example.com', $draft['to'][0]['email']);
        $this->assertSame('Re: Undangan rapat', $draft['subject']);
        $this->assertSame('balasan-1@example.com', Message::find($draft['id'])->in_reply_to);
    }

    public function test_unauthorized_ip_fails_fast_with_clear_message_and_banner(): void
    {
        $message = $this->draft([['email' => 'budi@example.com']]);
        Mail::shouldReceive('send')->once()->andThrow(new TransportException('Expected response code "235" but got code "525", with message "525 5.7.1 Unauthorized IP address".'));

        app(Outbox::class)->submit($message);

        $message->refresh();
        $this->assertSame(MessageStatus::Failed, $message->status);
        $this->assertStringContainsString('Authorized IPs', (string) $message->error);
        $this->assertSame(0, app(QuotaManager::class)->used(), 'Kredit dikembalikan');
        $this->assertNotNull(SmtpStatus::current());
    }

    public function test_discarding_returned_draft_removes_empty_thread(): void
    {
        Mail::fake();
        $message = $this->draft([['email' => 'budi@example.com']]);
        app(Outbox::class)->submit($message, now()->addDay());

        $this->actingAs($this->admin)->post(route('messages.unschedule', $message));
        $this->actingAs($this->admin)->deleteJson(route('drafts.destroy', $message))->assertOk();

        $this->assertSame(0, Thread::count());
    }
}
