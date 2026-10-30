<?php

use Illuminate\Support\Facades\Schedule;

// Jantung pengiriman: email terjadwal, email yang menunggu kuota, dan siaran
// dilepas dari sini. Tanpa servis `scheduler` berjalan, semua itu berhenti.
Schedule::command('mail:pump')->everyMinute()->withoutOverlapping();

Schedule::command('brevo:sync-account')->hourly();
Schedule::command('dns:check')->dailyAt('06:00');
Schedule::command('mail:purge-trash')->dailyAt('02:00');
Schedule::command('queue:prune-failed --hours=720')->daily();
