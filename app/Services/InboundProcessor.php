<?php

namespace App\Services;

use App\Enums\Folder;
use App\Enums\MessageStatus;
use App\Jobs\DownloadInboundAttachment;
use App\Jobs\ProcessInboundEmail;
use App\Mail\OutgoingMail;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\Domain;
use App\Models\InboundPayload;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Thread;
use App\Support\Address;
use App\Support\MailHtml;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Email masuk dari webhook Brevo Inbound Parsing → Message di mailbox yang tepat,
 * tersambung ke utas yang benar.
 *
 * Urutan pencarian utas: token Reply-To (kominfo+TOKEN@in…) → In-Reply-To /
 * References → utas baru. Token paling andal karena tidak bergantung pada
 * Message-ID yang bisa ditulis ulang di perjalanan.
 */
class InboundProcessor
{
    /**
     * Simpan payload webhook (bisa berisi beberapa email) lalu proses di antrean.
     *
     * @param  array<string, mixed>  $payload
     * @return int jumlah email baru
     */
    public function store(array $payload): int
    {
        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [$payload];
        $created = 0;

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['From'])) {
                continue;
            }

            // Brevo mengirim Uuid sebagai array (satu per penerima), bukan string.
            $uuid = Arr::first(Arr::wrap($item['Uuid'] ?? null), fn ($v) => is_string($v) && $v !== '');

            $row = InboundPayload::query()->firstOrCreate(
                ['uuid' => $uuid ?? sha1(json_encode($item) ?: '')],
                ['payload' => $item],
            );

            if ($row->wasRecentlyCreated) {
                ProcessInboundEmail::dispatch($row->id);
                $created++;
            }
        }

        return $created;
    }

    public function process(InboundPayload $payload): ?Message
    {
        if ($payload->processed_at && $payload->message_id) {
            return $payload->message;
        }

        $item = $payload->payload;
        $route = $this->resolveMailbox($item);

        if (! $route) {
            $payload->update(['processed_at' => now(), 'error' => 'Tidak ada mailbox untuk penerima: '.implode(', ', $this->recipientAddresses($item))]);

            return null;
        }

        [$mailbox, $token] = $route;

        return DB::transaction(function () use ($payload, $item, $mailbox, $token) {
            $from = $this->mailboxObject($item['From'] ?? []);
            $subject = mb_substr(trim((string) ($item['Subject'] ?? '')), 0, 250);
            $headers = $this->headers($item);
            $references = trim((string) ($headers['references'] ?? ''));
            $inReplyTo = trim((string) ($item['InReplyTo'] ?? $headers['in-reply-to'] ?? ''), '<> ');

            $delivery = $token && str_starts_with($token, 'b')
                ? Delivery::query()->where('reply_token', $token)->first()
                : null;

            $thread = $this->findThread($mailbox, $token, $delivery, $inReplyTo, $references)
                ?? Thread::create(['mailbox_id' => $mailbox->id, 'subject' => $subject]);

            $rawHtml = (string) ($item['RawHtmlBody'] ?? '');
            $rawText = (string) ($item['RawTextBody'] ?? '');
            $html = $rawHtml !== '' ? MailHtml::sanitize($rawHtml) : MailHtml::fromText($rawText);
            $text = $rawText !== '' ? $rawText : MailHtml::toText($html);
            $spamScore = $item['SpamScore'] ?? Arr::get($item, 'Spam.Score');
            $isSpam = is_numeric($spamScore) && (float) $spamScore >= Settings::spamThreshold();
            $attachments = array_values(array_filter($item['Attachments'] ?? [], 'is_array'));

            $message = Message::create([
                'mailbox_id' => $mailbox->id,
                'thread_id' => $thread->id,
                'contact_id' => Contact::query()->where('email', $from['email'])->value('id'),
                'direction' => Message::IN,
                'status' => MessageStatus::Received,
                'message_id_header' => trim((string) ($item['MessageId'] ?? ''), '<> ') ?: null,
                'in_reply_to' => $inReplyTo ?: null,
                'references' => $references ?: null,
                'from_name' => $from['name'],
                'from_email' => $from['email'],
                'to' => $this->mailboxList($item['To'] ?? []),
                'cc' => $this->mailboxList($item['Cc'] ?? []),
                'reply_to' => $this->mailboxObject($item['ReplyTo'] ?? [])['email'] ?: null,
                'subject' => $subject,
                'body_html' => $html,
                'body_text' => $text,
                'snippet' => MailHtml::snippet(($item['ExtractedMarkdownMessage'] ?? null) ?: $text),
                'size' => strlen($rawHtml) + strlen($rawText) + (int) array_sum(array_column($attachments, 'ContentLength')),
                'spam_score' => is_numeric($spamScore) ? (float) $spamScore : null,
                'is_read' => false,
                'has_attachments' => $attachments !== [],
                'received_at' => $this->date($item['SentAtDate'] ?? null),
            ]);

            foreach ($attachments as $a) {
                $attachment = $message->attachments()->create([
                    'filename' => mb_substr((string) ($a['Name'] ?? 'lampiran'), 0, 250),
                    'mime' => (string) ($a['ContentType'] ?? 'application/octet-stream'),
                    'size' => (int) ($a['ContentLength'] ?? 0),
                    'content_id' => trim((string) ($a['ContentID'] ?? ''), '<> ') ?: null,
                    'download_token' => $a['DownloadToken'] ?? null,
                ]);

                if ($attachment->download_token) {
                    DownloadInboundAttachment::dispatch($attachment->id)->afterCommit();
                }
            }

            // Balasan baru membawa utas kembali ke Kotak Masuk, seperti klien email pada umumnya.
            $thread->forceFill([
                'folder' => $isSpam ? Folder::Spam : Folder::Inbox,
                'trashed_at' => null,
                'subject' => $thread->subject ?: $subject,
            ])->save();
            $thread->refreshSummary();

            $delivery?->update(['replied_at' => $delivery->replied_at ?? now(), 'thread_id' => $thread->id]);

            $payload->update(['processed_at' => now(), 'message_id' => $message->id, 'error' => null]);

            return $message;
        });
    }

    /**
     * Mailbox tujuan berdasarkan alamat penerima di subdomain inbound.
     *
     * @param  array<string, mixed>  $item
     * @return array{0: Mailbox, 1: string|null}|null
     */
    private function resolveMailbox(array $item): ?array
    {
        $domains = Domain::query()->where('is_active', true)->get();

        foreach ($this->recipientAddresses($item) as $email) {
            [$local, $tag, $host] = Address::split($email);
            $domain = $domains->first(fn (Domain $d) => in_array($host, [strtolower($d->inbound_domain), strtolower($d->name)], true));

            if (! $domain) {
                continue;
            }

            $mailbox = Mailbox::query()->where('domain_id', $domain->id)->where('local_part', $local)->where('is_active', true)->first();

            if ($mailbox) {
                return [$mailbox, $tag];
            }
        }

        return null;
    }

    private function findThread(Mailbox $mailbox, ?string $token, ?Delivery $delivery, string $inReplyTo, string $references): ?Thread
    {
        if ($token && str_starts_with($token, 't')) {
            $thread = Thread::query()->where('mailbox_id', $mailbox->id)->where('reply_token', $token)->first();

            if ($thread) {
                return $thread;
            }
        }

        if ($delivery && $delivery->broadcast?->mailbox_id === $mailbox->id) {
            return $delivery->thread ?? $this->threadForBroadcastReply($delivery);
        }

        $ids = array_filter([$inReplyTo, ...array_map(fn ($r) => trim($r, '<> '), preg_split('/\s+/', $references) ?: [])]);

        if ($ids === []) {
            return null;
        }

        return Message::query()
            ->where('mailbox_id', $mailbox->id)
            ->whereIn('message_id_header', $ids)
            ->whereNotNull('thread_id')
            ->latest('id')
            ->first()
            ?->thread;
    }

    /**
     * Balasan pertama atas email siaran: buat utas berisi salinan email personal
     * yang dikirim ke penerima itu, supaya operator melihat konteks percakapannya.
     */
    private function threadForBroadcastReply(Delivery $delivery): Thread
    {
        $broadcast = $delivery->broadcast;
        $mail = OutgoingMail::forBroadcast($broadcast, $delivery);

        $thread = Thread::create([
            'mailbox_id' => $broadcast->mailbox_id,
            'broadcast_id' => $broadcast->id,
            'subject' => $mail->subjectLine,
            'reply_token' => Thread::newReplyToken(),
        ]);

        Message::create([
            'mailbox_id' => $broadcast->mailbox_id,
            'thread_id' => $thread->id,
            'user_id' => $broadcast->user_id,
            'contact_id' => $delivery->contact_id,
            'direction' => Message::OUT,
            'status' => MessageStatus::Sent,
            'message_id_header' => $delivery->provider_message_id,
            'from_name' => $broadcast->mailbox->display_name,
            'from_email' => $broadcast->mailbox->address,
            'to' => [['email' => $delivery->email, 'name' => $delivery->name]],
            'reply_to' => $mail->replyToAddress,
            'subject' => $mail->subjectLine,
            'body_html' => $mail->bodyHtml,
            'body_text' => MailHtml::toText($mail->bodyHtml),
            'snippet' => MailHtml::snippet(MailHtml::toText($mail->bodyHtml)),
            'is_read' => true,
            'sent_at' => $delivery->sent_at ?? $broadcast->started_at ?? now(),
        ]);

        $delivery->update(['thread_id' => $thread->id]);

        return $thread;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function recipientAddresses(array $item): array
    {
        $list = array_merge(
            array_map(fn ($r) => is_array($r) ? ($r['Address'] ?? '') : (string) $r, (array) ($item['Recipients'] ?? [])),
            array_column($this->mailboxList($item['To'] ?? []), 'email'),
            array_column($this->mailboxList($item['Cc'] ?? []), 'email'),
        );

        return array_values(array_unique(array_filter(array_map('mb_strtolower', array_map('trim', $list)))));
    }

    /** @return array{email: string, name: string|null} */
    private function mailboxObject(mixed $value): array
    {
        if (is_string($value)) {
            return Address::parse($value) ?? ['email' => mb_strtolower($value), 'name' => null];
        }

        $value = is_array($value) ? $value : [];

        return [
            'email' => mb_strtolower(trim((string) ($value['Address'] ?? $value['address'] ?? ''))),
            'name' => trim((string) ($value['Name'] ?? $value['name'] ?? '')) ?: null,
        ];
    }

    /** @return list<array{email: string, name: string|null}> */
    private function mailboxList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        // Satu objek mailbox, bukan daftar.
        if (isset($value['Address'])) {
            $value = [$value];
        }

        return array_values(array_filter(array_map(fn ($v) => $this->mailboxObject($v), $value), fn ($m) => $m['email'] !== ''));
    }

    /**
     * Header dengan kunci huruf kecil; header berulang digabung.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private function headers(array $item): array
    {
        $out = [];

        foreach ((array) ($item['Headers'] ?? []) as $key => $value) {
            $out[strtolower((string) $key)] = is_array($value) ? implode(' ', $value) : (string) $value;
        }

        return $out;
    }

    private function date(mixed $value): CarbonImmutable
    {
        try {
            // Simpan dalam zona waktu aplikasi; Eloquent tidak mengonversi offset sendiri.
            return ($value ? CarbonImmutable::parse((string) $value) : CarbonImmutable::now())->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return CarbonImmutable::now();
        }
    }
}
