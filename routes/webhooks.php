<?php

use App\Http\Controllers\Webhooks\BrevoWebhookController;
use App\Http\Middleware\VerifyBrevoWebhook;
use Illuminate\Support\Facades\Route;

// Tanpa sesi/CSRF (grup `api`), dilindungi rahasia di URL.
Route::middleware([VerifyBrevoWebhook::class, 'throttle:600,1'])->prefix('webhooks/brevo')->group(function () {
    Route::post('inbound/{secret}', [BrevoWebhookController::class, 'inbound'])->name('webhooks.brevo.inbound');
    Route::post('events/{secret}', [BrevoWebhookController::class, 'events'])->name('webhooks.brevo.events');
});
