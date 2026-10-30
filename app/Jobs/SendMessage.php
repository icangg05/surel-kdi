<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\Outbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public int $messageId) {}

    public function handle(Outbox $outbox): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message) {
            $outbox->deliver($message);
        }
    }

    public function failed(Throwable $e): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message) {
            app(Outbox::class)->markFailed($message, $e);
        }
    }
}
