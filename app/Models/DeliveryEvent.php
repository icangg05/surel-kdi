<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $delivery_id
 * @property string $event
 * @property CarbonImmutable $occurred_at
 * @property array<string, mixed>|null $payload
 */
#[Fillable(['delivery_id', 'event', 'occurred_at', 'payload'])]
class DeliveryEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'payload' => 'array'];
    }

    /** @return BelongsTo<Delivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
