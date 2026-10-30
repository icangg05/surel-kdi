<?php

namespace Tests\Feature\Mail;

use App\Enums\ContactStatus;
use App\Enums\DeliveryStatus;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\Message;

class DeliveryEventsTest extends MailTestCase
{
    private Delivery $delivery;

    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        $contact = Contact::create(['name' => 'Budi', 'email' => 'budi@example.com']);
        $this->message = Message::create([
            'mailbox_id' => $this->mailbox->id, 'direction' => Message::OUT, 'status' => MessageStatus::Sent,
            'from_email' => $this->mailbox->address, 'subject' => 'Uji', 'message_id_header' => 'abc@ilmifaizan.cloud',
        ]);
        $this->delivery = $this->message->deliveries()->create(['email' => 'budi@example.com', 'contact_id' => $contact->id, 'status' => DeliveryStatus::Sent, 'provider_message_id' => 'abc@ilmifaizan.cloud']);
    }

    private function event(string $name, int $ts, array $extra = []): array
    {
        return ['event' => $name, 'email' => 'budi@example.com', 'message-id' => '<abc@ilmifaizan.cloud>', 'X-Mailin-custom' => 'm:'.$this->message->uuid, 'ts_event' => $ts, ...$extra];
    }

    public function test_status_only_moves_forward(): void
    {
        $this->postJson('/webhooks/brevo/events/rahasia-uji', $this->event('opened', 1_800_000_100))->assertJson(['matched' => 1]);
        $this->postJson('/webhooks/brevo/events/rahasia-uji', $this->event('delivered', 1_800_000_050));

        $d = $this->delivery->fresh();
        $this->assertSame(DeliveryStatus::Opened, $d->status);
        $this->assertNotNull($d->delivered_at);
        $this->assertNotNull($d->opened_at);
    }

    public function test_duplicate_event_is_ignored(): void
    {
        $this->postJson('/webhooks/brevo/events/rahasia-uji', $this->event('delivered', 1_800_000_050));
        $this->postJson('/webhooks/brevo/events/rahasia-uji', $this->event('delivered', 1_800_000_050));

        $this->assertSame(1, $this->delivery->events()->count());
    }

    public function test_hard_bounce_suppresses_contact(): void
    {
        $this->postJson('/webhooks/brevo/events/rahasia-uji', $this->event('hard_bounce', 1_800_000_050, ['reason' => '550 user unknown']));

        $this->assertSame(DeliveryStatus::HardBounce, $this->delivery->fresh()->status);
        $contact = Contact::sole();
        $this->assertSame(ContactStatus::Bounced, $contact->status);
        $this->assertSame('550 user unknown', $contact->suppressed_reason);
    }

    public function test_matches_by_message_id_without_custom_header(): void
    {
        $event = $this->event('delivered', 1_800_000_050);
        unset($event['X-Mailin-custom']);

        $this->postJson('/webhooks/brevo/events/rahasia-uji', [$event])->assertJson(['matched' => 1]);
        $this->assertSame(DeliveryStatus::Delivered, $this->delivery->fresh()->status);
    }
}
