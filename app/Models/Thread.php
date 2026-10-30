<?php

namespace App\Models;

use App\Enums\Folder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $mailbox_id
 * @property int|null $broadcast_id
 * @property string $subject
 * @property string $reply_token
 * @property Folder|null $folder
 * @property bool $is_starred
 * @property int $unread_count
 * @property int $message_count
 * @property CarbonImmutable|null $last_message_at
 * @property CarbonImmutable|null $trashed_at
 * @property-read Mailbox $mailbox
 */
#[Fillable(['mailbox_id', 'broadcast_id', 'subject', 'reply_token', 'folder', 'is_starred', 'unread_count', 'message_count', 'last_message_at', 'trashed_at'])]
class Thread extends Model
{
    protected function casts(): array
    {
        return [
            'folder' => Folder::class,
            'is_starred' => 'boolean',
            'last_message_at' => 'datetime',
            'trashed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Thread $thread) {
            $thread->reply_token ??= static::newReplyToken();
        });
    }

    /**
     * Token huruf kecil + angka saja: bagian lokal alamat email bisa diubah
     * kapitalisasinya oleh server penerima, jadi jangan bergantung pada huruf besar.
     * Prefiks `t` membedakan dari token siaran (`b`) milik Delivery.
     */
    public static function newReplyToken(): string
    {
        return 't'.Str::lower(Str::random(11));
    }

    /** @return BelongsTo<Mailbox, $this> */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    /** @return BelongsTo<Broadcast, $this> */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderByRaw('coalesce(received_at, sent_at, created_at)');
    }

    /** @return HasOne<Message, $this> */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->where('status', '!=', 'draft')->latestOfMany();
    }

    /** Hitung ulang ringkasan utas dari pesan-pesannya. */
    public function refreshSummary(): void
    {
        $messages = $this->messages()->where('status', '!=', 'draft')->get(['is_read', 'received_at', 'sent_at', 'created_at']);

        $this->forceFill([
            'message_count' => $messages->count(),
            'unread_count' => $messages->where('is_read', false)->count(),
            'last_message_at' => $messages->map(fn (Message $m) => $m->received_at ?? $m->sent_at ?? $m->created_at)->max(),
        ])->save();
    }
}
