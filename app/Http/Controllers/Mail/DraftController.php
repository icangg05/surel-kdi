<?php

namespace App\Http\Controllers\Mail;

use App\Enums\BroadcastStatus;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\Mailbox;
use App\Models\Message;
use App\Services\Outbox;
use App\Support\MailHtml;
use App\Support\MailPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Panel Tulis: draf dibuat saat panel dibuka, disimpan otomatis (JSON),
 * lalu dikirim/dijadwalkan lewat Outbox.
 */
class DraftController extends Controller
{
    /** Buka panel tulis: email baru, balas, balas semua, atau teruskan. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mailbox_id' => ['required', 'integer', 'exists:mailboxes,id'],
            'mode' => ['nullable', Rule::in(['new', 'reply', 'reply_all', 'forward'])],
            'source_id' => ['nullable', 'integer', 'exists:messages,id'],
            'to' => ['nullable', 'array'],
        ]);

        $mailbox = Mailbox::query()->with('domain')->findOrFail((int) $data['mailbox_id']);
        $this->authorizeMailbox($request, $mailbox);
        $mode = $data['mode'] ?? 'new';
        $source = isset($data['source_id']) ? Message::query()->with('attachments')->find((int) $data['source_id']) : null;

        if ($source) {
            abort_unless($source->mailbox_id === $mailbox->id, 403);
        }

        $signature = $mailbox->signature_html ? '<p></p><div data-signature>'.$mailbox->signature_html.'</div>' : '';
        $draft = new Message([
            'mailbox_id' => $mailbox->id,
            'user_id' => $request->user()->id,
            'direction' => Message::OUT,
            'status' => MessageStatus::Draft,
            'from_email' => $mailbox->address,
            'from_name' => $mailbox->display_name,
            'to' => $this->cleanList($data['to'] ?? []),
            'subject' => '',
            'body_html' => '<p></p>'.$signature,
            'is_read' => true,
        ]);

        if ($source && $mode !== 'new') {
            $this->prefill($draft, $source, $mode, $mailbox, $signature);
        }

        $draft->save();

        if ($source && $mode === 'forward') {
            $this->copyAttachments($source, $draft);
        }

        return response()->json(MailPresenter::draft($draft));
    }

    public function show(Request $request, Message $message): JsonResponse
    {
        $this->authorizeDraft($request, $message);

        return response()->json(MailPresenter::draft($message));
    }

    /** Simpan otomatis tiap beberapa detik dari panel tulis. */
    public function update(Request $request, Message $message): JsonResponse
    {
        $this->authorizeDraft($request, $message);

        $data = $request->validate([
            'mailbox_id' => ['sometimes', 'integer', 'exists:mailboxes,id'],
            'to' => ['array'], 'cc' => ['array'], 'bcc' => ['array'],
            'subject' => ['nullable', 'string', 'max:250'],
            'body_html' => ['nullable', 'string', 'max:2000000'],
        ]);

        if (isset($data['mailbox_id']) && $data['mailbox_id'] !== $message->mailbox_id) {
            $mailbox = Mailbox::query()->with('domain')->findOrFail((int) $data['mailbox_id']);
            $this->authorizeMailbox($request, $mailbox);
            $message->fill(['mailbox_id' => $mailbox->id, 'from_email' => $mailbox->address, 'from_name' => $mailbox->display_name]);
        }

        $html = MailHtml::sanitize($data['body_html'] ?? $message->body_html);
        $text = MailHtml::toText($html);

        $message->fill([
            'to' => $this->cleanList($data['to'] ?? $message->to ?? []),
            'cc' => $this->cleanList($data['cc'] ?? $message->cc ?? []),
            'bcc' => $this->cleanList($data['bcc'] ?? $message->bcc ?? []),
            'subject' => (string) ($data['subject'] ?? $message->subject),
            'body_html' => $html,
            'body_text' => $text,
            'snippet' => MailHtml::snippet($text),
        ])->save();

        return response()->json(['saved_at' => now()->toIso8601String()]);
    }

    public function send(Request $request, Message $message, Outbox $outbox): RedirectResponse
    {
        $this->authorizeDraft($request, $message);
        $data = $request->validate(['scheduled_at' => ['nullable', 'date']]);

        $recipients = $message->recipients();

        if ($recipients === []) {
            throw ValidationException::withMessages(['to' => 'Isi minimal satu penerima.']);
        }

        $invalid = collect($recipients)->reject(fn ($r) => filter_var($r['email'], FILTER_VALIDATE_EMAIL))->pluck('email');

        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages(['to' => 'Alamat tidak valid: '.$invalid->implode(', ')]);
        }

        $bytes = $message->attachments()->sum('size');

        if ($bytes > config('kdimail.max_attachments_mb') * 1024 * 1024) {
            throw ValidationException::withMessages(['attachments' => 'Total lampiran melebihi '.config('kdimail.max_attachments_mb').' MB.']);
        }

        $at = isset($data['scheduled_at']) ? CarbonImmutable::parse($data['scheduled_at']) : null;

        $message->update([
            'subject' => $message->subject ?: '(tanpa subjek)',
            'size' => strlen((string) $message->body_html) + $bytes,
            'has_attachments' => $bytes > 0,
        ]);

        $outbox->submit($message, $at);

        $this->toast($message->status === MessageStatus::Scheduled
            ? 'Email dijadwalkan '.$message->scheduled_at?->translatedFormat('j M Y, H:i').'.'
            : 'Email masuk antrean kirim ('.count($recipients).' penerima).');

        return back();
    }

    public function destroy(Request $request, Message $message): JsonResponse|RedirectResponse
    {
        $this->authorizeDraft($request, $message);

        $thread = $message->thread;
        $message->attachments->each->delete();
        $message->delete();

        // Utas yang hanya berisi draf ini ikut dibuang supaya tidak tersisa utas kosong.
        if ($thread && ! $thread->messages()->exists()) {
            $thread->delete();
        } else {
            $thread?->refreshSummary();
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['deleted' => true]);
        }

        $this->toast('Draf dibuang.');

        return back();
    }

    /** Email terjadwal / menunggu kuota / gagal → kembali jadi draf supaya bisa diubah. */
    public function unschedule(Request $request, Message $message): RedirectResponse
    {
        $this->authorizeMailbox($request, $message->mailbox);
        abort_unless(in_array($message->status, [...MessageStatus::pending(), MessageStatus::Failed], true), 422);

        $message->deliveries()->delete();
        $message->update(['status' => MessageStatus::Draft, 'scheduled_at' => null, 'error' => null]);
        $this->toast('Pengiriman dibatalkan. Email kembali ke Draf.');

        return back();
    }

    /** Terlalu banyak penerima untuk email biasa → jadikan siaran personal. */
    public function toBroadcast(Request $request, Message $message): RedirectResponse
    {
        $this->authorizeDraft($request, $message);

        $emails = array_column($message->recipients(), 'email');
        $contactIds = Contact::query()->whereIn('email', $emails)->pluck('id')->all();

        $broadcast = Broadcast::create([
            'mailbox_id' => $message->mailbox_id,
            'user_id' => $request->user()->id,
            'subject' => $message->subject,
            'body_html' => $message->body_html ?? '',
            'audience' => ['groups' => [], 'contacts' => $contactIds],
            'status' => BroadcastStatus::Draft,
        ]);

        $message->attachments()->update(['message_id' => null, 'broadcast_id' => $broadcast->id]);
        $message->delete();

        $unknown = count($emails) - count($contactIds);
        $this->toast($unknown > 0
            ? "Siaran dibuat. $unknown alamat belum ada di Kontak sehingga tidak ikut — tambahkan dulu bila perlu."
            : 'Siaran dibuat dari draf. Periksa lalu kirim.', $unknown > 0 ? 'warning' : 'success');

        return to_route('broadcasts.edit', $broadcast);
    }

    private function prefill(Message $draft, Message $source, string $mode, Mailbox $mailbox, string $signature): void
    {
        $subject = preg_replace('/^((re|fwd?|bls|tr)\s*:\s*)+/i', '', $source->subject) ?? $source->subject;
        $own = [$mailbox->address, $mailbox->inbound_address];
        $isOwn = fn (string $email) => in_array(explode('+', Str::before($email, '@'))[0].'@'.Str::after($email, '@'), $own, true)
            || str_starts_with($email, $mailbox->local_part.'+');
        $date = ($source->received_at ?? $source->sent_at)?->translatedFormat('l, j F Y H:i');
        $who = e($source->from_name ? "{$source->from_name} <{$source->from_email}>" : $source->from_email);

        if ($mode === 'forward') {
            $draft->subject = 'Fwd: '.$subject;
            $draft->body_html = '<p></p>'.$signature.'<p>---------- Pesan diteruskan ----------<br>Dari: '.$who.'<br>Tanggal: '.e((string) $date).'<br>Subjek: '.e($source->subject).'</p>'.$source->body_html;

            return;
        }

        $draft->thread_id = $source->thread_id;
        $draft->subject = 'Re: '.$subject;
        $draft->in_reply_to = $source->message_id_header;
        $draft->references = trim(($source->references ? $source->references.' ' : '').($source->message_id_header ? '<'.$source->message_id_header.'>' : ''));

        $replyTarget = $source->isOutgoing()
            ? ($source->to ?? [])
            : [['email' => $source->reply_to ?: $source->from_email, 'name' => $source->from_name]];
        $draft->to = $this->cleanList($replyTarget);

        if ($mode === 'reply_all') {
            $others = collect([...($source->to ?? []), ...($source->cc ?? [])])
                ->reject(fn ($r) => $isOwn(mb_strtolower($r['email'])))
                ->reject(fn ($r) => collect($draft->to)->contains('email', mb_strtolower($r['email'])))
                ->values()->all();
            $draft->cc = $this->cleanList($others);
        }

        $draft->body_html = '<p></p>'.$signature.'<p>Pada '.e((string) $date).', '.$who.' menulis:</p><blockquote>'.$source->body_html.'</blockquote>';
    }

    private function copyAttachments(Message $source, Message $draft): void
    {
        foreach ($source->attachments->filter->isAvailable() as $a) {
            $path = 'attachments/'.now()->format('Y/m').'/'.Str::uuid();
            Storage::disk(Attachment::DISK)->copy((string) $a->path, $path);
            $draft->attachments()->create([...$a->only('filename', 'mime', 'size', 'content_id'), 'path' => $path, 'user_id' => $draft->user_id]);
        }
    }

    /**
     * @param  array<int, mixed>  $list
     * @return list<array{email: string, name: string|null}>
     */
    private function cleanList(array $list): array
    {
        return collect($list)
            ->map(fn ($r) => is_array($r) ? ['email' => mb_strtolower(trim((string) ($r['email'] ?? ''))), 'name' => ($r['name'] ?? null) ?: null] : ['email' => mb_strtolower(trim((string) $r)), 'name' => null])
            ->filter(fn ($r) => $r['email'] !== '')
            ->unique('email')
            ->values()
            ->all();
    }

    private function authorizeDraft(Request $request, Message $message): void
    {
        $this->authorizeMailbox($request, $message->mailbox);
        abort_unless($message->status === MessageStatus::Draft, 422, 'Email ini sudah tidak bisa diubah.');
    }
}
