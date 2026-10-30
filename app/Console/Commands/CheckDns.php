<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\DnsChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dns:check')]
#[Description('Cek SPF/DKIM/DMARC/MX semua domain aktif')]
class CheckDns extends Command
{
    public function handle(DnsChecker $checker): int
    {
        foreach (Domain::query()->where('is_active', true)->get() as $domain) {
            $s = $checker->check($domain);
            $flag = fn (bool $ok) => $ok ? '✓' : '✗';
            $this->line("{$domain->name}: SPF {$flag($s['spf'])}  DKIM {$flag($s['dkim'])}  DMARC {$flag($s['dmarc'])}  MX {$domain->inbound_domain} {$flag($s['mx'])}");
        }

        return self::SUCCESS;
    }
}
