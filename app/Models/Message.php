<?php

namespace App\Models;

use App\Enums\MessageStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Satu email di dalam mailbox, masuk maupun keluar.
 *
 * @property int $id
 * @property string $uuid
 * @property int $mailbox_id
 * @property int|null $thread_id
 * @property int|null $user_id
 * @property int|null $contact_id
 * @property string $direction
 * @property MessageStatus $status
 * @property string|null $message_id_header
 * @property string|null $in_reply_to
 * @property string|null $references
 * @property string|null $from_name
 * @property string $from_email
 * @property list<array{email: string, name?: string|null}>|null $to
 * @property list<array{email: string, name?: string|null}>|null $cc
 * @property list<array{email: string, name?: string|null}>|null $bcc
 * @property string|null $reply_to
 * @property string $subject
 * @property string|null $body_html
 * @property string|null $body_text
 * @property string $snippet
 * @property int $size
 * @property float|null $spam_score
 * @property bool $is_read
 * @property bool $has_attachments
 * @property string|null $error
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $received_at
 * @property-read Mailbox $mailbox
 * @property-read Thread|null $thread
 */
#[Fillable([
    'mailbox_id', 'thread_id', 'user_id', 'contact_id', 'direction', 'status', 'message_id_header',
    'in_reply_to', 'references', 'from_name', 'from_email', 'to', 'cc', 'bcc', 'reply_to', 'subject',
    'body_html', 'body_text', 'snippet', 'size', 'spam_score', 'is_read', 'has_attachments', 'error',
    'scheduled_at', 'sent_at', 'received_at',
])]
class Message extends Model
{
    public const IN = 'in';

    public const OUT = 'out';

    protected function casts(): array
    {
        return [
            'status' => MessageStatus::class,
            'to' => 'array',
            'cc' => 'array',
            'bcc' => 'array',
            'spam_score' => 'float',
            'is_read' => 'boolean',
            'has_attachments' => 'boolean',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Message $message) {
            $message->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Mailbox, $this> */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    /** @return BelongsTo<Thread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function isOutgoing(): bool
    {
        return $this->direction === self::OUT;
    }

    /**
     * Semua penerima (to + cc + bcc), huruf kecil, unik.
     *
     * @return list<array{email: string, name: string|null, kind: string}>
     */
    public function recipients(): array
    {
        $all = [];

        foreach (['to', 'cc', 'bcc'] as $kind) {
            foreach ($this->{$kind} ?? [] as $r) {
                $email = mb_strtolower($r['email']);
                $all[$email] ??= ['email' => $email, 'name' => $r['name'] ?? null, 'kind' => $kind];
            }
        }

        return array_values($all);
    }

    /** Domain Message-ID diambil dari domain pengirim, sesuai RFC 5322. */
    public function makeMessageIdHeader(): string
    {
        return $this->uuid.'@'.Str::after($this->from_email, '@');
    }
}
