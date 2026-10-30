<?php

namespace Tests\Feature\Mail;

use App\Models\Thread;
use App\Models\User;

class AccessTest extends MailTestCase
{
    public function test_operator_cannot_open_unassigned_mailbox(): void
    {
        $operator = User::factory()->create();
        $thread = Thread::create(['mailbox_id' => $this->mailbox->id, 'subject' => 'Rahasia']);

        $this->actingAs($operator)->get(route('mail.index', [$this->mailbox, 'inbox']))->assertForbidden();
        $this->actingAs($operator)->patch(route('threads.update', $thread), ['starred' => true])->assertForbidden();
        $this->actingAs($operator)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_assigned_operator_can_read_mailbox(): void
    {
        $operator = User::factory()->create();
        $this->mailbox->users()->attach($operator);

        $this->actingAs($operator)->get(route('mail.index', [$this->mailbox, 'inbox']))->assertOk();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
