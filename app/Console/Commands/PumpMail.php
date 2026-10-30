<?php

namespace App\Console\Commands;

use App\Services\BroadcastPump;
use App\Services\Outbox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mail:pump')]
#[Description('Lepas email terjadwal/menunggu kuota dan pompa siaran yang berjalan (tiap menit)')]
class PumpMail extends Command
{
    public function handle(Outbox $outbox, BroadcastPump $pump): int
    {
        $messages = $outbox->dispatchDue();
        $broadcast = $pump->pump();

        if ($messages + $broadcast > 0) {
            $this->info("Email biasa: $messages, email siaran: $broadcast dilepas ke antrean.");
        }

        return self::SUCCESS;
    }
}
