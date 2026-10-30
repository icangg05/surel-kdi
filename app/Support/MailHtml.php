<?php

namespace App\Support;

use Illuminate\Support\Str;
use Soundasleep\Html2Text;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Semua HTML email (masuk dari luar maupun tulisan operator) melewati sini
 * sebelum disimpan. Skrip, form, iframe, dan event handler dibuang; atribut
 * `style` dipertahankan karena tata letak email bergantung padanya — CSS
 * remote tetap tidak bisa memuat apa pun karena halaman isi email dilayani
 * dengan CSP ketat (lihat MessageBodyController).
 */
class MailHtml
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function sanitize(?string $html): string
    {
        if (! filled($html)) {
            return '';
        }

        self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowAttribute('style', '*')
                ->allowAttribute('align', '*')
                ->allowAttribute('bgcolor', '*')
                ->allowAttribute('valign', '*')
                ->allowAttribute('width', '*')
                ->allowAttribute('height', '*')
                ->allowAttribute('cellpadding', 'table')
                ->allowAttribute('cellspacing', 'table')
                ->allowAttribute('border', '*')
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowMediaSchemes(['http', 'https', 'cid', 'data'])
                ->allowRelativeLinks(false)
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->forceAttribute('a', 'target', '_blank')
                ->withMaxInputLength(5_000_000)
        );

        return trim(self::$sanitizer->sanitize($html));
    }

    public static function toText(?string $html): string
    {
        if (! filled($html)) {
            return '';
        }

        return trim(Html2Text::convert($html, ['ignore_errors' => true]));
    }

    /** Teks polos → HTML aman (untuk email masuk yang hanya punya bagian teks). */
    public static function fromText(?string $text): string
    {
        if (! filled($text)) {
            return '';
        }

        $escaped = e($text);
        $linked = preg_replace('~(https?://[^\s<]+)~u', '<a href="$1" target="_blank" rel="noopener noreferrer nofollow">$1</a>', $escaped);

        return '<div style="white-space: pre-wrap">'.$linked.'</div>';
    }

    public static function snippet(?string $text, int $length = 200): string
    {
        $flat = preg_replace('/\s+/u', ' ', strip_tags((string) $text)) ?? '';

        return Str::limit(trim($flat), $length, '…');
    }
}
