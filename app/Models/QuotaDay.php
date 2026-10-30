<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $date
 * @property int $used
 * @property bool $exhausted
 */
#[Fillable(['date', 'used', 'exhausted'])]
class QuotaDay extends Model
{
    protected $primaryKey = 'date';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['exhausted' => 'boolean'];
    }
}
