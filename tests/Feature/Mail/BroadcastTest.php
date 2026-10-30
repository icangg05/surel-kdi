<?php

namespace Tests\Feature\Mail;

use App\Enums\BroadcastStatus;
use App\Enums\ContactStatus;
use App\Enums\DeliveryStatus;
use App\Mail\OutgoingMail;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Delivery;
use App\Models\Message;
use App\Services\BroadcastPump;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class BroadcastTest extends MailTestCase
{
    private Broadcast $broadcast;

    protected function setUp(): void
    {
        parent::setUp();

        $group = ContactGroup::create(['name' => 'Camat', 'color' => 'blue']);
        $contacts = Contact::factory()->count(5)->create();
        $contacts[0]->update(['name' => 'Andi <b>&</b> Co', 'organization' => 'Kec. Poasia']);
        $contacts[4]->suppress(ContactStatus::Bounced, 'tidak dikenal');
        $group->contacts()->attach($contacts->pluck('id'));
        // Kontak yang dipilih langsung juga ada di grup → tidak boleh dikirimi dua kali.
        $this->broadcast = Broadcast::create([
            'mailbox_id' => $this->mailbox->id, 'user_id' => $this->admin->id, 'subject' => 'Surat edaran untuk {{nama}}',
            'body_html' => '<p>Yth. {{nama}} ({{instansi}})</p>', 'status' => BroadcastStatus::Draft,
            'audience' => ['groups' => [$group->id], 'contacts' => [$contacts[0]->id]],
        ]);
    }

    public function test_launch_dedupes_skips_suppressed_and_respects_quota(): void
    {
        Mail::fake();
        Settings::set('daily_limit', 2);

        $this->actingAs($this->admin)->post(route('broadcasts.launch', $this->broadcast))->assertRedirect(route('broadcasts.show', $this->broadcast));

        $this->broadcast->refresh();
        $this->assertSame(5, $this->broadcast->total);
        $this->assertSame(1, $this->broadcast->deliveries()->where('status', DeliveryStatus::Skipped)->count());
        $this->assertSame(2, $this->broadcast->deliveries()->where('status', DeliveryStatus::Sent)->count());
        Mail::assertSent(OutgoingMail::class, 2);

        // Putaran berikutnya hari yang sama: kuota habis → menunggu.
        app(BroadcastPump::class)->pump();
        $this->assertSame(BroadcastStatus::WaitingQuota, $this->broadcast->fresh()->status);

        // Besok kuota reset → sisa 2 berangkat, siaran selesai.
        $this->travel(1)->days();
        app(BroadcastPump::class)->pump();
        $this->assertSame(BroadcastStatus::Completed, $this->broadcast->fresh()->status);
        Mail::assertSent(OutgoingMail::class, 4);
    }

    public function test_merge_fields_are_escaped_per_recipient(): void
    {
        $this->broadcast->update(['status' => BroadcastStatus::Sending]);
        app(BroadcastPump::class)->launch($this->broadcast);
        $delivery = Delivery::query()->where('name', 'Andi <b>&</b> Co')->sole();

        $mail = OutgoingMail::forBroadcast($this->broadcast, $delivery);

        $this->assertSame('<p>Yth. Andi &lt;b&gt;&amp;&lt;/b&gt; Co (Kec. Poasia)</p>', $mail->bodyHtml);
        $this->assertSame('Surat edaran untuk Andi <b>&</b> Co', $mail->subjectLine);
        $this->assertSame('kominfo+'.$delivery->reply_token.'@in.ilmifaizan.cloud', $mail->replyToAddress);
    }

    public function test_reply_to_broadcast_creates_thread_with_context(): void
    {
        Mail::fake();
        Http::fake();
        $this->actingAs($this->admin)->post(route('broadcasts.launch', $this->broadcast));
        $delivery = $this->broadcast->deliveries()->where('status', DeliveryStatus::Sent)->first();

        $payload = $this->fixture('inbound-reply');
        $address = "kominfo+{$delivery->reply_token}@in.ilmifaizan.cloud";
        $payload['items'][0]['Recipients'] = [$address];
        $payload['items'][0]['To'] = [['Address' => $address]];
        $payload['items'][0]['From'] = ['Name' => $delivery->name, 'Address' => $delivery->email];
        $payload['items'][0]['SentAtDate'] = now()->addMinutes(5)->toRfc2822String();

        $this->postJson('/webhooks/brevo/inbound/rahasia-uji', $payload)->assertOk();

        $delivery->refresh();
        $this->assertNotNull($delivery->replied_at);
        $thread = $delivery->thread;
        $this->assertSame($this->broadcast->id, $thread->broadcast_id);
        $this->assertSame([Message::OUT, Message::IN], $thread->messages->pluck('direction')->all());
    }
}
