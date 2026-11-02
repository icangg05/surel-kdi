<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Isi mailbox berubah: email masuk (`received`) atau status kirim berubah
 * (`status`). Browser yang membuka mailbox ini memuat ulang daftarnya lewat
 * Reverb tanpa menunggu polling.
 */
class MailboxUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /** Event realtime yang terlambat tidak berguna; jangan diulang bila Reverb mati. */
    public int $tries = 1;

    /** @param array<string, mixed> $data */
    public function __construct(
        public int $mailboxId,
        public string $kind,
        public ?int $threadId = null,
        public array $data = [],
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("mailbox.{$this->mailboxId}");
    }

    public function broadcastAs(): string
    {
        return 'mailbox.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['kind' => $this->kind, 'mailbox_id' => $this->mailboxId, 'thread_id' => $this->threadId, ...$this->data];
    }

    /** Antrean sendiri di depan pengiriman siaran, agar tidak tertahan ratusan email. */
    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}
