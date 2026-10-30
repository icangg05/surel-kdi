<?php

namespace App\Console\Commands;

use App\Services\Brevo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Signature('brevo:sync-account')]
#[Description('Ambil paket & sisa kredit akun Brevo untuk dasbor')]
class BrevoSyncAccount extends Command
{
    public const CACHE_KEY = 'brevo.account';

    public function handle(Brevo $brevo): int
    {
        if (! $brevo->configured()) {
            return self::SUCCESS;
        }

        try {
            $account = $brevo->account();
            Cache::put(self::CACHE_KEY, [
                'email' => $account['email'] ?? null,
                'company' => $account['companyName'] ?? null,
                'plan' => collect($account['plan'] ?? [])->map(fn ($p) => [
                    'type' => $p['type'] ?? null,
                    'credits' => $p['credits'] ?? null,
                    'credits_type' => $p['creditsType'] ?? null,
                    'end_date' => $p['endDate'] ?? null,
                ])->all(),
                'synced_at' => now()->toIso8601String(),
            ], now()->addDay());
            $this->info('Akun Brevo tersinkron.');
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
