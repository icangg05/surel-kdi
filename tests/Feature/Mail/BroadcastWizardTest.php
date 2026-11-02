<?php

namespace Tests\Feature\Mail;

use App\Enums\BroadcastStatus;
use App\Enums\ContactStatus;
use App\Enums\MessageStatus;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Template;
use App\Models\Thread;
use Inertia\Testing\AssertableInertia as Assert;

class BroadcastWizardTest extends MailTestCase
{
    public function test_opening_a_new_broadcast_saves_nothing_until_the_recipients_step_is_done(): void
    {
        Contact::factory()->count(2)->create();

        $this->actingAs($this->admin)->get(route('broadcasts.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('broadcasts/Form')
                ->where('broadcast.id', null)
                ->where('step', 0)
                ->has('contacts', 2));

        $this->assertSame(0, Broadcast::count());

        $contact = Contact::first();
        $this->post(route('broadcasts.store'), ['all' => false, 'groups' => [], 'contacts' => [$contact->id]])
            ->assertRedirect(route('broadcasts.edit', [Broadcast::sole(), 'step' => 1]));

        $this->assertSame(['all' => false, 'groups' => [], 'contacts' => [$contact->id]], Broadcast::sole()->audience);
    }

    public function test_all_contacts_audience_includes_everyone_and_skips_suppressed(): void
    {
        Contact::factory()->count(3)->create();
        Contact::factory()->create()->suppress(ContactStatus::Bounced, 'tidak dikenal');

        $this->actingAs($this->admin)->postJson(route('broadcasts.estimate'), ['all' => true])
            ->assertOk()->assertJson(['total' => 4, 'sendable' => 3, 'suppressed' => 1]);
    }

    public function test_template_broadcast_is_locked_to_the_template_content(): void
    {
        $template = Template::create(['name' => 'Undangan', 'subject' => 'Undangan rapat', 'body_html' => '<p>Yth. {{nama}}, mohon hadir.</p>']);

        $this->actingAs($this->admin)->post(route('broadcasts.store'), ['template_id' => $template->id, 'all' => true]);
        $broadcast = Broadcast::sole();
        $this->assertSame('Undangan rapat', $broadcast->subject);

        // Isi dari browser diabaikan selama templat terpasang.
        $this->putJson(route('broadcasts.update', $broadcast), ['template_id' => $template->id, 'subject' => 'Diubah', 'body_html' => '<p>Diubah</p>', 'all' => true])->assertOk();
        $this->assertSame('Undangan rapat', $broadcast->fresh()->subject);

        // Perubahan di menu Templat ikut ke draf saat dibuka lagi.
        $template->update(['subject' => 'Undangan rapat (revisi)']);
        $this->get(route('broadcasts.edit', $broadcast))->assertInertia(fn (Assert $page) => $page->where('broadcast.subject', 'Undangan rapat (revisi)'));

        // Lepas templat: isi boleh ditulis sendiri.
        $this->putJson(route('broadcasts.update', $broadcast), ['template_id' => null, 'subject' => 'Tulis sendiri', 'body_html' => '<p>Isi</p>', 'all' => true])->assertOk();
        $this->assertSame('Tulis sendiri', $broadcast->fresh()->subject);
    }

    public function test_sent_folder_lists_started_broadcasts_as_one_row_next_to_threads(): void
    {
        $thread = Thread::create(['mailbox_id' => $this->mailbox->id, 'subject' => 'Email biasa', 'last_message_at' => now()->subHour()]);
        Message::create([
            'mailbox_id' => $this->mailbox->id, 'thread_id' => $thread->id, 'direction' => Message::OUT, 'status' => MessageStatus::Sent,
            'from_email' => $this->mailbox->address, 'subject' => 'Email biasa', 'sent_at' => now()->subHour(),
        ]);
        $sent = Broadcast::create([
            'mailbox_id' => $this->mailbox->id, 'user_id' => $this->admin->id, 'subject' => 'Surat edaran', 'body_html' => '<p>Isi</p>',
            'status' => BroadcastStatus::Completed, 'total' => 700, 'started_at' => now(),
        ]);
        Broadcast::create([
            'mailbox_id' => $this->mailbox->id, 'user_id' => $this->admin->id, 'subject' => 'Masih draf', 'body_html' => '<p>Isi</p>',
            'status' => BroadcastStatus::Draft,
        ]);

        $this->actingAs($this->admin)->get(route('mail.index', [$this->mailbox, 'sent']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 2)
                ->where('rows.data.0.key', 'b'.$sent->id)
                ->where('rows.data.0.kind', 'broadcast')
                ->where('rows.data.0.participants', 'Siaran ke 700 penerima')
                ->where('rows.data.1.key', 't'.$thread->id));
    }
}
