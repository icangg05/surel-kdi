<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Catat perubahan kolom fillable ke log aktivitas (halaman Admin › Log Aktivitas). */
trait Audited
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('audit')
            ->logFillable()
            ->logExcept(['password', 'body_html', 'signature_html'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
