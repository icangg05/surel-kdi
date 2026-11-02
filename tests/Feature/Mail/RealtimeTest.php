<?php

namespace Tests\Feature\Mail;

use App\Enums\BroadcastStatus;
use App\Enums\DeliveryStatus;
use App\Enums\MessageStatus;
use App\Events\MailboxUpdated;
use App\Models\Broadcast as BroadcastModel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class RealtimeTest extends MailTestCase
{
    public function test_inbound_email_announces_itself_on_the_mailbox_channel(): void
    {
        Event::fake([MailboxUpdated::class]);
        Http::fake(['api.brevo.com/*' => Http::response('isi', 200)]);

        $this->postJson('/webhooks/brevo/inbound/rahasia-uji', $this->fixture('inbound-reply'))->assertOk();

        $message = Message::sole();
        Event::assertDispatched(MailboxUpdated::class, fn (MailboxUpdated $e) => $e->kind === 'received'
            && $e->mailboxId === $this->mailbox->id
            && $e->threadId === $message->thread_id
            && $e->broadcastOn()->name === "private-mailbox.{$this->mailbox->id}"
            && $e->broadcastWith()['subject'] === $message->subject);
    }

    public function test_delivery_status_of_a_direct_email_is_pushed_but_broadcast_deliveries_are_not(): void
    {
        Event::fake([MailboxUpdated::class]);
        $message = Message::create([
            'mailbox_id' => $this->mailbox->id, 'direction' => Message::OUT, 'status' => MessageStatus::Sent,
            'from_email' => $this->mailbox->address, 'subject' => 'Uji', 'message_id_header' => 'abc@ilmifaizan.cloud',
        ]);
        $message->deliveries()->create(['email' => 'budi@example.com', 'status' => DeliveryStatus::Sent, 'provider_message_id' => 'abc@ilmifaizan.cloud']);

        $this->postJson('/webhooks/brevo/events/rahasia-uji', ['event' => 'delivered', 'email' => 'budi@example.com', 'X-Mailin-custom' => 'm:'.$message->uuid, 'ts_event' => 1_800_000_050]);
        Event::assertDispatchedTimes(MailboxUpdated::class, 1);
        Event::assertDispatched(MailboxUpdated::class, fn (MailboxUpdated $e) => $e->kind === 'status' && $e->mailboxId === $this->mailbox->id);

        $broadcast = BroadcastModel::create([
            'mailbox_id' => $this->mailbox->id, 'user_id' => $this->admin->id, 'subject' => 'Edaran',
            'body_html' => '<p>Isi</p>', 'status' => BroadcastStatus::Sending, 'audience' => [],
        ]);
        $delivery = $broadcast->deliveries()->create(['email' => 'sari@example.com', 'status' => DeliveryStatus::Sent]);
        $this->postJson('/webhooks/brevo/events/rahasia-uji', ['event' => 'delivered', 'email' => 'sari@example.com', 'X-Mailin-custom' => 'd:'.$delivery->uuid, 'ts_event' => 1_800_000_060]);
        Event::assertDispatchedTimes(MailboxUpdated::class, 1);
    }

    public function test_only_users_who_may_open_the_mailbox_can_listen(): void
    {
        $authorize = Broadcast::driver()->getChannels()['mailbox.{mailbox}'];
        $operator = User::factory()->create();
        $mailbox = $this->mailbox->fresh();

        $this->assertTrue($authorize($this->admin, $mailbox));
        $this->assertFalse($authorize($operator, $mailbox));

        $operator->mailboxes()->attach($mailbox);
        $this->assertTrue($authorize($operator, $mailbox));

        $operator->update(['is_active' => false]);
        $this->assertFalse($authorize($operator, $mailbox));
    }
}
