<?php

namespace App\Http\Controllers\Contacts;

use App\Enums\ContactStatus;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'group' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'suppressed'])],
        ]);

        $contacts = Contact::query()
            ->with('groups:id,name,color')
            ->search($filters['q'] ?? null)
            ->when($filters['group'] ?? null, fn ($q, $g) => $q->whereHas('groups', fn ($g2) => $g2->whereKey($g)))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('status', ContactStatus::Active))
            ->when(($filters['status'] ?? null) === 'suppressed', fn ($q) => $q->where('status', '!=', ContactStatus::Active))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Contact $c) => [
                ...$c->only('id', 'name', 'email', 'position', 'organization', 'phone', 'notes', 'suppressed_reason'),
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'last_contacted_at' => $c->last_contacted_at?->toIso8601String(),
                'groups' => $c->groups->map->only('id', 'name', 'color'),
            ]);

        return Inertia::render('contacts/Index', [
            'contacts' => $contacts,
            'filters' => $filters,
            'groups' => ContactGroup::query()->withCount('contacts')->orderBy('name')->get(['id', 'name', 'description', 'color']),
            'colors' => ContactGroup::COLORS,
            'totals' => [
                'all' => Contact::query()->count(),
                'suppressed' => Contact::query()->where('status', '!=', ContactStatus::Active)->count(),
            ],
            'organizations' => Contact::query()->whereNotNull('organization')->distinct()->orderBy('organization')->limit(300)->pluck('organization'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $contact = Contact::create([...$data, 'created_by' => $request->user()->id]);
        $contact->groups()->sync($data['group_ids'] ?? []);

        $this->toast("Kontak {$contact->name} ditambahkan.");

        return back();
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $data = $this->validated($request, $contact);
        $contact->update($data);
        $contact->groups()->sync($data['group_ids'] ?? []);

        $this->toast('Kontak diperbarui.');

        return back();
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();
        $this->toast('Kontak dihapus.');

        return back();
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:1000'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['add_group', 'remove_group', 'delete', 'reactivate'])],
            'group_id' => ['required_if:action,add_group,remove_group', 'nullable', 'integer', 'exists:contact_groups,id'],
        ]);

        $contacts = Contact::query()->whereIn('id', $data['ids']);
        $n = (clone $contacts)->count();

        match ($data['action']) {
            'add_group' => ContactGroup::query()->findOrFail((int) $data['group_id'])->contacts()->syncWithoutDetaching($data['ids']),
            'remove_group' => ContactGroup::query()->findOrFail((int) $data['group_id'])->contacts()->detach($data['ids']),
            'delete' => $contacts->get()->each->delete(),
            'reactivate' => $contacts->update(['status' => ContactStatus::Active, 'suppressed_reason' => null, 'suppressed_at' => null]),
        };

        $this->toast(match ($data['action']) {
            'add_group' => "$n kontak dimasukkan ke grup.",
            'remove_group' => "$n kontak dikeluarkan dari grup.",
            'delete' => "$n kontak dihapus.",
            'reactivate' => "$n kontak diaktifkan kembali.",
        });

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Contact $contact = null): array
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('contacts', 'email')->ignore($contact)],
            'position' => ['nullable', 'string', 'max:150'],
            'organization' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'group_ids' => ['array'],
            'group_ids.*' => ['integer', 'exists:contact_groups,id'],
        ], [], ['name' => 'nama', 'position' => 'jabatan', 'organization' => 'instansi', 'phone' => 'telepon']);
    }
}
