<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Satu alamat penerima dari satu email keluar.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $message_id
 * @property int|null $broadcast_id
 * @property int|null $contact_id
 * @property int|null $thread_id
 * @property string $email
 * @property string|null $name
 * @property string $kind
 * @property DeliveryStatus $status
 * @property string|null $reply_token
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $clicked_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $replied_at
 * @property-read Broadcast|null $broadcast
 * @property-read Message|null $message
 * @property-read Contact|null $contact
 */
#[Fillable([
    'message_id', 'broadcast_id', 'contact_id', 'thread_id', 'email', 'name', 'kind', 'status', 'reply_token',
    'provider_message_id', 'error', 'sent_at', 'delivered_at', 'opened_at', 'clicked_at', 'failed_at', 'replied_at',
])]
class Delivery extends Model
{
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'failed_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Delivery $delivery) {
            $delivery->uuid ??= (string) Str::uuid();

            if ($delivery->broadcast_id && ! $delivery->reply_token) {
                $delivery->reply_token = 'b'.Str::lower(Str::random(11));
            }
        });
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** @return BelongsTo<Broadcast, $this> */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Thread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /** @return HasMany<DeliveryEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class)->orderBy('occurred_at');
    }
}
