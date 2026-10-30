<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\Brevo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** Lampiran email masuk tidak ada di payload webhook — diunduh terpisah lewat API Brevo. */
class DownloadInboundAttachment implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public int $attachmentId) {}

    public function handle(Brevo $brevo): void
    {
        $attachment = Attachment::query()->find($this->attachmentId);

        if (! $attachment || $attachment->path || ! $attachment->download_token) {
            return;
        }

        if (! $brevo->configured()) {
            $attachment->update(['error' => 'BREVO_API_KEY belum diisi — lampiran belum bisa diunduh.']);

            return;
        }

        $body = $brevo->inboundAttachment($attachment->download_token);
        $path = 'attachments/'.now()->format('Y/m').'/'.Str::uuid();
        Storage::disk(Attachment::DISK)->put($path, $body);

        $attachment->update(['path' => $path, 'size' => strlen($body), 'error' => null]);
    }

    public function failed(Throwable $e): void
    {
        Attachment::query()->whereKey($this->attachmentId)->update(['error' => mb_substr($e->getMessage(), 0, 250)]);
    }
}
