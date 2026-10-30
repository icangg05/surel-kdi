<?php

namespace App\Support;

use App\Models\Contact;

/**
 * Isian personal siaran: {{nama}}, {{jabatan}}, {{instansi}}, {{email}}.
 * Sengaja hanya str_replace atas nilai yang sudah di-escape — konten tulisan
 * pengguna tidak pernah dikompilasi sebagai Blade.
 */
class MergeFields
{
    /** @return array<string, string> kunci => keterangan untuk UI */
    public static function available(): array
    {
        return [
            'nama' => 'Nama penerima',
            'jabatan' => 'Jabatan',
            'instansi' => 'Instansi / OPD',
            'email' => 'Alamat email',
        ];
    }

    /** @return array<string, string> */
    public static function valuesFor(?Contact $contact, string $email, ?string $name = null): array
    {
        return [
            'nama' => $contact->name ?? $name ?? $email,
            'jabatan' => $contact->position ?? '',
            'instansi' => $contact->organization ?? '',
            'email' => $email,
        ];
    }

    /** @param array<string, string> $values */
    public static function render(string $template, array $values, bool $html = true): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function (array $m) use ($values, $html) {
            $key = strtolower($m[1]);

            if (! array_key_exists($key, $values)) {
                return $m[0];
            }

            return $html ? e($values[$key]) : $values[$key];
        }, $template) ?? $template;
    }
}
