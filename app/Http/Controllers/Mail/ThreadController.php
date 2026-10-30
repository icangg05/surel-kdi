<?php

namespace App\Http\Controllers\Mail;

use App\Enums\Folder;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Thread;
use App\Support\MailboxContext;
use App\Support\MailPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ThreadController extends Controller
{
    public const FOLDERS = ['inbox', 'starred', 'sent', 'drafts', 'scheduled', 'archive', 'spam', 'trash'];

    public function redirect(Request $request): RedirectResponse
    {
        $mailbox = MailboxContext::current($request->user());

        abort_unless($mailbox !== null, 403, 'Anda belum ditugaskan ke mailbox mana pun. Hubungi admin.');

        return to_route('mail.index', [$mailbox, 'inbox']);
    }

    public function index(Request $request, Mailbox $mailbox, string $folder = 'inbox', ?Thread $thread = null): Response
    {
        $this->authorizeMailbox($request, $mailbox);
        abort_if($thread && $thread->mailbox_id !== $mailbox->id, 404);
        MailboxContext::remember($mailbox);

        $search = trim((string) $request->query('q', ''));
        $rows = in_array($folder, ['drafts', 'scheduled'], true)
            ? $this->outgoingRows($mailbox, $folder, $search)
            : $this->threadRows($mailbox, $folder, $search);

        return Inertia::render('mail/Index', [
            'mailbox' => ['id' => $mailbox->id, 'name' => $mailbox->display_name, 'address' => $mailbox->address],
            'folder' => $folder,
            'search' => $search,
            'rows' => $rows,
            'thread' => $thread ? fn () => $this->threadDetail($thread) : null,
        ]);
    }

    private function threadRows(Mailbox $mailbox, string $folder, string $search): mixed
    {
        $query = Thread::query()
            ->where('mailbox_id', $mailbox->id)
            ->with(['messages' => fn ($q) => $q->select('id', 'thread_id', 'direction', 'status', 'from_name', 'from_email', 'to', 'snippet', 'has_attachments', 'received_at', 'sent_at', 'created_at')]);

        match ($folder) {
            'starred' => $query->where('is_starred', true)->where(fn ($q) => $q->whereNull('folder')->orWhere('folder', '!=', Folder::Trash)),
            'sent' => $query->whereHas('messages', fn ($q) => $q->where('direction', Message::OUT)->where('status', MessageStatus::Sent))
                ->where(fn ($q) => $q->whereNull('folder')->orWhere('folder', '!=', Folder::Trash)),
            default => $query->where('folder', $folder),
        };

        if ($search !== '') {
            $query->whereHas('messages', fn (Builder $q) => $this->searchMessages($q, $search));
        }

        return $query->orderByDesc('last_message_at')->paginate(30)->withQueryString()
            ->through(fn (Thread $t) => MailPresenter::threadRow($t));
    }

    private function outgoingRows(Mailbox $mailbox, string $folder, string $search): mixed
    {
        $statuses = $folder === 'drafts' ? [MessageStatus::Draft] : [...MessageStatus::pending(), MessageStatus::Failed];

        return Message::query()
            ->where('mailbox_id', $mailbox->id)
            ->whereIn('status', $statuses)
            ->when($search !== '', fn ($q) => $this->searchMessages($q, $search))
            ->orderByRaw('coalesce(scheduled_at, updated_at) desc')
            ->paginate(30)->withQueryString()
            ->through(fn (Message $m) => MailPresenter::outgoingRow($m));
    }

    /** @param Builder<Message> $query */
    private function searchMessages(Builder $query, string $search): void
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

        $query->where(function (Builder $q) use ($search, $like) {
            if (DB::getDriverName() === 'mysql' && mb_strlen($search) >= 3) {
                $q->whereFullText(['subject', 'body_text'], $search);
            } else {
                $q->where('subject', 'like', $like)->orWhere('body_text', 'like', $like);
            }

            $q->orWhere('from_email', 'like', $like)->orWhere('from_name', 'like', $like)->orWhere('to', 'like', $like);
        });
    }

    /** @return array<string, mixed> */
    private function threadDetail(Thread $thread): array
    {
        $messages = $thread->messages()
            ->where('status', '!=', MessageStatus::Draft)
            ->with(['attachments', 'deliveries', 'contact', 'author'])
            ->get();

        if ($thread->unread_count > 0) {
            Message::query()->where('thread_id', $thread->id)->where('is_read', false)->update(['is_read' => true]);
            $thread->refreshSummary();
        }

        $thread->loadMissing('broadcast:id,subject');

        return [
            'id' => $thread->id,
            'subject' => $thread->subject ?: '(tanpa subjek)',
            'folder' => $thread->folder?->value,
            'is_starred' => $thread->is_starred,
            'broadcast' => $thread->broadcast?->only('id', 'subject'),
            'messages' => $messages->map(fn (Message $m) => MailPresenter::message($m))->values(),
        ];
    }

    /** Tandai bintang / baca / pindah folder untuk satu utas. */
    public function update(Request $request, Thread $thread): RedirectResponse
    {
        $this->authorizeMailbox($request, $thread->mailbox);
        $this->applyChanges(collect([$thread]), $this->validatedChanges($request));

        return back();
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']]);
        $changes = $this->validatedChanges($request);
        $threads = Thread::query()->with('mailbox')->whereIn('id', $data['ids'])->get()
            ->filter(fn (Thread $t) => $request->user()->canAccessMailbox($t->mailbox));

        $this->applyChanges($threads, $changes);

        return back();
    }

    /** Hapus permanen — hanya dari Sampah/Spam. */
    public function destroy(Request $request, Thread $thread): RedirectResponse
    {
        $this->authorizeMailbox($request, $thread->mailbox);
        abort_unless(in_array($thread->folder, [Folder::Trash, Folder::Spam], true), 422, 'Pindahkan ke Sampah dulu.');

        Attachment::query()->whereIn('message_id', $thread->messages()->select('id'))->get()->each->delete();
        $thread->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utas dihapus permanen.']);

        return to_route('mail.index', [$thread->mailbox_id, $thread->folder->value]);
    }

    /** @return array<string, mixed> */
    private function validatedChanges(Request $request): array
    {
        return $request->validate([
            'starred' => ['sometimes', 'boolean'],
            'read' => ['sometimes', 'boolean'],
            'folder' => ['sometimes', Rule::in(array_column(Folder::cases(), 'value'))],
        ]);
    }

    /**
     * @param  Collection<int, Thread>  $threads
     * @param  array<string, mixed>  $changes
     */
    private function applyChanges($threads, array $changes): void
    {
        foreach ($threads as $thread) {
            if (array_key_exists('starred', $changes)) {
                $thread->is_starred = (bool) $changes['starred'];
            }

            if (array_key_exists('folder', $changes)) {
                $thread->folder = Folder::from($changes['folder']);
                $thread->trashed_at = $thread->folder === Folder::Trash ? now() : null;
            }

            $thread->save();

            if (array_key_exists('read', $changes)) {
                $thread->messages()->where('direction', Message::IN)->update(['is_read' => (bool) $changes['read']]);
                $thread->refreshSummary();
            }
        }
    }
}
