<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Users', [
            'users' => User::query()->with('mailboxes:id')->orderBy('name')->get()->map(fn (User $u) => [
                ...$u->only('id', 'name', 'email', 'is_active'),
                'role' => $u->role->value,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
                'two_factor' => $u->two_factor_confirmed_at !== null,
                'mailbox_ids' => $u->mailboxes->pluck('id'),
            ]),
            'mailboxes' => Mailbox::query()->with('domain')->orderBy('display_name')->get()->map(fn (Mailbox $m) => ['id' => $m->id, 'name' => $m->display_name, 'address' => $m->address]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = User::create([...$data, 'email' => mb_strtolower($data['email'])]);
        $user->mailboxes()->sync($data['mailbox_ids'] ?? []);
        $this->toast("Pengguna {$user->name} dibuat.");

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && (! $data['is_active'] || $data['role'] !== Role::Admin->value)) {
            return back()->withErrors(['role' => 'Anda tidak bisa menonaktifkan atau menurunkan peran akun sendiri.']);
        }

        $user->update(array_filter([...$data, 'email' => mb_strtolower($data['email'])], fn ($v, $k) => $k !== 'password' || filled($v), ARRAY_FILTER_USE_BOTH));
        $user->mailboxes()->sync($data['mailbox_ids'] ?? []);
        $this->toast('Pengguna diperbarui.');

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'Tidak bisa menghapus akun sendiri.');
        $user->delete();
        $this->toast('Pengguna dihapus.');

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::defaults(), 'max:100'],
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['boolean'],
            'mailbox_ids' => ['array'],
            'mailbox_ids.*' => ['integer', 'exists:mailboxes,id'],
        ], [], ['name' => 'nama', 'password' => 'kata sandi', 'role' => 'peran']);
    }
}
