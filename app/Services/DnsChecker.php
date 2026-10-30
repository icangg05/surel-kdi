<?php

namespace App\Services;

use App\Models\Domain;

/**
 * Cek DNS yang dibutuhkan Brevo: SPF/DKIM/DMARC di domain pengirim,
 * MX ke server inbound Brevo di subdomain penerima.
 */
class DnsChecker
{
    /** @return array<string, mixed> */
    public function check(Domain $domain): array
    {
        $name = $domain->name;
        $txt = $this->txt($name);
        $spf = collect($txt)->first(fn ($t) => str_starts_with($t, 'v=spf1'));
        $dmarc = collect($this->txt('_dmarc.'.$name))->first(fn ($t) => str_starts_with(strtoupper($t), 'V=DMARC1'));
        $dkim = $this->cname('brevo1._domainkey.'.$name)
            ?? $this->cname('brevo2._domainkey.'.$name)
            ?? collect($this->txt('mail._domainkey.'.$name))->first();
        $mx = $this->mx($domain->inbound_domain);
        $expectedMx = config('kdimail.brevo.inbound_mx');

        $status = [
            'spf' => $spf !== null && (str_contains($spf, 'spf.brevo.com') || str_contains($spf, 'spf.sendinblue.com')),
            'dkim' => $dkim !== null,
            'dmarc' => $dmarc !== null,
            'brevo_code' => collect($txt)->contains(fn ($t) => str_starts_with($t, 'brevo-code:')),
            'mx' => array_intersect($expectedMx, $mx) !== [],
            'records' => [
                'spf' => $spf,
                'dkim' => $dkim,
                'dmarc' => $dmarc,
                'mx' => $mx,
            ],
        ];

        $domain->forceFill(['dns_status' => $status, 'dns_checked_at' => now()])->save();

        return $status;
    }

    /** @return list<string> */
    private function txt(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT) ?: [];

        return array_values(array_map(fn ($r) => (string) ($r['txt'] ?? implode('', $r['entries'] ?? [])), $records));
    }

    private function cname(string $host): ?string
    {
        $records = @dns_get_record($host, DNS_CNAME) ?: [];

        return $records[0]['target'] ?? null;
    }

    /** @return list<string> */
    private function mx(string $host): array
    {
        $records = @dns_get_record($host, DNS_MX) ?: [];
        usort($records, fn ($a, $b) => ($a['pri'] ?? 0) <=> ($b['pri'] ?? 0));

        return array_values(array_map(fn ($r) => rtrim(strtolower((string) $r['target']), '.'), $records));
    }
}
