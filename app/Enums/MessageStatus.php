<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case WaitingQuota = 'waiting_quota';
    case Sent = 'sent';
    case Failed = 'failed';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Scheduled => 'Terjadwal',
            self::Queued => 'Dalam antrean',
            self::WaitingQuota => 'Menunggu kuota',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal',
            self::Received => 'Diterima',
        };
    }

    /**
     * Status keluar yang masih menunggu berangkat.
     *
     * @return list<self>
     */
    public static function pending(): array
    {
        return [self::Scheduled, self::Queued, self::WaitingQuota];
    }
}
