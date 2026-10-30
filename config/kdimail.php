<?php

return [

    /*
    | Brevo. SMTP (kirim) memakai blok MAIL_* biasa di .env; API v3 di bawah ini
    | dipakai untuk email masuk, unduh lampiran, webhook, dan info akun.
    */
    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'base_url' => env('BREVO_API_URL', 'https://api.brevo.com/v3'),
        'webhook_secret' => env('BREVO_WEBHOOK_SECRET'),
        // MX yang harus dipasang di subdomain inbound (docs Brevo "Parse inbound email").
        'inbound_mx' => ['inbound1.sendinblue.com', 'inbound2.sendinblue.com'],
        // Event transaksional yang didaftarkan oleh `brevo:register-webhooks`.
        'events' => ['sent', 'delivered', 'opened', 'click', 'softBounce', 'hardBounce', 'invalid', 'deferred', 'blocked', 'spam', 'error', 'unsubscribed'],
    ],

    /*
    | Nilai bawaan pengaturan yang bisa diubah admin dari halaman Kuota.
    | Paket gratis Brevo = 300 email/hari.
    */
    'defaults' => [
        'daily_limit' => (int) env('BREVO_DAILY_LIMIT', 300),
        'per_minute' => 30,
        'spam_threshold' => 5.0,
        'quota_timezone' => 'UTC',
        'reply_tracking' => true,
    ],

    // Batas total lampiran satu email (MB). Brevo menolak email yang terlalu besar.
    'max_attachments_mb' => 10,

    // Lebih dari ini penerima pada email biasa → disarankan memakai siaran personal.
    'broadcast_hint_threshold' => 10,

    // Akun admin pertama dari db:seed.
    'seed_admin_email' => env('SEED_ADMIN_EMAIL', 'admin@kdimail.test'),
    'seed_admin_password' => env('SEED_ADMIN_PASSWORD'),

    // Utas di Sampah dihapus permanen setelah sekian hari.
    'trash_retention_days' => 30,
];
