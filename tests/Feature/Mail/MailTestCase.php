<?php

namespace Tests\Feature\Mail;

use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class MailTestCase extends TestCase
{
    use RefreshDatabase;

    protected Mailbox $mailbox;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        config(['kdimail.brevo.webhook_secret' => 'rahasia-uji', 'kdimail.brevo.api_key' => 'xkeysib-test']);

        $domain = Domain::create(['name' => 'ilmifaizan.cloud', 'inbound_domain' => 'in.ilmifaizan.cloud']);
        $this->mailbox = Mailbox::create(['domain_id' => $domain->id, 'local_part' => 'kominfo', 'display_name' => 'Dinas Kominfo']);
        $this->admin = User::factory()->admin()->create();
    }

    /** @return array<string, mixed> */
    protected function fixture(string $name): array
    {
        return json_decode((string) file_get_contents(base_path("tests/Fixtures/brevo/$name.json")), true);
    }
}
