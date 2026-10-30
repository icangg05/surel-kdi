<?php

namespace App\Enums;

enum BroadcastStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case WaitingQuota = 'waiting_quota';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Scheduled => 'Terjadwal',
            self::Sending => 'Mengirim',
            self::WaitingQuota => 'Menunggu kuota',
            self::Paused => 'Dijeda',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Siaran yang dipompa scheduler tiap menit.
     *
     * @return list<self>
     */
    public static function running(): array
    {
        return [self::Sending, self::WaitingQuota];
    }
}
