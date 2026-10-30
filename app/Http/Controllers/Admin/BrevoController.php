<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\BrevoRegisterWebhooks;
use App\Console\Commands\BrevoSyncAccount;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundEmail;
use App\Models\InboundPayload;
use App\Models\Mailbox;
use App\Services\Brevo;
use App\Services\QuotaManager;
use App\Support\SmtpStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BrevoController extends Controller
{
    public function show(Request $request, Brevo $brevo): Response
    {
        $probe = SmtpStatus::probe($request->boolean('recheck'));
        $base = rtrim((string) config('app.url'), '/');
        $secret = (string) config('kdimail.brevo.webhook_secret');
        $webhooks = null;
        $error = null;

        if ($brevo->configured()) {
            try {
                $webhooks = collect(['transactional', 'inbound'])
                    ->flatMap(fn ($type) => collect($brevo->webhooks($type))->map(fn ($w) => [
                        'id' => $w['id'] ?? null,
                        'type' => $type,
                        'url' => $w['url'] ?? '',
                        'description' => $w['description'] ?? '',
                        'events' => $w['events'] ?? [],
                        'domain' => $w['domain'] ?? null,
                        'ours' => str_contains((string) ($w['url'] ?? ''), '/webhooks/brevo/'),
                    ]))->values();
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return Inertia::render('admin/Brevo', [
            'smtp' => [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => Str::mask((string) config('mail.mailers.smtp.username'), '•', 4, -12),
                'configured' => filled(config('mail.mailers.smtp.password')) && config('mail.default') === 'smtp',
                'mailer' => config('mail.default'),
                'ok' => $probe['ok'],
                'error' => $probe['message'],
                'checked_at' => $probe['checked_at'],
            ],
            'api' => [
                'configured' => $brevo->configured(),
                'account' => Cache::get(BrevoSyncAccount::CACHE_KEY),
                'error' => $error,
            ],
            'webhooks' => $webhooks,
            'expected' => collect(BrevoRegisterWebhooks::plan($base, $secret))->map(fn ($h) => [
                ...collect($h)->only('type', 'description', 'domain')->all(),
                'url' => str_replace($secret, Str::mask($secret, '•', 4), $h['url']),
                'error' => Cache::get(BrevoRegisterWebhooks::ERRORS_KEY, [])[BrevoRegisterWebhooks::key($h)] ?? null,
            ]),
            'publicUrl' => str_starts_with($base, 'https://'),
            'inbound' => InboundPayload::query()->latest()->limit(15)->get()->map(fn (InboundPayload $p) => [
                'id' => $p->id,
                'from' => data_get($p->payload, 'From.Address'),
                'subject' => data_get($p->payload, 'Subject'),
                'to' => collect((array) data_get($p->payload, 'Recipients', []))->implode(', '),
                'processed_at' => $p->processed_at?->toIso8601String(),
                'error' => $p->error,
                'created_at' => $p->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function registerWebhooks(): RedirectResponse
    {
        Artisan::call('brevo:register-webhooks');
        $output = trim(Artisan::output());
        $this->toast($output !== '' ? Str::limit(preg_replace('/\s+/', ' ', $output), 300) : 'Selesai.', str_contains($output, '✗') ? 'error' : 'success');

        return back();
    }

    public function sync(): RedirectResponse
    {
        $code = Artisan::call('brevo:sync-account');
        $this->toast($code === 0 ? 'Info akun Brevo diperbarui.' : trim(Artisan::output()), $code === 0 ? 'success' : 'error');

        return back();
    }

    /** Kirim satu email uji lewat SMTP Brevo ke alamat admin — memakai 1 kredit. */
    public function testSmtp(Request $request, QuotaManager $quota): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'mailbox_id' => ['nullable', 'integer', 'exists:mailboxes,id']]);
        $mailbox = Mailbox::query()->with('domain')->find((int) ($data['mailbox_id'] ?? 0)) ?? Mailbox::query()->with('domain')->first();

        if (! $quota->reserve(1)) {
            return back()->withErrors(['email' => 'Kuota hari ini habis.']);
        }

        try {
            Mail::html(
                '<p>Ini email uji dari <strong>Surel Kendari</strong>. Bila Anda menerimanya, SMTP Brevo sudah tersambung.</p><p>Dikirim '.now()->translatedFormat('l, j F Y H:i').' WITA.</p>',
                function ($m) use ($data, $mailbox) {
                    $m->to($data['email'])->subject('Uji koneksi Surel Kendari');

                    if ($mailbox) {
                        $m->from($mailbox->address, $mailbox->display_name)->replyTo($mailbox->inbound_address);
                    }
                },
            );
        } catch (Throwable $e) {
            $quota->release(1);

            return back()->withErrors(['email' => SmtpStatus::friendly($e->getMessage())]);
        }

        SmtpStatus::ok();

        $this->toast("Email uji dikirim ke {$data['email']}. Periksa kotak masuk (atau folder spam).");

        return back();
    }

    public function reprocess(InboundPayload $payload): RedirectResponse
    {
        $payload->update(['processed_at' => null, 'error' => null]);
        ProcessInboundEmail::dispatch($payload->id);
        $this->toast('Email masuk diproses ulang.', 'info');

        return back();
    }
}
