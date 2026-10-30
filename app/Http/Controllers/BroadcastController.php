<?php

namespace App\Http\Controllers;

use App\Enums\BroadcastStatus;
use App\Enums\DeliveryStatus;
use App\Mail\OutgoingMail;
use App\Models\Attachment;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Delivery;
use App\Models\Template;
use App\Services\BroadcastPump;
use App\Services\QuotaManager;
use App\Support\MailboxContext;
use App\Support\MailHtml;
use App\Support\MailPresenter;
use App\Support\MergeFields;
use App\Support\SmtpStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    public function __construct(private BroadcastPump $pump, private QuotaManager $quota) {}

    public function index(Request $request): Response
    {
        $mailboxIds = MailboxContext::accessible($request->user())->pluck('id');

        return Inertia::render('broadcasts/Index', [
            'broadcasts' => Broadcast::query()
                ->whereIn('mailbox_id', $mailboxIds)
                ->with(['mailbox.domain', 'author:id,name'])
                ->latest()
                ->paginate(20)
                ->through(fn (Broadcast $b) => [
                    ...$b->only('id', 'subject', 'total'),
                    'status' => $b->status->value,
                    'status_label' => $b->status->label(),
                    'mailbox' => $b->mailbox->address,
                    'author' => $b->author?->name,
                    'stats' => $b->status === BroadcastStatus::Draft ? null : $b->stats(),
                    'scheduled_at' => $b->scheduled_at?->toIso8601String(),
                    'started_at' => $b->started_at?->toIso8601String(),
                    'completed_at' => $b->completed_at?->toIso8601String(),
                    'created_at' => $b->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /** "Siaran baru" langsung membuat draf supaya lampiran bisa diunggah sejak awal. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'template_id' => ['nullable', 'integer', 'exists:templates,id'],
            'groups' => ['array'], 'groups.*' => ['integer'],
        ]);

        $mailbox = MailboxContext::current($request->user());
        abort_unless($mailbox !== null, 403, 'Anda belum ditugaskan ke mailbox mana pun.');
        $template = isset($data['template_id']) ? Template::query()->find((int) $data['template_id']) : null;

        $broadcast = Broadcast::create([
            'mailbox_id' => $mailbox->id,
            'user_id' => $request->user()->id,
            'template_id' => $template?->id,
            'subject' => $template->subject ?? '',
            'body_html' => $template->body_html ?? '<p>Yth. {{nama}},</p><p></p>',
            'audience' => ['groups' => $data['groups'] ?? [], 'contacts' => []],
            'status' => BroadcastStatus::Draft,
        ]);

        return to_route('broadcasts.edit', $broadcast);
    }

    public function edit(Request $request, Broadcast $broadcast): Response|RedirectResponse
    {
        $this->authorizeMailbox($request, $broadcast->mailbox);

        if (! $broadcast->isEditable()) {
            return to_route('broadcasts.show', $broadcast);
        }

        $audience = $broadcast->audience ?? [];

        return Inertia::render('broadcasts/Form', [
            'broadcast' => [
                ...$broadcast->only('id', 'mailbox_id', 'subject', 'body_html'),
                'status' => $broadcast->status->value,
                'scheduled_at' => $broadcast->scheduled_at?->toIso8601String(),
                'groups' => $audience['groups'] ?? [],
                'contacts' => Contact::query()->whereIn('id', $audience['contacts'] ?? [])->get(['id', 'name', 'email', 'organization']),
                'attachments' => $broadcast->attachments->map(fn (Attachment $a) => MailPresenter::attachment($a)),
            ],
            'groups' => ContactGroup::query()->withCount(['contacts', 'contacts as sendable_count' => fn ($q) => $q->sendable()])->orderBy('name')->get(['id', 'name', 'color']),
            'templates' => Template::query()->orderBy('name')->get(['id', 'name', 'subject', 'body_html']),
            'mailboxes' => MailboxContext::accessible($request->user())->map(fn ($m) => ['id' => $m->id, 'name' => $m->display_name, 'address' => $m->address])->values(),
            'mergeFields' => MergeFields::available(),
        ]);
    }

    /** Simpan otomatis dari wizard (JSON), atau simpan manual (Inertia). */
    public function update(Request $request, Broadcast $broadcast): RedirectResponse|JsonResponse
    {
        $this->authorizeMailbox($request, $broadcast->mailbox);
        abort_unless($broadcast->isEditable(), 422, 'Siaran yang sudah berjalan tidak bisa diubah.');

        $broadcast->update($this->validated($request));

        if ($request->wantsJson()) {
            return response()->json(['saved_at' => now()->toIso8601String()]);
        }

        $this->toast('Draf siaran disimpan.');

        return back();
    }

    /** Hitungan penerima + perkiraan jadwal kuota, untuk langkah Jadwal di wizard. */
    public function estimate(Request $request, Broadcast $broadcast): JsonResponse
    {
        $this->authorizeMailbox($request, $broadcast->mailbox);
        $data = $request->validate(['groups' => ['array'], 'contacts' => ['array'], 'scheduled_at' => ['nullable', 'date']]);

        $audience = $this->pump->audience(['groups' => array_map('intval', $data['groups'] ?? []), 'contacts' => array_map('intval', $data['contacts'] ?? [])]);
        $sendable = $audience->filter(fn (Contact $c) => $c->status->value === 'active');
        $start = isset($data['scheduled_at']) ? CarbonImmutable::parse($data['scheduled_at']) : null;

        return response()->json([
            'total' => $audience->count(),
            'sendable' => $sendable->count(),
            'suppressed' => $audience->count() - $sendable->count(),
            'sample' => $sendable->take(5)->map->only('id', 'name', 'email', 'organization', 'position')->values(),
            'forecast' => $this->quota->forecast($sendable->count(), $start),
            'remaining_today' => $this->quota->remaining(),
            'limit' => $this->quota->limit(),
        ]);
    }

    /** Pratinjau isi untuk satu kontak contoh (merge field terisi). */
    public function preview(Request $request, Broadcast $broadcast): JsonResponse
    {
        $this->authorizeMailbox($request, $broadcast->mailbox);
        $data = $request->validate(['contact_id' => ['nullable', 'integer'], 'subject' => ['nullable', 'string'], 'body_html' => ['nullable', 'string']]);

        $contact = isset($data['contact_id']) ? Contact::query()->find((int) $data['contact_id']) : null;
        $values = MergeFields::valuesFor($contact, $contact->email ?? 'penerima@contoh.go.id', $contact ? null : 'Bapak/Ibu Penerima');

        return response()->json([
            'subject' => MergeFields::render((string) ($data['subject'] ?? $broadcast->subject), $values, html: false),
            'html' => MergeFields::render(MailHtml::sanitize($data['body_html'] ?? $broadcast->body_html), $values),
        ]);
    }

    public function test(Request $request, Broadcast $broadcast): RedirectResponse
    {
        $this->authorizeMailbox($request, $broadcast->mailbox);
        $data = $request->validate(['email' => ['required', 'email']]);

        if (! $this->quota->reserve(1)) {
            throw ValidationException::withMessages(['email' => 'Kuota hari ini habis.']);
        }

        $delivery = new Delivery([
            'email' => mb_strtolower($data['email']),
            'name' => $request->user()->name,
            'reply_token' => null,
        ]);
        $delivery->uuid = (string) Str::uuid();
        $delivery->provider_message_id = $delivery->uuid.'@'.$broadcast->mailbox->domain->name;
        $delivery->setRelation('contact', Contact::query()->where('email', $delivery->email)->first());

        $mail = OutgoingMail::forBroadcast($broadcast, $delivery);
        $mail->subjectLine = '[UJI] '.$mail->subjectLine;

        try {
            Mail::send($mail);
        } catch (\Throwable $e) {
            $this->quota->release(1);
            throw ValidationException::withMessages(['email' => 'Gagal mengirim: '.SmtpStatus::friendly($e->getMessage())]);
        }

        $this->toast("Email uji dikirim ke {$data['email']}.");

        return back();
    }

    public function launch(Request $request, Broadcast $broadcast): RedirectResponse
    {
        $this->authorizeMailbox($request, $broadcast->mailbox);
        abort_unless($broadcast->isEditable(), 422);

        $data = $request->validate(['scheduled_at' => ['nullable', 'date']]);

        if (blank($broadcast->subject) || blank(trim(strip_tags($broadcast->body_html)))) {
            throw ValidationException::withMessages(['subject' => 'Subjek dan isi siaran wajib diisi.']);
        }

        if ($this->pump->audience($broadcast->audience)->where('status.value', 'active')->isEmpty()) {
            throw ValidationException::withMessages(['audience' => 'Belum ada penerima aktif. Pilih grup atau kontak.']);
        }

        $this->pump->launch($broadcast, isset($data['scheduled_at']) ? CarbonImmutable::parse($data['scheduled_at']) : null);

        if ($broadcast->status === BroadcastStatus::Sending) {
            $this->pump->pump();
        }

        $this->toast($broadcast->status === BroadcastStatus::Scheduled
            ? 'Siaran dijadwalkan.'
            : "Siaran mulai dikirim ke {$broadcast->total} penerima.");

        return to_route('broadcasts.show', $broadcast);
    }

    public function show(Request $request, Broadcast $broadcast): Response|RedirectResponse
    {
        $this->authorizeMailbox($request, $broadcast->mailbox);

        if ($broadcast->status === BroadcastStatus::Draft) {
            return to_route('broadcasts.edit', $broadcast);
        }

        $filter = $request->query('status');
        $search = trim((string) $request->query('q', ''));
        $broadcast->load('mailbox.domain', 'author:id,name', 'attachments');

        $recipients = $broadcast->deliveries()
            ->when($filter === 'failed', fn ($q) => $q->whereIn('status', [DeliveryStatus::HardBounce, DeliveryStatus::Blocked, DeliveryStatus::Failed, DeliveryStatus::Spam]))
            ->when($filter === 'opened', fn ($q) => $q->whereIn('status', [DeliveryStatus::Opened, DeliveryStatus::Clicked]))
            ->when($filter === 'pending', fn ($q) => $q->whereIn('status', [DeliveryStatus::Pending, DeliveryStatus::Queued]))
            ->when($filter === 'replied', fn ($q) => $q->whereNotNull('replied_at'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('email', 'like', "%$search%")->orWhere('name', 'like', "%$search%")))
            ->with('contact:id,organization')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Delivery $d) => [
                ...$d->only('id', 'email', 'name', 'error', 'thread_id'),
                'organization' => $d->contact?->organization,
                'status' => $d->status->value,
                'label' => $d->status->label(),
                'sent_at' => $d->sent_at?->toIso8601String(),
                'opened_at' => $d->opened_at?->toIso8601String(),
                'replied_at' => $d->replied_at?->toIso8601String(),
            ]);

        $stats = $broadcast->stats();
        $pending = $stats['pending'];

        return Inertia::render('broadcasts/Show', [
            'broadcast' => [
                ...$broadcast->only('id', 'subject', 'body_html', 'total', 'mailbox_id'),
                'status' => $broadcast->status->value,
                'status_label' => $broadcast->status->label(),
                'mailbox' => $broadcast->mailbox->address,
                'author' => $broadcast->author?->name,
                'scheduled_at' => $broadcast->scheduled_at?->toIso8601String(),
                'started_at' => $broadcast->started_at?->toIso8601String(),
                'completed_at' => $broadcast->completed_at?->toIso8601String(),
                'attachments' => $broadcast->attachments->map(fn (Attachment $a) => MailPresenter::attachment($a)),
            ],
            'stats' => $stats,
            'forecast' => $pending > 0 && in_array($broadcast->status, [...BroadcastStatus::running(), BroadcastStatus::Paused], true) ? $this->quota->forecast($pending) : [],
            'recipients' => $recipients,
            'filters' => ['status' => $filter, 'q' => $search],
        ]);
    }

    public function pause(Request $request, Broadcast $broadcast): RedirectResponse
    {
        $this->authorizeMailbox($request, $broadcast->mailbox);
        abort_unless(in_array($broadcast->status, BroadcastStatus::running(), true), 422);
        $this->pump->pause($broadcast);
        $this->toast('Siaran dijeda. Email yang sudah di antrean tetap berangkat.', 'info');

        return back();
    }

    public function resume(Request $request, Broadcast $broadcast): RedirectResponse
    {
        $this->authorizeMailbox($request, $broadcast->mailbox);
        abort_unless($broadcast->status === BroadcastStatus::Paused, 422);
        $this->pump->resume($broadcast);
        $this->pump->pump();
        $this->toast('Siaran dilanjutkan.');

        return back();
    }

    public function cancel(Request $request, Broadcast $broadcast): RedirectResponse
    {
        $this->authorizeMailbox($request, $broadcast->mailbox);
        abort_if(in_array($broadcast->status, [BroadcastStatus::Completed, BroadcastStatus::Cancelled, BroadcastStatus::Draft], true), 422);
        $this->pump->cancel($broadcast);
        $this->toast('Siaran dibatalkan. Penerima yang belum dikirimi tidak akan menerima email.', 'warning');

        return back();
    }

    /** Siaran terjadwal → kembali jadi draf. */
    public function unschedule(Request $request, Broadcast $broadcast): RedirectResponse
    {
        $this->authorizeMailbox($request, $broadcast->mailbox);
        abort_unless($broadcast->status === BroadcastStatus::Scheduled, 422);
        $broadcast->deliveries()->delete();
        $broadcast->update(['status' => BroadcastStatus::Draft, 'scheduled_at' => null, 'total' => 0]);

        return to_route('broadcasts.edit', $broadcast);
    }

    public function destroy(Request $request, Broadcast $broadcast): RedirectResponse
    {
        $this->authorizeMailbox($request, $broadcast->mailbox);
        abort_unless(in_array($broadcast->status, [BroadcastStatus::Draft, BroadcastStatus::Completed, BroadcastStatus::Cancelled], true), 422, 'Batalkan siaran dulu.');

        $broadcast->attachments->each->delete();
        $broadcast->delete();
        $this->toast('Siaran dihapus.');

        return to_route('broadcasts.index');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'mailbox_id' => ['sometimes', 'integer', Rule::in(MailboxContext::accessible($request->user())->pluck('id'))],
            'subject' => ['nullable', 'string', 'max:250'],
            'body_html' => ['nullable', 'string', 'max:1000000'],
            'groups' => ['array'], 'groups.*' => ['integer', 'exists:contact_groups,id'],
            'contacts' => ['array'], 'contacts.*' => ['integer', 'exists:contacts,id'],
        ]);

        return array_filter([
            'mailbox_id' => $data['mailbox_id'] ?? null,
            'subject' => $data['subject'] ?? '',
            'body_html' => MailHtml::sanitize($data['body_html'] ?? ''),
            'audience' => ['groups' => array_values(array_map('intval', $data['groups'] ?? [])), 'contacts' => array_values(array_map('intval', $data['contacts'] ?? []))],
        ], fn ($v) => $v !== null);
    }
}
