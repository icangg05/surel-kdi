<?php

namespace App\Http\Middleware;

use App\Models\Mailbox;
use App\Services\Brevo;
use App\Services\QuotaManager;
use App\Support\MailboxContext;
use App\Support\SmtpStatus;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    ...$user->only('id', 'name', 'email', 'created_at', 'updated_at', 'email_verified_at'),
                    'role' => $user->role->value,
                    'is_admin' => $user->isAdmin(),
                    'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Dibaca ulang oleh polling sidebar (only: ['mail']), jadi dibuat lazy.
            'mail' => fn () => $user ? $this->mail($request) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function mail(Request $request): array
    {
        $user = $request->user();
        $current = MailboxContext::current($user);
        $quota = app(QuotaManager::class);

        return [
            'mailboxes' => MailboxContext::accessible($user)->map(fn (Mailbox $m) => [
                'id' => $m->id,
                'name' => $m->display_name,
                'address' => $m->address,
            ])->values(),
            'current' => $current?->id,
            'signature' => $current?->signature_html,
            'counts' => $current ? MailboxContext::counts($current) : null,
            'quota' => [
                'used' => $quota->limit() - $quota->remaining(),
                'limit' => $quota->limit(),
                'remaining' => $quota->remaining(),
                'resets_at' => $quota->resetsAt()->toIso8601String(),
            ],
            'brevo_api' => app(Brevo::class)->configured(),
            'smtp_error' => SmtpStatus::current(),
            'broadcast_hint' => (int) config('kdimail.broadcast_hint_threshold'),
            'max_attachments_mb' => (int) config('kdimail.max_attachments_mb'),
        ];
    }
}
