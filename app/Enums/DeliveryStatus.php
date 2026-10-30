<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case SoftBounce = 'soft_bounce';
    case HardBounce = 'hard_bounce';
    case Blocked = 'blocked';
    case Spam = 'spam';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Queued => 'Dalam antrean',
            self::Sent => 'Terkirim ke Brevo',
            self::Delivered => 'Diterima',
            self::Opened => 'Dibuka',
            self::Clicked => 'Diklik',
            self::SoftBounce => 'Tertunda (soft bounce)',
            self::HardBounce => 'Ditolak (hard bounce)',
            self::Blocked => 'Diblokir',
            self::Spam => 'Ditandai spam',
            self::Failed => 'Gagal',
            self::Skipped => 'Dilewati',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Urutan kemajuan untuk status positif. Webhook Brevo bisa datang tidak
     * berurutan (opened sebelum delivered), jadi status hanya boleh naik.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Pending, self::Skipped, self::Cancelled => 0,
            self::Queued => 1,
            self::Sent, self::SoftBounce => 2,
            self::Delivered => 3,
            self::Opened => 4,
            self::Clicked => 5,
            self::HardBounce, self::Blocked, self::Failed => 2,
            self::Spam => 6,
        };
    }

    public function isFailure(): bool
    {
        return in_array($this, [self::HardBounce, self::Blocked, self::Failed, self::Spam], true);
    }
}
