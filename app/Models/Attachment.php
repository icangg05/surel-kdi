<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int|null $message_id
 * @property int|null $broadcast_id
 * @property int|null $user_id
 * @property string $filename
 * @property string $mime
 * @property int $size
 * @property string|null $path
 * @property string|null $content_id
 * @property string|null $download_token
 * @property string|null $error
 * @property-read Message|null $message
 * @property-read Broadcast|null $broadcast
 */
#[Fillable(['message_id', 'broadcast_id', 'user_id', 'filename', 'mime', 'size', 'path', 'content_id', 'download_token', 'error'])]
class Attachment extends Model
{
    /** Disk privat; berkas hanya keluar lewat controller berotorisasi. */
    public const DISK = 'local';

    protected $hidden = ['download_token', 'path'];

    protected static function booted(): void
    {
        static::deleting(function (Attachment $attachment) {
            if ($attachment->path) {
                Storage::disk(self::DISK)->delete($attachment->path);
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

    public function isAvailable(): bool
    {
        return $this->path !== null;
    }

    public function isInlineImage(): bool
    {
        return $this->content_id !== null && str_starts_with($this->mime, 'image/');
    }
}
