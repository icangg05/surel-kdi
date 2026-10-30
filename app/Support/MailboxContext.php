<?php

namespace App\Support;

use App\Enums\Folder;
use App\Enums\MessageStatus;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Mailbox yang sedang dibuka operator (diingat di sesi) dan hitungan
 * folder untuk sidebar.
 */
class MailboxContext
{
    private const SESSION_KEY = 'mailbox.current';

    /** @return Collection<int, Mailbox> */
    public static function accessible(User $user): Collection
    {
        return once(fn () => $user->accessibleMailboxes()->get());
    }

    public static function current(User $user): ?Mailbox
    {
        $mailboxes = self::accessible($user);

        return $mailboxes->firstWhere('id', session(self::SESSION_KEY)) ?? $mailboxes->first();
    }

    public static function remember(Mailbox $mailbox): void
    {
        session([self::SESSION_KEY => $mailbox->id]);
    }

    /** @return array<string, int> */
    public static function counts(Mailbox $mailbox): array
    {
        $threads = Thread::query()->where('mailbox_id', $mailbox->id);

        return [
            'inbox' => (int) (clone $threads)->where('folder', Folder::Inbox)->sum('unread_count'),
            'spam' => (clone $threads)->where('folder', Folder::Spam)->where('unread_count', '>', 0)->count(),
            'drafts' => Message::query()->where('mailbox_id', $mailbox->id)->where('status', MessageStatus::Draft)->count(),
            'scheduled' => Message::query()->where('mailbox_id', $mailbox->id)->whereIn('status', [...MessageStatus::pending(), MessageStatus::Failed])->count(),
        ];
    }
}
