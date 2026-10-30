<?php

namespace App\Jobs;

use App\Models\InboundPayload;
use App\Services\InboundProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessInboundEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $payloadId) {}

    public function handle(InboundProcessor $processor): void
    {
        $payload = InboundPayload::query()->find($this->payloadId);

        if ($payload) {
            $processor->process($payload);
        }
    }

    public function failed(Throwable $e): void
    {
        InboundPayload::query()->whereKey($this->payloadId)->update(['error' => mb_substr($e->getMessage(), 0, 250)]);
    }
}
