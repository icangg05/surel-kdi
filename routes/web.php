<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\BrevoController;
use App\Http\Controllers\Admin\MailboxController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Contacts\ContactExportController;
use App\Http\Controllers\Contacts\ContactGroupController;
use App\Http\Controllers\Contacts\ContactImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Mail\AttachmentController;
use App\Http\Controllers\Mail\DraftController;
use App\Http\Controllers\Mail\MessageBodyController;
use App\Http\Controllers\Mail\RecipientSearchController;
use App\Http\Controllers\Mail\ThreadController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Kotak surat
    Route::get('mail', [ThreadController::class, 'redirect'])->name('mail');
    Route::get('mail/{mailbox}/{folder}/{thread?}', [ThreadController::class, 'index'])
        ->whereIn('folder', ThreadController::FOLDERS)
        ->name('mail.index');
    Route::patch('threads/{thread}', [ThreadController::class, 'update'])->name('threads.update');
    Route::post('threads/bulk', [ThreadController::class, 'bulk'])->name('threads.bulk');
    Route::delete('threads/{thread}', [ThreadController::class, 'destroy'])->name('threads.destroy');
    Route::get('messages/{message}/body', MessageBodyController::class)->name('messages.body');
    Route::post('messages/{message}/unschedule', [DraftController::class, 'unschedule'])->name('messages.unschedule');

    // Panel tulis (JSON untuk simpan otomatis)
    Route::post('drafts', [DraftController::class, 'store'])->name('drafts.store');
    Route::get('drafts/{message}', [DraftController::class, 'show'])->name('drafts.show');
    Route::put('drafts/{message}', [DraftController::class, 'update'])->name('drafts.update');
    Route::post('drafts/{message}/send', [DraftController::class, 'send'])->name('drafts.send');
    Route::post('drafts/{message}/broadcast', [DraftController::class, 'toBroadcast'])->name('drafts.broadcast');
    Route::delete('drafts/{message}', [DraftController::class, 'destroy'])->name('drafts.destroy');

    Route::post('attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::get('attachments/{attachment}/inline', [AttachmentController::class, 'inline'])->name('attachments.inline');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('recipients/search', RecipientSearchController::class)->name('recipients.search');

    // Buku alamat
    Route::get('contacts/export', ContactExportController::class)->name('contacts.export');
    Route::post('contacts/import/preview', [ContactImportController::class, 'preview'])->name('contacts.import.preview');
    Route::post('contacts/import', [ContactImportController::class, 'store'])->name('contacts.import');
    Route::post('contacts/bulk', [ContactController::class, 'bulk'])->name('contacts.bulk');
    Route::resource('contacts', ContactController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('groups', ContactGroupController::class)->only(['store', 'update', 'destroy']);

    // Siaran & templat
    Route::resource('templates', TemplateController::class)->except(['show']);
    Route::resource('broadcasts', BroadcastController::class)->except(['create']);
    Route::prefix('broadcasts/{broadcast}')->name('broadcasts.')->controller(BroadcastController::class)->group(function () {
        Route::post('estimate', 'estimate')->name('estimate');
        Route::post('preview', 'preview')->name('preview');
        Route::post('test', 'test')->middleware('throttle:10,1')->name('test');
        Route::post('launch', 'launch')->name('launch');
        Route::post('pause', 'pause')->name('pause');
        Route::post('resume', 'resume')->name('resume');
        Route::post('cancel', 'cancel')->name('cancel');
        Route::post('unschedule', 'unschedule')->name('unschedule');
    });

    // Admin
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('mailboxes', [MailboxController::class, 'index'])->name('mailboxes.index');
        Route::post('mailboxes', [MailboxController::class, 'store'])->name('mailboxes.store');
        Route::put('mailboxes/{mailbox}', [MailboxController::class, 'update'])->name('mailboxes.update');
        Route::delete('mailboxes/{mailbox}', [MailboxController::class, 'destroy'])->name('mailboxes.destroy');
        Route::post('domains', [MailboxController::class, 'storeDomain'])->name('domains.store');
        Route::put('domains/{domain}', [MailboxController::class, 'updateDomain'])->name('domains.update');
        Route::delete('domains/{domain}', [MailboxController::class, 'destroyDomain'])->name('domains.destroy');
        Route::post('domains/{domain}/check', [MailboxController::class, 'checkDomain'])->name('domains.check');

        Route::get('brevo', [BrevoController::class, 'show'])->name('brevo');
        Route::post('brevo/webhooks', [BrevoController::class, 'registerWebhooks'])->name('brevo.webhooks');
        Route::post('brevo/sync', [BrevoController::class, 'sync'])->name('brevo.sync');
        Route::post('brevo/test-smtp', [BrevoController::class, 'testSmtp'])->middleware('throttle:5,1')->name('brevo.test');
        Route::post('brevo/inbound/{payload}/reprocess', [BrevoController::class, 'reprocess'])->name('brevo.reprocess');

        Route::get('settings', [SettingController::class, 'edit'])->name('settings');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/reset-quota', [SettingController::class, 'resetToday'])->name('settings.reset-quota');

        Route::get('activity', ActivityController::class)->name('activity');
    });
});

require __DIR__.'/settings.php';
