<?php

namespace App\Enums;

enum ContactStatus: string
{
    case Active = 'active';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Bounced => 'Alamat ditolak',
            self::Complained => 'Ditandai spam',
            self::Blocked => 'Diblokir',
        };
    }
}
