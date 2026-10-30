<?php

namespace App\Jobs;

use App\Models\Delivery;
use App\Services\BroadcastPump;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendBroadcastEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $deliveryId)
    {
        // Antrean terpisah supaya email biasa tidak terjebak di belakang ratusan email siaran.
        $this->onQueue('broadcast');
    }

    public function handle(BroadcastPump $pump): void
    {
        $delivery = Delivery::query()->find($this->deliveryId);

        if ($delivery) {
            $pump->sendOne($delivery);
        }
    }

    public function failed(Throwable $e): void
    {
        $delivery = Delivery::query()->find($this->deliveryId);

        if ($delivery) {
            app(BroadcastPump::class)->markFailed($delivery, $e);
        }
    }
}
