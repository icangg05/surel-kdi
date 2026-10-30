<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $inbound_domain
 * @property bool $is_active
 * @property array<string, mixed>|null $dns_status
 * @property CarbonImmutable|null $dns_checked_at
 */
#[Fillable(['name', 'inbound_domain', 'is_active'])]
class Domain extends Model
{
    use Audited;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'dns_status' => 'array',
            'dns_checked_at' => 'datetime',
        ];
    }

    /** @return HasMany<Mailbox, $this> */
    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class);
    }
}
