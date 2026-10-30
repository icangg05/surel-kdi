<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien tipis Brevo API v3. Kirim email memakai SMTP (Laravel Mailer), jadi
 * di sini hanya yang tidak bisa lewat SMTP: webhook, email masuk, akun.
 */
class Brevo
{
    public function configured(): bool
    {
        return filled(config('kdimail.brevo.api_key'));
    }

    /**
     * Info akun, termasuk paket & sisa kredit.
     *
     * @return array<string, mixed>
     */
    public function account(): array
    {
        return $this->http()->get('account')->json();
    }

    /** @return list<array<string, mixed>> */
    public function webhooks(string $type): array
    {
        try {
            return $this->http()->get('webhooks', ['type' => $type])->json('webhooks') ?? [];
        } catch (RequestException $e) {
            // Brevo membalas 400 "document_not_found" bila belum ada webhook, bukan daftar kosong.
            if ($e->response->json('code') === 'document_not_found') {
                return [];
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createWebhook(array $data): array
    {
        return $this->http()->post('webhooks', $data)->json() ?? [];
    }

    public function deleteWebhook(int $id): void
    {
        $this->http()->delete("webhooks/$id");
    }

    /** Isi biner lampiran email masuk. Token berasal dari payload webhook inbound. */
    public function inboundAttachment(string $downloadToken): string
    {
        return $this->http()->timeout(120)->get('inbound/attachments/'.rawurlencode($downloadToken))->body();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function inboundEvents(array $query = []): array
    {
        return $this->http()->get('inbound/events', $query)->json('events') ?? [];
    }

    /** @return array<string, mixed> */
    public function inboundEvent(string $uuid): array
    {
        return $this->http()->get('inbound/events/'.rawurlencode($uuid))->json() ?? [];
    }

    private function http(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('BREVO_API_KEY belum diisi di .env.');
        }

        return Http::baseUrl(config('kdimail.brevo.base_url'))
            ->withHeaders(['api-key' => config('kdimail.brevo.api_key')])
            ->acceptJson()
            ->timeout(20)
            // Ulangi hanya untuk gangguan jaringan; 4xx dari Brevo adalah jawaban final.
            ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false)
            ->throw();
    }
}
