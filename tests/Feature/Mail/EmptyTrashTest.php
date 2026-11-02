<?php

namespace Tests\Feature\Mail;

use App\Enums\Folder;
use App\Enums\MessageStatus;
use App\Models\Attachment;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class EmptyTrashTest extends MailTestCase
{
    private function thread(Mailbox $mailbox, Folder $folder): Thread
    {
        $thread = Thread::create(['mailbox_id' => $mailbox->id, 'subject' => 'Uji', 'folder' => $folder, 'trashed_at' => $folder === Folder::Trash ? now() : null]);
        Message::create([
            'mailbox_id' => $mailbox->id, 'thread_id' => $thread->id, 'direction' => Message::IN, 'status' => MessageStatus::Received,
            'from_email' => 'budi@example.com', 'subject' => 'Uji',
        ]);

        return $thread;
    }

    public function test_empty_trash_purges_only_this_mailbox_trash_including_attachment_files(): void
    {
        Storage::fake(Attachment::DISK);
        $trashed = $this->thread($this->mailbox, Folder::Trash);
        Storage::disk(Attachment::DISK)->put('lampiran/uji.pdf', '%PDF');
        $trashed->messages()->sole()->attachments()->create(['filename' => 'uji.pdf', 'mime' => 'application/pdf', 'size' => 4, 'path' => 'lampiran/uji.pdf']);
        $inbox = $this->thread($this->mailbox, Folder::Inbox);
        $other = Mailbox::create(['domain_id' => $this->mailbox->domain_id, 'local_part' => 'lain', 'display_name' => 'Lain']);
        $otherTrash = $this->thread($other, Folder::Trash);

        $this->actingAs($this->admin)->delete(route('mail.empty-trash', $this->mailbox))
            ->assertRedirect(route('mail.index', [$this->mailbox, 'trash']));

        $this->assertModelMissing($trashed);
        $this->assertSame(0, Message::query()->where('thread_id', $trashed->id)->count());
        Storage::disk(Attachment::DISK)->assertMissing('lampiran/uji.pdf');
        $this->assertModelExists($inbox);
        $this->assertModelExists($otherTrash);
    }

    public function test_operator_cannot_empty_a_mailbox_they_are_not_assigned_to(): void
    {
        $trashed = $this->thread($this->mailbox, Folder::Trash);

        $this->actingAs(User::factory()->create())->delete(route('mail.empty-trash', $this->mailbox))->assertForbidden();

        $this->assertModelExists($trashed);
    }
}
