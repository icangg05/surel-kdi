<?php

use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Satu channel per mailbox: semua yang boleh membuka mailbox ikut mendengar
// email masuk dan perubahan status kirimnya.
Broadcast::channel('mailbox.{mailbox}', fn (User $user, Mailbox $mailbox) => $user->is_active && $mailbox->is_active && $user->canAccessMailbox($mailbox));
