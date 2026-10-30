<?php

namespace App\Mail;

use App\Models\Attachment as AttachmentModel;
use App\Models\Broadcast;
use App\Models\Delivery;
use App\Models\Mailbox;
use App\Models\Message;
use App\Support\MailHtml;
use App\Support\MergeFields;
use App\Support\Settings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Collection;
use Symfony\Component\Mime\Email;

/**
 * Satu email keluar lewat SMTP Brevo — dipakai email biasa maupun siaran.
 * Tidak pernah di-queue sebagai Mailable; job kita sendiri yang memanggilnya
 * setelah kredit kuota direservasi.
 */
class OutgoingMail extends Mailable
{
    /**
     * @param  list<array{email: string, name?: string|null}>  $toList
     * @param  list<array{email: string, name?: string|null}>  $ccList
     * @param  list<array{email: string, name?: string|null}>  $bccList
     * @param  list<string>  $referenceIds
     * @param  Collection<int, AttachmentModel>  $files
     */
    public function __construct(
        public Mailbox $mailbox,
        public string $subjectLine,
        public string $bodyHtml,
        public string $messageIdHeader,
        public string $customId,
        public array $toList,
        public array $ccList = [],
        public array $bccList = [],
        public ?string $replyToAddress = null,
        public ?string $inReplyTo = null,
        public array $referenceIds = [],
        public Collection $files = new Collection,
    ) {}

    public static function forMessage(Message $message): self
    {
        $message->loadMissing('mailbox.domain', 'thread', 'attachments');

        return new self(
            mailbox: $message->mailbox,
            subjectLine: $message->subject,
            bodyHtml: (string) $message->body_html,
            messageIdHeader: (string) $message->message_id_header,
            customId: 'm:'.$message->uuid,
            toList: $message->to ?? [],
            ccList: $message->cc ?? [],
            bccList: $message->bcc ?? [],
            replyToAddress: $message->reply_to,
            inReplyTo: $message->in_reply_to,
            referenceIds: array_values(array_filter(explode(' ', (string) $message->references))),
            files: $message->attachments->filter->isAvailable()->values(),
        );
    }

    /** Email personal untuk satu penerima siaran: merge field diisi, Reply-To memakai token penerima. */
    public static function forBroadcast(Broadcast $broadcast, Delivery $delivery): self
    {
        $broadcast->loadMissing('mailbox.domain', 'attachments');
        $delivery->loadMissing('contact');
        $values = MergeFields::valuesFor($delivery->contact, $delivery->email, $delivery->name);
        $mailbox = $broadcast->mailbox;

        return new self(
            mailbox: $mailbox,
            subjectLine: MergeFields::render($broadcast->subject, $values, html: false),
            bodyHtml: MergeFields::render($broadcast->body_html, $values),
            messageIdHeader: (string) $delivery->provider_message_id,
            customId: 'd:'.$delivery->uuid,
            toList: [['email' => $delivery->email, 'name' => $delivery->name]],
            replyToAddress: $mailbox->replyAddress(Settings::replyTracking() ? $delivery->reply_token : null),
            files: $broadcast->attachments->filter->isAvailable()->values(),
        );
    }

    public function envelope(): Envelope
    {
        $map = fn (array $list) => array_map(fn (array $r) => new Address($r['email'], $r['name'] ?? null), $list);

        return new Envelope(
            from: new Address($this->mailbox->address, $this->mailbox->display_name),
            to: $map($this->toList),
            cc: $map($this->ccList),
            bcc: $map($this->bccList),
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress, $this->mailbox->display_name)] : [],
            subject: $this->subjectLine,
            tags: ['kdi-mail'],
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            messageId: $this->messageIdHeader,
            references: $this->referenceIds,
            text: array_filter([
                'In-Reply-To' => $this->inReplyTo ? '<'.trim($this->inReplyTo, '<>').'>' : null,
                // Dikembalikan Brevo di setiap webhook event — kunci pencocokan status kirim.
                'X-Mailin-custom' => $this->customId,
                'X-Mailin-Tag' => 'kdi-mail',
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.outgoing', with: ['body' => $this->bodyHtml]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return $this->files
            ->map(fn (AttachmentModel $a) => Attachment::fromStorageDisk(AttachmentModel::DISK, (string) $a->path)
                ->as($a->filename)
                ->withMime($a->mime))
            ->all();
    }

    public function build(): static
    {
        return $this->withSymfonyMessage(fn (Email $email) => $email->text(MailHtml::toText($this->bodyHtml)));
    }
}
