<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\ContactGroup;
use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal: satu admin, domain uji ilmifaizan.cloud (sudah terautentikasi
     * di Brevo) dengan subdomain penerima in.ilmifaizan.cloud, dan mailbox Kominfo.
     */
    public function run(): void
    {
        $password = config('kdimail.seed_admin_password') ?: Str::password(14, symbols: false);

        $admin = User::query()->firstOrCreate(['email' => config('kdimail.seed_admin_email')], [
            'name' => 'Administrator',
            'password' => $password,
            'role' => Role::Admin,
            'email_verified_at' => now(),
        ]);

        if ($admin->wasRecentlyCreated) {
            $this->command?->warn("Admin: {$admin->email} / $password  — segera ganti di Pengaturan › Keamanan.");
        }

        $domain = Domain::query()->firstOrCreate(['name' => 'ilmifaizan.cloud'], ['inbound_domain' => 'in.ilmifaizan.cloud']);

        $mailbox = Mailbox::query()->firstOrCreate(['domain_id' => $domain->id, 'local_part' => 'kominfo'], [
            'display_name' => 'Dinas Kominfo Kota Kendari',
            'signature_html' => '<p>Salam,<br><strong>Dinas Komunikasi dan Informatika</strong><br>Pemerintah Kota Kendari</p>',
        ]);
        $mailbox->users()->syncWithoutDetaching([$admin->id]);

        foreach (['Kepala OPD' => 'blue', 'Camat' => 'emerald', 'Lurah' => 'amber', 'Admin Website OPD' => 'violet'] as $name => $color) {
            ContactGroup::query()->firstOrCreate(['name' => $name], ['color' => $color]);
        }
    }
}
