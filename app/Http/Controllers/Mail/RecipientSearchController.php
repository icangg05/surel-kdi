<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Autocomplete penerima di panel tulis: kontak + grup (grup diperluas jadi anggotanya). */
class RecipientSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $contacts = Contact::query()->search($q)->orderBy('name')->limit(8)
            ->get(['id', 'name', 'email', 'organization', 'position', 'status'])
            ->map(fn (Contact $c) => [
                'type' => 'contact',
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'detail' => collect([$c->position, $c->organization])->filter()->implode(' · '),
                'suppressed' => $c->status->value !== 'active',
            ]);

        $groups = ContactGroup::query()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.$q.'%'))
            ->withCount(['contacts' => fn ($c) => $c->sendable()])
            ->orderBy('name')->limit(5)->get()
            ->map(fn (ContactGroup $g) => [
                'type' => 'group',
                'id' => $g->id,
                'name' => $g->name,
                'count' => $g->contacts_count,
                'color' => $g->color,
                'members' => $g->contacts()->sendable()->orderBy('name')->get(['name', 'email'])->map->only('name', 'email'),
            ]);

        return response()->json(['contacts' => $contacts, 'groups' => $groups]);
    }
}
