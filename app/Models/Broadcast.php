<?php

namespace App\Models;

use App\Enums\BroadcastStatus;
use App\Enums\DeliveryStatus;
use App\Models\Concerns\Audited;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Siaran: satu isi, dikirim personal ke banyak kontak (satu email per penerima).
 *
 * @property int $id
 * @property int $mailbox_id
 * @property int|null $user_id
 * @property int|null $template_id
 * @property string $subject
 * @property string $body_html
 * @property array{groups?: list<int>, contacts?: list<int>}|null $audience
 * @property BroadcastStatus $status
 * @property int $total
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property-read Mailbox $mailbox
 */
#[Fillable(['mailbox_id', 'user_id', 'template_id', 'subject', 'body_html', 'audience', 'status', 'total', 'scheduled_at', 'started_at', 'completed_at'])]
class Broadcast extends Model
{
    use Audited;

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'status' => BroadcastStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Mailbox, $this> */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /** @return HasMany<Thread, $this> */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [BroadcastStatus::Draft, BroadcastStatus::Scheduled], true);
    }

    /**
     * Hitungan per status untuk progres.
     *
     * @return array<string, int>
     */
    public function stats(): array
    {
        $counts = $this->deliveries()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($c) => (int) $c);
        $get = fn (DeliveryStatus ...$s) => array_sum(array_map(fn ($x) => $counts[$x->value] ?? 0, $s));

        return [
            'total' => $this->total,
            'pending' => $get(DeliveryStatus::Pending, DeliveryStatus::Queued),
            'sent' => $get(DeliveryStatus::Sent, DeliveryStatus::Delivered, DeliveryStatus::Opened, DeliveryStatus::Clicked, DeliveryStatus::SoftBounce, DeliveryStatus::Spam),
            'delivered' => $get(DeliveryStatus::Delivered, DeliveryStatus::Opened, DeliveryStatus::Clicked, DeliveryStatus::Spam),
            'opened' => $get(DeliveryStatus::Opened, DeliveryStatus::Clicked),
            'failed' => $get(DeliveryStatus::HardBounce, DeliveryStatus::Blocked, DeliveryStatus::Failed),
            'skipped' => $get(DeliveryStatus::Skipped, DeliveryStatus::Cancelled),
            'replied' => $this->deliveries()->whereNotNull('replied_at')->count(),
        ];
    }
}
