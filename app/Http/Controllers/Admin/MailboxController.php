<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\DnsChecker;
use App\Support\MailHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Domain & Mailbox dalam satu halaman — padanan "Mail Setup" di mailcow. */
class MailboxController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Mailboxes', [
            'domains' => Domain::query()->withCount('mailboxes')->orderBy('name')->get()->map(fn (Domain $d) => [
                ...$d->only('id', 'name', 'inbound_domain', 'is_active', 'dns_status', 'mailboxes_count'),
                'dns_checked_at' => $d->dns_checked_at?->toIso8601String(),
            ]),
            'mailboxes' => Mailbox::query()->with(['domain', 'users:id,name'])->withCount('threads')->orderBy('display_name')->get()->map(fn (Mailbox $m) => [
                ...$m->only('id', 'domain_id', 'local_part', 'display_name', 'signature_html', 'is_active', 'threads_count'),
                'address' => $m->address,
                'inbound_address' => $m->inbound_address,
                'users' => $m->users->map->only('id', 'name'),
                'user_ids' => $m->users->pluck('id'),
            ]),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'inboundMx' => config('kdimail.brevo.inbound_mx'),
        ]);
    }

    public function storeDomain(Request $request, DnsChecker $dns): RedirectResponse
    {
        $domain = Domain::create($this->validatedDomain($request));
        $dns->check($domain);
        $this->toast("Domain {$domain->name} ditambahkan.");

        return back();
    }

    public function updateDomain(Request $request, Domain $domain): RedirectResponse
    {
        $domain->update($this->validatedDomain($request, $domain));
        $this->toast('Domain diperbarui.');

        return back();
    }

    public function destroyDomain(Domain $domain): RedirectResponse
    {
        abort_if($domain->mailboxes()->exists(), 422, 'Hapus atau pindahkan mailbox di domain ini dulu.');
        $domain->delete();
        $this->toast('Domain dihapus.');

        return back();
    }

    public function checkDomain(Domain $domain, DnsChecker $dns): RedirectResponse
    {
        $dns->check($domain);
        $this->toast('DNS diperiksa ulang.', 'info');

        return back();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedMailbox($request);
        $mailbox = Mailbox::create($data);
        $mailbox->users()->sync($data['user_ids'] ?? []);
        $this->toast("Mailbox {$mailbox->load('domain')->address} dibuat.");

        return back();
    }

    public function update(Request $request, Mailbox $mailbox): RedirectResponse
    {
        $data = $this->validatedMailbox($request, $mailbox);
        $mailbox->update($data);
        $mailbox->users()->sync($data['user_ids'] ?? []);
        $this->toast('Mailbox diperbarui.');

        return back();
    }

    public function destroy(Mailbox $mailbox): RedirectResponse
    {
        abort_if($mailbox->messages()->exists(), 422, 'Mailbox masih berisi email. Nonaktifkan saja.');
        $mailbox->delete();
        $this->toast('Mailbox dihapus.');

        return back();
    }

    /** @return array<string, mixed> */
    private function validatedDomain(Request $request, ?Domain $domain = null): array
    {
        $request->merge([
            'name' => mb_strtolower(trim((string) $request->input('name'))),
            'inbound_domain' => mb_strtolower(trim((string) $request->input('inbound_domain'))),
        ]);
        $host = 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/';

        return $request->validate([
            'name' => ['required', 'string', 'max:190', $host, Rule::unique('domains')->ignore($domain)],
            'inbound_domain' => ['required', 'string', 'max:190', $host, 'different:name', Rule::unique('domains')->ignore($domain)],
            'is_active' => ['boolean'],
        ], ['inbound_domain.different' => 'Brevo mewajibkan subdomain penerima berbeda dari domain pengirim.'], ['name' => 'domain pengirim', 'inbound_domain' => 'subdomain penerima']);
    }

    /** @return array<string, mixed> */
    private function validatedMailbox(Request $request, ?Mailbox $mailbox = null): array
    {
        $request->merge(['local_part' => mb_strtolower(trim((string) $request->input('local_part')))]);

        $data = $request->validate([
            'domain_id' => ['required', 'integer', 'exists:domains,id'],
            'local_part' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]+$/',
                Rule::unique('mailboxes')->where('domain_id', $request->integer('domain_id'))->ignore($mailbox)],
            'display_name' => ['required', 'string', 'max:150'],
            'signature_html' => ['nullable', 'string', 'max:20000'],
            'is_active' => ['boolean'],
            'user_ids' => ['array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ], ['local_part.regex' => 'Hanya huruf kecil, angka, titik, minus, dan garis bawah. Tanda + dipakai sistem untuk pelacak balasan.'], ['local_part' => 'nama alamat', 'display_name' => 'nama tampilan']);

        $data['signature_html'] = MailHtml::sanitize($data['signature_html'] ?? '') ?: null;

        return $data;
    }
}
