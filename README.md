# Surel Kendari

Webmail Dinas Kominfo Kota Kendari berbasis **Brevo** untuk ±700 penerima: kotak masuk berutas, siaran massal, buku alamat, dan panel admin.

Stack: Laravel 13 · PHP 8.5.12 · Inertia v3 + Vue 3 · Tailwind 4 · MySQL 8.4 · Docker (FrankenPHP).

## Menjalankan

```bash
docker compose up -d app queue scheduler mysql   # http://localhost:8010
docker compose exec app php artisan migrate --seed
docker compose exec app npm run build
```

Seeder membuat admin `admin@kdimail.test` (sandi dicetak di terminal, atau atur `SEED_ADMIN_PASSWORD`). Servis `queue` dan `scheduler` wajib jalan agar email terkirim.

## Brevo

1. Izinkan IP server di Brevo › Security › Authorized IPs (`curl -4 ifconfig.me`).
2. Isi `BREVO_API_KEY` di `.env`.
3. Arahkan MX domain inbound ke `inbound1.sendinblue.com` / `inbound2.sendinblue.com`.
4. Jalankan tunnel (`docker compose --profile tunnel up -d tunnel`) dan salin URL-nya ke `APP_URL`.
5. Daftarkan webhook: `php artisan brevo:register-webhooks`.

Status koneksi bisa dicek di **Admin › Integrasi Brevo**.

## Uji

```bash
docker compose exec app php artisan test
docker compose exec app php artisan brevo:simulate-inbound
```
