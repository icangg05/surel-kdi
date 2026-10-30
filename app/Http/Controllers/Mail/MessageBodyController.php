<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Isi email dirender di iframe sandbox (tanpa skrip) dari URL ini, dengan
 * CSP sendiri: gambar remote diblok kecuali ?images=1 — ini juga mematikan
 * piksel pelacak di CSS, bukan hanya di <img>.
 */
class MessageBodyController extends Controller
{
    public function __invoke(Request $request, Message $message): Response
    {
        $this->authorizeMailbox($request, $message->mailbox);

        $html = (string) $message->body_html;
        $inline = $message->attachments->filter(fn (Attachment $a) => $a->content_id && $a->isAvailable());

        foreach ($inline as $a) {
            $html = str_ireplace('cid:'.$a->content_id, route('attachments.inline', $a), $html);
        }

        $remote = $request->boolean('images') ? 'https: http:' : '';
        $csp = "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data: $remote; font-src data:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'";

        $doc = <<<HTML
        <!DOCTYPE html>
        <html lang="id"><head><meta charset="utf-8"><base target="_blank">
        <meta name="color-scheme" content="light">
        <style>
          html,body{margin:0;padding:0;background:#fff;color:#1f2328}
          body{font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;font-size:15px;line-height:1.65;word-wrap:break-word;overflow-wrap:anywhere;padding:2px}
          img{max-width:100%;height:auto}
          table{max-width:100%}
          a{color:#1d4ed8}
          blockquote{margin:12px 0 0 2px;padding-left:12px;border-left:3px solid #d0d7de;color:#57606a}
          pre{white-space:pre-wrap}
        </style></head><body>$html</body></html>
        HTML;

        return response($doc, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
