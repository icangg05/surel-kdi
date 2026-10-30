<?php

namespace App\Http\Controllers;

use App\Models\Mailbox;
use Illuminate\Http\Request;
use Inertia\Inertia;

abstract class Controller
{
    /** Operator hanya boleh menyentuh mailbox yang ditugaskan kepadanya. */
    protected function authorizeMailbox(Request $request, ?Mailbox $mailbox): void
    {
        abort_unless($mailbox && $request->user()->canAccessMailbox($mailbox), 403);
    }

    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
