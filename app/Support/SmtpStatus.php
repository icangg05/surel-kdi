<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

/**
 * Kesehatan SMTP Brevo. Penolakan konfigurasi (IP belum diizinkan, login
 * salah) tidak ada gunanya dicoba ulang: pengiriman dihentikan, pesannya
 * disimpan, dan seluruh aplikasi menampilkan banner sampai ada kiriman sukses.
 */
class SmtpStatus
{
    private const ERROR_KEY = 'kdimail.smtp_error';

    private const PROBE_KEY = 'kdimail.smtp_probe';

    public static function isConfigError(Throwable $e): bool
    {
        return (bool) preg_match('/unauthorized ip|failed to authenticate|authentication failed|\b535\b|\b525\b|connection (could not be established|refused)/i', $e->getMessage());
    }

    public static function friendly(string $raw): string
    {
        return match (true) {
            (bool) preg_match('/unauthorized ip/i', $raw) => 'Brevo menolak IP server ini. Izinkan IP-nya di Brevo › Security › Authorized IPs.',
            (bool) preg_match('/authenticate|535/i', $raw) => 'Login SMTP Brevo ditolak. Periksa MAIL_USERNAME/MAIL_PASSWORD (kunci SMTP) di .env.',
            (bool) preg_match('/connection/i', $raw) => 'Tidak bisa terhubung ke server SMTP Brevo. Periksa koneksi internet server.',
            default => mb_substr($raw, 0, 200),
        };
    }

    public static function fail(Throwable $e): string
    {
        $message = self::friendly($e->getMessage());
        Cache::put(self::ERROR_KEY, ['message' => $message, 'at' => now()->toIso8601String()], now()->addDays(2));
        Cache::forget(self::PROBE_KEY);

        return $message;
    }

    public static function ok(): void
    {
        Cache::forget(self::ERROR_KEY);
    }

    /** @return array{message: string, at: string}|null */
    public static function current(): ?array
    {
        return Cache::get(self::ERROR_KEY);
    }

    /**
     * Uji login SMTP tanpa mengirim email (dan tanpa memakai kuota). Di-cache 5 menit.
     *
     * @return array{ok: bool, message: string|null, checked_at: string}
     */
    public static function probe(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::PROBE_KEY);
        }

        return Cache::remember(self::PROBE_KEY, now()->addMinutes(5), function () {
            $transport = Mail::mailer('smtp')->getSymfonyTransport();

            try {
                if ($transport instanceof SmtpTransport) {
                    $transport->start();
                    $transport->stop();
                }
                self::ok();

                return ['ok' => true, 'message' => null, 'checked_at' => now()->toIso8601String()];
            } catch (Throwable $e) {
                return ['ok' => false, 'message' => self::friendly($e->getMessage()), 'checked_at' => now()->toIso8601String()];
            }
        });
    }
}
