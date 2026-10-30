<?php

namespace App\Support;

/** Utilitas alamat email. */
class Address
{
    /**
     * "Nama <a@b.c>" atau "a@b.c" → ['email' => 'a@b.c', 'name' => 'Nama'].
     *
     * @return array{email: string, name: string|null}|null
     */
    public static function parse(string $raw): ?array
    {
        $raw = trim($raw);

        if (preg_match('/^(.*)<([^>]+)>$/u', $raw, $m)) {
            $email = mb_strtolower(trim($m[2]));
            $name = trim(trim($m[1]), "\"' ") ?: null;
        } else {
            $email = mb_strtolower($raw);
            $name = null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? ['email' => $email, 'name' => $name] : null;
    }

    /**
     * Pecah bagian lokal plus-addressing: kominfo+t8x2@in.d → ['kominfo', 't8x2', 'in.d'].
     *
     * @return array{0: string, 1: string|null, 2: string}
     */
    public static function split(string $email): array
    {
        [$local, $domain] = array_pad(explode('@', mb_strtolower($email), 2), 2, '');
        [$base, $tag] = array_pad(explode('+', $local, 2), 2, null);

        return [$base, $tag, $domain];
    }
}
