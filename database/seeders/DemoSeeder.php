<?php

namespace Database\Seeders;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\InboundPayload;
use App\Models\Mailbox;
use App\Services\InboundProcessor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data contoh untuk melihat tampilan: kontak @example.com (tidak pernah
 * benar-benar terkirim) dan beberapa email masuk.
 *   php artisan db:seed --class=DemoSeeder
 * Jangan dipakai di produksi.
 */
class DemoSeeder extends Seeder
{
    public function run(InboundProcessor $processor): void
    {
        $groups = ContactGroup::query()->get();
        $contacts = Contact::factory()->count(60)->create();

        foreach ($contacts as $i => $contact) {
            $contact->groups()->attach($groups->random(rand(1, 2))->pluck('id'));

            if ($i % 17 === 0) {
                $contact->suppress(ContactStatus::Bounced, '550 5.1.1 mailbox unavailable');
            }
        }

        $mailbox = Mailbox::query()->with('domain')->firstOrFail();
        $samples = [
            ['Laporan kegiatan Oktober', "Selamat siang,\n\nBersama ini kami lampirkan laporan kegiatan bulan Oktober untuk ditindaklanjuti.\n\nTerima kasih."],
            ['Konfirmasi kehadiran rapat koordinasi', "Kami konfirmasi akan hadir pada rapat koordinasi hari Kamis pukul 09.00 WITA.\n\nHormat kami."],
            ['Permohonan akun website OPD', "Mohon dibuatkan akun admin untuk website kecamatan kami.\n\nNama operator: Andi\nNIP: 1990xxxx"],
        ];

        foreach ($samples as $i => [$subject, $body]) {
            $from = $contacts[$i * 3];
            $item = [
                'Uuid' => (string) Str::uuid(),
                'MessageId' => Str::uuid().'@example.com',
                'From' => ['Name' => $from->name, 'Address' => $from->email],
                'To' => [['Name' => $mailbox->display_name, 'Address' => $mailbox->inbound_address]],
                'Recipients' => [$mailbox->inbound_address],
                'Subject' => $subject,
                'RawTextBody' => $body,
                'RawHtmlBody' => '<p>'.nl2br(e($body)).'</p>',
                'ExtractedMarkdownMessage' => $body,
                'SpamScore' => 0.2,
                'SentAtDate' => now()->subHours(($i + 1) * 5)->toRfc2822String(),
                'Attachments' => [],
            ];
            $processor->store(['items' => [$item]]);
            $processor->process(InboundPayload::query()->where('uuid', $item['Uuid'])->firstOrFail());
        }
    }
}
