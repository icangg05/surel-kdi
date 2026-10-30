<?php

namespace App\Services;

use App\Models\QuotaDay;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Kredit kirim harian Brevo. Setiap alamat penerima = 1 kredit. Reservasi
 * dilakukan sebelum SMTP dipanggil dan dikembalikan bila pengiriman gagal,
 * dengan kunci baris supaya beberapa worker tidak melampaui limit bersama.
 */
class QuotaManager
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now(Settings::quotaTimezone());
    }

    public function today(): string
    {
        return $this->now()->toDateString();
    }

    public function limit(): int
    {
        return Settings::dailyLimit();
    }

    public function used(?string $date = null): int
    {
        return (int) QuotaDay::query()->whereKey($date ?? $this->today())->value('used');
    }

    public function remaining(): int
    {
        $day = QuotaDay::query()->find($this->today());

        if ($day?->exhausted) {
            return 0;
        }

        return max(0, $this->limit() - (int) $day?->used);
    }

    public function reserve(int $credits): bool
    {
        if ($credits <= 0) {
            return true;
        }

        $date = $this->today();
        QuotaDay::query()->insertOrIgnore(['date' => $date, 'used' => 0, 'exhausted' => false, 'created_at' => now(), 'updated_at' => now()]);

        return DB::transaction(function () use ($date, $credits) {
            /** @var QuotaDay $day */
            $day = QuotaDay::query()->whereKey($date)->lockForUpdate()->firstOrFail();

            if ($day->exhausted || $day->used + $credits > $this->limit()) {
                return false;
            }

            $day->increment('used', $credits);

            return true;
        });
    }

    public function release(int $credits): void
    {
        if ($credits > 0) {
            QuotaDay::query()->whereKey($this->today())->where('used', '>=', $credits)->decrement('used', $credits);
        }
    }

    /** Brevo menolak karena limit harian akun — berhenti mencoba sampai reset. */
    public function markExhausted(): void
    {
        QuotaDay::query()->updateOrCreate(['date' => $this->today()], ['exhausted' => true]);
    }

    /** Waktu reset berikutnya, dalam zona waktu aplikasi untuk ditampilkan. */
    public function resetsAt(): CarbonImmutable
    {
        return $this->now()->addDay()->startOfDay()->setTimezone(config('app.timezone'));
    }

    /**
     * Pemakaian beberapa hari terakhir, termasuk hari tanpa kiriman.
     *
     * @return list<array{date: string, used: int}>
     */
    public function history(int $days = 14): array
    {
        $start = $this->now()->subDays($days - 1)->toDateString();
        $rows = QuotaDay::query()->where('date', '>=', $start)->pluck('used', 'date');

        return collect(range($days - 1, 0))
            ->map(fn (int $ago) => $this->now()->subDays($ago)->toDateString())
            ->map(fn (string $d) => ['date' => $d, 'used' => (int) ($rows[$d] ?? 0)])
            ->all();
    }

    /**
     * Perkiraan berapa yang terkirim per hari untuk sejumlah kredit, mulai hari ini.
     *
     * @return list<array{date: string, count: int}>
     */
    public function forecast(int $credits, ?CarbonImmutable $startAt = null): array
    {
        $limit = max(1, $this->limit());
        $plan = [];
        $day = ($startAt ?? $this->now())->setTimezone(Settings::quotaTimezone());
        $available = $day->isSameDay($this->now()) ? $this->remaining() : $limit;

        while ($credits > 0 && count($plan) < 60) {
            $take = min($credits, $available);

            if ($take > 0) {
                $plan[] = ['date' => $day->toDateString(), 'count' => $take];
                $credits -= $take;
            }

            $day = $day->addDay()->startOfDay();
            $available = $limit;
        }

        return $plan;
    }
}
