<?php

namespace App\Console\Commands;

use App\Models\InboundPayload;
use App\Models\Mailbox;
use App\Models\Message;
use App\Services\InboundProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('brevo:simulate-inbound
    {--from=warga@example.com : Alamat pengirim}
    {--name=Pengirim Uji : Nama pengirim}
    {--subject= : Subjek (bawaan: balasan atas email terkirim terakhir)}
    {--mailbox= : ID mailbox tujuan (bawaan: mailbox pertama)}
    {--fresh : Email baru, bukan balasan}')]
#[Description('Uji email masuk tanpa DNS/tunnel: buat payload seperti webhook Brevo lalu proses')]
class BrevoSimulateInbound extends Command
{
    public function handle(InboundProcessor $processor): int
    {
        $mailbox = Mailbox::query()->with('domain')->when($this->option('mailbox'), fn ($q, $id) => $q->whereKey($id))->first();

        if (! $mailbox) {
            $this->error('Belum ada mailbox. Jalankan db:seed atau buat dari halaman admin.');

            return self::FAILURE;
        }

        $lastSent = $this->option('fresh') ? null : Message::query()
            ->where('mailbox_id', $mailbox->id)->where('direction', Message::OUT)->where('status', 'sent')->latest('id')->first();

        // Bila membalas, kirim ke alamat Reply-To berpelacak, persis seperti klien email penerima.
        $to = $lastSent->reply_to ?? $mailbox->inbound_address;
        $subject = $this->option('subject') ?: ($lastSent ? 'Re: '.$lastSent->subject : 'Email uji masuk');
        $body = "Terima kasih, informasinya sudah kami terima.\n\nSalam,\n".$this->option('name');

        $uuid = (string) Str::uuid();
        $item = [
            'Uuid' => [$uuid],
            'MessageId' => Str::uuid().'@example.com',
            'InReplyTo' => $lastSent?->message_id_header,
            'From' => ['Name' => $this->option('name'), 'Address' => $this->option('from')],
            'To' => [['Name' => $mailbox->display_name, 'Address' => $to]],
            'Recipients' => [$to],
            'Cc' => [],
            'ReplyTo' => null,
            'SentAtDate' => now()->toRfc2822String(),
            'Subject' => $subject,
            'RawHtmlBody' => '<p>'.nl2br(e($body)).'</p>',
            'RawTextBody' => $body,
            'ExtractedMarkdownMessage' => $body,
            'SpamScore' => 0.4,
            'Attachments' => [],
            'Headers' => ['Message-ID' => '<x@example.com>'],
        ];

        $processor->store(['items' => [$item]]);
        $payload = InboundPayload::query()->where('uuid', $uuid)->firstOrFail();
        $message = $processor->process($payload);

        if (! $message) {
            $this->error((string) $payload->fresh()?->error);

            return self::FAILURE;
        }

        $this->info("Email masuk dibuat di {$mailbox->address} → utas #{$message->thread_id} ($subject).");

        return self::SUCCESS;
    }
}
