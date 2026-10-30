<?php

namespace App\Http\Controllers;

use App\Console\Commands\BrevoSyncAccount;
use App\Enums\BroadcastStatus;
use App\Enums\DeliveryStatus;
use App\Enums\Folder;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\Thread;
use App\Services\QuotaManager;
use App\Support\MailboxContext;
use App\Support\MailPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, QuotaManager $quota): Response
    {
        $mailboxIds = MailboxContext::accessible($request->user())->pluck('id');
        $since = now()->subDays(13)->startOfDay();

        // Aktivitas pengiriman 14 hari per tanggal (zona waktu aplikasi).
        $daily = Delivery::query()
            ->where('sent_at', '>=', $since)
            ->where(fn ($q) => $q
                ->whereIn('message_id', fn ($m) => $m->select('id')->from('messages')->whereIn('mailbox_id', $mailboxIds))
                ->orWhereIn('broadcast_id', fn ($b) => $b->select('id')->from('broadcasts')->whereIn('mailbox_id', $mailboxIds)))
            ->get(['sent_at', 'status', 'opened_at'])
            ->groupBy(fn (Delivery $d) => $d->sent_at->toDateString());

        $series = collect(range(13, 0))->map(function (int $ago) use ($daily) {
            $date = now()->subDays($ago)->toDateString();
            $rows = $daily->get($date, collect());

            return [
                'date' => $date,
                'sent' => $rows->count(),
                'opened' => $rows->whereNotNull('opened_at')->count(),
                'failed' => $rows->filter(fn (Delivery $d) => $d->status->isFailure() && $d->status !== DeliveryStatus::Spam)->count(),
            ];
        });

        $recentThreads = Thread::query()
            ->whereIn('mailbox_id', $mailboxIds)
            ->where('folder', Folder::Inbox)
            ->where('unread_count', '>', 0)
            ->with(['messages' => fn ($q) => $q->select('id', 'thread_id', 'direction', 'status', 'from_name', 'from_email', 'to', 'snippet', 'has_attachments', 'received_at', 'sent_at', 'created_at')])
            ->orderByDesc('last_message_at')
            ->limit(6)
            ->get();

        return Inertia::render('Dashboard', [
            'quota' => [
                'used' => $quota->used(),
                'limit' => $quota->limit(),
                'remaining' => $quota->remaining(),
                'resets_at' => $quota->resetsAt()->toIso8601String(),
                'history' => $quota->history(14),
            ],
            'brevo' => Cache::get(BrevoSyncAccount::CACHE_KEY),
            'series' => $series,
            'totals' => [
                'unread' => (int) Thread::query()->whereIn('mailbox_id', $mailboxIds)->where('folder', Folder::Inbox)->sum('unread_count'),
                'contacts' => Contact::query()->count(),
                'suppressed' => Contact::query()->where('status', '!=', 'active')->count(),
                'sent_7d' => $series->slice(-7)->sum('sent'),
                'opened_7d' => $series->slice(-7)->sum('opened'),
            ],
            'unread' => $recentThreads->map(fn (Thread $t) => [...MailPresenter::threadRow($t), 'mailbox_id' => $t->mailbox_id]),
            'broadcasts' => Broadcast::query()
                ->whereIn('mailbox_id', $mailboxIds)
                ->whereIn('status', [...BroadcastStatus::running(), BroadcastStatus::Scheduled, BroadcastStatus::Paused])
                ->latest()->limit(5)->get()
                ->map(fn (Broadcast $b) => [
                    ...$b->only('id', 'subject', 'total'),
                    'status' => $b->status->value,
                    'status_label' => $b->status->label(),
                    'scheduled_at' => $b->scheduled_at?->toIso8601String(),
                    'stats' => $b->stats(),
                ]),
            'driver' => DB::getDriverName(),
        ]);
    }
}
