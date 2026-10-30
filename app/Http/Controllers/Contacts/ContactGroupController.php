<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactGroupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $group = ContactGroup::create($this->validated($request));
        $this->toast("Grup {$group->name} dibuat.");

        return back();
    }

    public function update(Request $request, ContactGroup $group): RedirectResponse
    {
        $group->update($this->validated($request, $group));
        $this->toast('Grup diperbarui.');

        return back();
    }

    public function destroy(ContactGroup $group): RedirectResponse
    {
        $group->delete();
        $this->toast('Grup dihapus. Kontaknya tetap ada.');

        return to_route('contacts.index');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ContactGroup $group = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('contact_groups')->ignore($group)],
            'description' => ['nullable', 'string', 'max:250'],
            'color' => ['required', Rule::in(ContactGroup::COLORS)],
        ], [], ['name' => 'nama grup']);
    }
}
