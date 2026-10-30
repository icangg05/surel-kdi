<?php

namespace App\Console\Commands;

use App\Enums\Folder;
use App\Models\Attachment;
use App\Models\Thread;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mail:purge-trash')]
#[Description('Hapus permanen utas di Sampah yang lebih lama dari batas retensi')]
class PurgeTrash extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('kdimail.trash_retention_days'));
        $count = 0;

        Thread::query()->where('folder', Folder::Trash)->where('trashed_at', '<', $cutoff)->each(function (Thread $thread) use (&$count) {
            // Lewat model supaya berkas lampiran ikut terhapus dari disk.
            Attachment::query()->whereIn('message_id', $thread->messages()->select('id'))->get()->each->delete();
            $thread->delete();
            $count++;
        });

        $this->info("$count utas dihapus.");

        return self::SUCCESS;
    }
}
