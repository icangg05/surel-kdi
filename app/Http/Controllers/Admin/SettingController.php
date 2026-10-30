<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuotaDay;
use App\Services\QuotaManager;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(QuotaManager $quota): Response
    {
        return Inertia::render('admin/Settings', [
            'settings' => Settings::all(),
            'quota' => [
                'used' => $quota->used(),
                'remaining' => $quota->remaining(),
                'resets_at' => $quota->resetsAt()->toIso8601String(),
                'history' => $quota->history(30),
            ],
            'timezones' => ['UTC', 'Asia/Makassar', 'Asia/Jakarta', 'Europe/Paris'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'daily_limit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'per_minute' => ['required', 'integer', 'min:1', 'max:1000'],
            'spam_threshold' => ['required', 'numeric', 'min:0', 'max:100'],
            'quota_timezone' => ['required', 'timezone'],
            'reply_tracking' => ['boolean'],
        ], [], ['daily_limit' => 'limit harian', 'per_minute' => 'laju per menit', 'spam_threshold' => 'ambang spam']);

        foreach ($data as $key => $value) {
            Settings::set($key, $value);
        }

        activity('audit')->causedBy($request->user())->withProperties($data)->log('Pengaturan kuota diubah');
        $this->toast('Pengaturan disimpan.');

        return back();
    }

    /** Tandai kuota hari ini tersedia lagi (mis. setelah upgrade paket Brevo). */
    public function resetToday(Request $request): RedirectResponse
    {
        QuotaDay::query()->whereKey(app(QuotaManager::class)->today())->update(['exhausted' => false]);
        activity('audit')->causedBy($request->user())->log('Status kuota habis dicabut');
        $this->toast('Status "kuota habis" hari ini dicabut.');

        return back();
    }
}
