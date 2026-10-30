<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\Brevo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('brevo:register-webhooks {--url= : URL publik aplikasi, bawaan APP_URL}')]
#[Description('Daftarkan webhook Brevo: inbound per domain + event transaksional')]
class BrevoRegisterWebhooks extends Command
{
    /** Pesan penolakan Brevo per webhook, ditampilkan di Admin › Integrasi Brevo. */
    public const ERRORS_KEY = 'brevo.webhook_errors';

    /** @param array<string, mixed> $hook */
    public static function key(array $hook): string
    {
        return $hook['type'].':'.($hook['domain'] ?? '');
    }

    public function handle(Brevo $brevo): int
    {
        if (! $brevo->configured()) {
            $this->error('BREVO_API_KEY belum diisi di .env.');

            return self::FAILURE;
        }

        $base = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        $secret = (string) config('kdimail.brevo.webhook_secret');

        if (! str_starts_with($base, 'https://')) {
            $this->warn("URL $base bukan HTTPS — Brevo biasanya menolak. Pakai tunnel: docker compose --profile tunnel up tunnel");
        }

        $errors = Cache::get(self::ERRORS_KEY, []);

        foreach (self::plan($base, $secret) as $hook) {
            try {
                $current = collect($brevo->webhooks($hook['type']))
                    ->filter(fn ($w) => $hook['type'] !== 'inbound' || ($w['domain'] ?? null) === $hook['domain']);
                $existing = $current->first(fn ($w) => ($w['url'] ?? null) === $hook['url']);

                // Webhook Surel Kendari ke URL lama (mis. tunnel yang sudah berganti) dihapus agar
                // Brevo tidak terus mengirim ke alamat mati. Dikenali dari path + rahasia.
                $path = (string) parse_url($hook['url'], PHP_URL_PATH);
                $current
                    ->filter(fn ($w) => ($w['url'] ?? '') !== $hook['url'] && str_ends_with((string) ($w['url'] ?? ''), $path))
                    ->each(function ($w) use ($brevo, $hook) {
                        $brevo->deleteWebhook((int) $w['id']);
                        $this->line("• Dihapus (URL lama): {$hook['description']}");
                    });

                if ($existing) {
                    $this->line("• Sudah ada: {$hook['description']}");
                } else {
                    $brevo->createWebhook($hook);
                    $this->info("✓ Dibuat: {$hook['description']}");
                }

                unset($errors[self::key($hook)]);
            } catch (Throwable $e) {
                // Pesan asli Brevo (mis. domain belum siap, izin kurang) jauh lebih berguna daripada "HTTP 400".
                $reason = $e instanceof RequestException
                    ? trim(($e->response->json('code') ? $e->response->json('code').': ' : '').($e->response->json('message') ?? $e->response->body()))
                    : $e->getMessage();

                $errors[self::key($hook)] = ['message' => $reason, 'at' => now()->toIso8601String()];
                Log::warning('Brevo menolak pendaftaran webhook', ['hook' => collect($hook)->except('url')->all(), 'reason' => $reason]);
                $this->error("✗ {$hook['description']}: $reason");
            }
        }

        Cache::put(self::ERRORS_KEY, $errors, now()->addWeek());

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    public static function plan(string $base, string $secret): array
    {
        $hooks = [[
            'type' => 'transactional',
            'description' => 'Surel Kendari — status pengiriman',
            'url' => "$base/webhooks/brevo/events/$secret",
            'events' => config('kdimail.brevo.events'),
        ]];

        foreach (Domain::query()->where('is_active', true)->get() as $domain) {
            $hooks[] = [
                'type' => 'inbound',
                'description' => "Surel Kendari — email masuk {$domain->inbound_domain}",
                'url' => "$base/webhooks/brevo/inbound/$secret",
                'events' => ['inboundEmailProcessed'],
                'domain' => $domain->inbound_domain,
            ];
        }

        return $hooks;
    }
}
