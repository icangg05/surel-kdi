<?php

namespace App\Support;

use App\Enums\MessageStatus;
use App\Models\Attachment;
use App\Models\Delivery;
use App\Models\Message;
use App\Models\Thread;
use Illuminate\Support\Collection;

/** Bentuk data email untuk halaman Inertia — satu tempat supaya daftar & panel baca konsisten. */
class MailPresenter
{
    /** @return array<string, mixed> */
    public static function threadRow(Thread $thread): array
    {
        /** @var Collection<int, Message> $messages */
        $messages = $thread->messages->where('status', '!=', MessageStatus::Draft);
        $latest = $messages->last();

        return [
            'key' => 't'.$thread->id,
            'kind' => 'thread',
            'id' => $thread->id,
            'subject' => $thread->subject ?: '(tanpa subjek)',
            'snippet' => $latest->snippet ?? '',
            'participants' => self::participants($messages),
            'date' => $thread->last_message_at?->toIso8601String(),
            'unread' => $thread->unread_count > 0,
            'starred' => $thread->is_starred,
            'has_attachments' => $messages->contains('has_attachments', true),
            'count' => $thread->message_count,
            'folder' => $thread->folder?->value,
            'broadcast' => $thread->broadcast_id !== null,
            'status' => null,
        ];
    }

    /** Baris untuk folder Draf & Terjadwal: satu email keluar, bukan utas. */
    public static function outgoingRow(Message $message): array
    {
        $to = collect($message->recipients())->map(fn ($r) => $r['name'] ?: $r['email']);

        return [
            'key' => 'm'.$message->id,
            'kind' => $message->status->value === 'draft' ? 'draft' : 'outgoing',
            'id' => $message->id,
            'thread_id' => $message->thread_id,
            'subject' => $message->subject ?: '(tanpa subjek)',
            'snippet' => $message->snippet ?: MailHtml::snippet(MailHtml::toText($message->body_html)),
            'participants' => $to->isEmpty() ? '(belum ada penerima)' : 'Kepada: '.$to->take(3)->implode(', ').($to->count() > 3 ? ' +'.($to->count() - 3) : ''),
            'date' => ($message->scheduled_at ?? $message->updated_at)?->toIso8601String(),
            'unread' => false,
            'starred' => false,
            'has_attachments' => $message->has_attachments,
            'count' => 1,
            'folder' => null,
            'broadcast' => false,
            'status' => ['value' => $message->status->value, 'label' => $message->status->label()],
            'error' => $message->error,
        ];
    }

    /** @param Collection<int, Message> $messages */
    private static function participants(Collection $messages): string
    {
        $names = $messages->map(fn (Message $m) => $m->isOutgoing() ? 'Saya' : ($m->from_name ?: $m->from_email))->unique()->values();

        if ($names->count() === 1 && $names->first() === 'Saya') {
            $first = $messages->first();
            $to = collect($first->to ?? [])->map(fn ($r) => $r['name'] ?? $r['email']);

            return 'Kepada: '.$to->take(2)->implode(', ').($to->count() > 2 ? ' +'.($to->count() - 2) : '');
        }

        return $names->take(3)->implode(', ').($names->count() > 3 ? ' …' : '');
    }

    /** @return array<string, mixed> */
    public static function message(Message $message): array
    {
        $outgoing = $message->isOutgoing();

        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'status' => ['value' => $message->status->value, 'label' => $message->status->label()],
            'from_name' => $message->from_name,
            'from_email' => $message->from_email,
            'to' => $message->to ?? [],
            'cc' => $message->cc ?? [],
            'bcc' => $outgoing ? ($message->bcc ?? []) : [],
            'reply_to' => $message->reply_to,
            'subject' => $message->subject,
            'snippet' => $message->snippet,
            'date' => ($message->received_at ?? $message->sent_at ?? $message->scheduled_at ?? $message->created_at)?->toIso8601String(),
            'is_read' => $message->is_read,
            'body_url' => route('messages.body', $message),
            'has_remote_images' => (bool) preg_match('~(<img[^>]+src=["\']?https?:|url\(\s*["\']?https?:)~i', (string) $message->body_html),
            'spam_score' => $message->spam_score,
            'error' => $message->error,
            'author' => $message->author?->name,
            'contact' => $message->contact?->only('id', 'name', 'position', 'organization', 'email'),
            'attachments' => $message->attachments->reject(fn (Attachment $a) => $a->isInlineImage())->map(fn (Attachment $a) => self::attachment($a))->values(),
            'deliveries' => $outgoing ? $message->deliveries->map(fn (Delivery $d) => [
                'email' => $d->email,
                'name' => $d->name,
                'status' => $d->status->value,
                'label' => $d->status->label(),
                'opened_at' => $d->opened_at?->toIso8601String(),
                'error' => $d->error,
            ])->values() : [],
        ];
    }

    /** @return array<string, mixed> */
    public static function attachment(Attachment $a): array
    {
        return [
            'id' => $a->id,
            'filename' => $a->filename,
            'mime' => $a->mime,
            'size' => $a->size,
            'available' => $a->isAvailable(),
            'error' => $a->error,
            'url' => route('attachments.show', $a),
        ];
    }

    /** @return array<string, mixed> */
    public static function draft(Message $message): array
    {
        $message->loadMissing('attachments');

        return [
            'id' => $message->id,
            'mailbox_id' => $message->mailbox_id,
            'thread_id' => $message->thread_id,
            'to' => $message->to ?? [],
            'cc' => $message->cc ?? [],
            'bcc' => $message->bcc ?? [],
            'subject' => $message->subject,
            'body_html' => $message->body_html ?? '',
            'attachments' => $message->attachments->map(fn (Attachment $a) => self::attachment($a))->values(),
            'is_reply' => $message->in_reply_to !== null,
        ];
    }
}
