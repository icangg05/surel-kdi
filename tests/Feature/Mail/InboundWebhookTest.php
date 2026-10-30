<?php

namespace Tests\Feature\Mail;

use App\Enums\Folder;
use App\Enums\MessageStatus;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\InboundPayload;
use App\Models\Message;
use App\Models\Thread;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class InboundWebhookTest extends MailTestCase
{
    public function test_wrong_secret_is_rejected(): void
    {
        $this->postJson('/webhooks/brevo/inbound/salah', $this->fixture('inbound-reply'))->assertNotFound();
        $this->assertSame(0, InboundPayload::count());
    }

    public function test_inbound_email_lands_in_mailbox_sanitized_with_attachment(): void
    {
        Storage::fake('local');
        Http::fake(['api.brevo.com/v3/inbound/attachments/*' => Http::response('%PDF-1.4 isi', 200)]);
        $contact = Contact::create(['name' => 'Budi', 'email' => 'budi@example.com']);

        $this->postJson('/webhooks/brevo/inbound/rahasia-uji', $this->fixture('inbound-reply'))
            ->assertOk()->assertJson(['received' => 1]);

        $message = Message::sole();
        $this->assertSame($this->mailbox->id, $message->mailbox_id);
        $this->assertSame('budi@example.com', $message->from_email);
        $this->assertSame($contact->id, $message->contact_id);
        $this->assertSame(MessageStatus::Received, $message->status);
        $this->assertFalse($message->is_read);
        $this->assertStringNotContainsString('<script', $message->body_html);
        $this->assertStringNotContainsString('onerror', $message->body_html);
        $this->assertStringNotContainsString('javascript:', $message->body_html);
        $this->assertSame(Folder::Inbox, $message->thread->folder);
        $this->assertSame(1, $message->thread->unread_count);

        $attachment = Attachment::sole();
        $this->assertNotNull($attachment->path);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_same_payload_twice_is_stored_once(): void
    {
        Http::fake();
        $this->postJson('/webhooks/brevo/inbound/rahasia-uji', $this->fixture('inbound-reply'));
        $this->postJson('/webhooks/brevo/inbound/rahasia-uji', $this->fixture('inbound-reply'))->assertJson(['received' => 0]);

        $this->assertSame(1, Message::count());
        // Uuid dari Brevo berupa array; yang disimpan nilai pertamanya.
        $this->assertSame('6c7c7c8a-0000-4000-8000-000000000001', InboundPayload::sole()->uuid);
    }

    public function test_reply_token_threads_into_existing_conversation(): void
    {
        Http::fake();
        $thread = Thread::create(['mailbox_id' => $this->mailbox->id, 'subject' => 'Undangan rapat']);
        $payload = $this->fixture('inbound-reply');
        $payload['items'][0]['Recipients'] = ["kominfo+{$thread->reply_token}@in.ilmifaizan.cloud"];
        $payload['items'][0]['To'] = [['Address' => "kominfo+{$thread->reply_token}@in.ilmifaizan.cloud"]];

        $this->postJson('/webhooks/brevo/inbound/rahasia-uji', $payload)->assertOk();

        $this->assertSame($thread->id, Message::sole()->thread_id);
        $this->assertSame(1, Thread::count());
    }

    public function test_high_spam_score_goes_to_spam(): void
    {
        Http::fake();
        $payload = $this->fixture('inbound-reply');
        $payload['items'][0]['SpamScore'] = 9.5;

        $this->postJson('/webhooks/brevo/inbound/rahasia-uji', $payload);

        $this->assertSame(Folder::Spam, Message::sole()->thread->folder);
    }

    public function test_unknown_recipient_is_logged_not_dropped(): void
    {
        $payload = $this->fixture('inbound-reply');
        $payload['items'][0]['Recipients'] = ['siapa@in.domainlain.id'];
        $payload['items'][0]['To'] = [['Address' => 'siapa@in.domainlain.id']];

        $this->postJson('/webhooks/brevo/inbound/rahasia-uji', $payload)->assertOk();

        $this->assertSame(0, Message::count());
        $this->assertStringContainsString('Tidak ada mailbox', (string) InboundPayload::sole()->error);
    }
}
