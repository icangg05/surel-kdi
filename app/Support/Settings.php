<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan aplikasi yang bisa diubah admin, dengan nilai bawaan dari
 * config/kdimail.php. Dibaca sangat sering (tiap pengiriman), jadi di-cache.
 */
class Settings
{
    private const CACHE_KEY = 'kdimail.settings';

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        return $all[$key] ?? config("kdimail.defaults.$key", $default);
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return collect(config('kdimail.defaults'))->map(fn ($v, $k) => self::get($k))->all();
    }

    public static function dailyLimit(): int
    {
        return (int) self::get('daily_limit');
    }

    public static function perMinute(): int
    {
        return max(1, (int) self::get('per_minute'));
    }

    public static function spamThreshold(): float
    {
        return (float) self::get('spam_threshold');
    }

    public static function quotaTimezone(): string
    {
        return (string) self::get('quota_timezone');
    }

    public static function replyTracking(): bool
    {
        return (bool) self::get('reply_tracking');
    }
}
