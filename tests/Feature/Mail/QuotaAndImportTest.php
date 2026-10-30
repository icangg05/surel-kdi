<?php

namespace Tests\Feature\Mail;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Services\QuotaManager;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;

class QuotaAndImportTest extends MailTestCase
{
    public function test_quota_reserve_release_and_forecast(): void
    {
        Settings::set('daily_limit', 300);
        $quota = app(QuotaManager::class);

        $this->assertTrue($quota->reserve(250));
        $this->assertFalse($quota->reserve(60));
        $quota->release(10);
        $this->assertSame(60, $quota->remaining());

        $plan = $quota->forecast(700);
        $this->assertSame([60, 300, 300, 40], array_column($plan, 'count'));
    }

    public function test_csv_import_with_semicolons_headers_and_groups(): void
    {
        Contact::create(['name' => 'Lama', 'email' => 'lama@example.com']);
        $csv = "Nama;Email;Jabatan;OPD;Grup\nAndi;ANDI@example.com;Camat;Kec. Poasia;Camat\nLama Baru;lama@example.com;Staf;Dinas X;\nRusak;bukan-email;;;\n";
        $file = UploadedFile::fake()->createWithContent('kontak.csv', $csv);

        $preview = $this->actingAs($this->admin)->post(route('contacts.import.preview'), ['file' => $file])->assertOk()->json();
        $this->assertSame([1, 1, 1], [$preview['new'], $preview['update'], $preview['invalid']]);

        $file = UploadedFile::fake()->createWithContent('kontak.csv', $csv);
        $this->actingAs($this->admin)->post(route('contacts.import'), ['file' => $file, 'update_existing' => true])->assertRedirect();

        $andi = Contact::query()->where('email', 'andi@example.com')->sole();
        $this->assertSame('Kec. Poasia', $andi->organization);
        $this->assertSame(['Camat'], $andi->groups->pluck('name')->all());
        $this->assertSame('Lama Baru', Contact::query()->where('email', 'lama@example.com')->value('name'));
        $this->assertSame(1, ContactGroup::count());
    }
}
