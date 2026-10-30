<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\MessageStatus;
use App\Jobs\SendMessage;
use App\Mail\OutgoingMail;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Thread;
use App\Support\Settings;
use App\Support\SmtpStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Jalur email biasa (bukan siaran): draf → antrean/terjadwal → SMTP Brevo.
 */
class Outbox
{
    public function __construct(private QuotaManager $quota) {}

    /** Kirim sekarang, atau jadwalkan bila $at di masa depan. */
    public function submit(Message $message, ?CarbonInterface $at = null): void
    {
        DB::transaction(function () use ($message, $at) {
            $message->loadMissing('mailbox.domain');

            $thread = $message->thread ?? Thread::create([
                'mailbox_id' => $message->mailbox_id,
                'subject' => $message->subject,
            ]);

            $message->deliveries()->delete();
            $recipients = $message->recipients();
            $contacts = Contact::query()->whereIn('email', array_column($recipients, 'email'))->pluck('id', 'email');

            foreach ($recipients as $r) {
                $message->deliveries()->create([
                    'email' => $r['email'],
                    'name' => $r['name'],
                    'kind' => $r['kind'],
                    'contact_id' => $contacts[$r['email']] ?? null,
                ]);
            }

            $scheduled = $at !== null && $at->isFuture();

            $message->forceFill([
                'thread_id' => $thread->id,
                'from_email' => $message->mailbox->address,
                'from_name' => $message->mailbox->display_name,
                'message_id_header' => $message->message_id_header ?? $message->makeMessageIdHeader(),
                'reply_to' => $message->mailbox->replyAddress(Settings::replyTracking() ? $thread->reply_token : null),
                'status' => $scheduled ? MessageStatus::Scheduled : MessageStatus::Queued,
                'scheduled_at' => $scheduled ? $at : null,
                'error' => null,
            ])->save();

            $thread->refreshSummary();
        });

        if ($message->status === MessageStatus::Queued) {
            SendMessage::dispatch($message->id)->afterCommit();
        }
    }

    /** Dipanggil job: reservasi kredit lalu kirim. Tidak melempar bila kuota habis — scheduler akan mencoba lagi. */
    public function deliver(Message $message): void
    {
        if (! in_array($message->status, MessageStatus::pending(), true)) {
            return;
        }

        $credits = max(1, $message->deliveries()->count());

        if (! $this->quota->reserve($credits)) {
            $message->update(['status' => MessageStatus::WaitingQuota]);

            return;
        }

        try {
            Mail::send(OutgoingMail::forMessage($message));
        } catch (TransportExceptionInterface $e) {
            $this->quota->release($credits);

            if (self::isQuotaError($e)) {
                $this->quota->markExhausted();
                $message->update(['status' => MessageStatus::WaitingQuota, 'error' => 'Limit harian Brevo tercapai.']);

                return;
            }

            // Salah konfigurasi (IP/login): mencoba ulang hanya memperlama; tampilkan sebabnya.
            if (SmtpStatus::isConfigError($e)) {
                $this->markFailed($message, new \RuntimeException(SmtpStatus::fail($e)));

                return;
            }

            throw $e;
        }

        SmtpStatus::ok();

        $now = now();
        $message->update(['status' => MessageStatus::Sent, 'sent_at' => $now, 'error' => null]);
        $message->deliveries()->update([
            'status' => DeliveryStatus::Sent,
            'sent_at' => $now,
            'provider_message_id' => $message->message_id_header,
        ]);
        Contact::query()->whereIn('id', $message->deliveries()->whereNotNull('contact_id')->select('contact_id'))->update(['last_contacted_at' => $now]);
        $message->thread?->refreshSummary();
    }

    public function markFailed(Message $message, Throwable $e): void
    {
        $message->update(['status' => MessageStatus::Failed, 'error' => mb_substr($e->getMessage(), 0, 250)]);
        $message->deliveries()->update(['status' => DeliveryStatus::Failed, 'failed_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 250)]);
    }

    /** Tiap menit: lepas email terjadwal yang jatuh tempo dan yang menunggu kuota. */
    public function dispatchDue(): int
    {
        $count = 0;

        Message::query()
            ->where('status', MessageStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->each(function (Message $message) use (&$count) {
                $message->update(['status' => MessageStatus::Queued]);
                SendMessage::dispatch($message->id);
                $count++;
            });

        if ($this->quota->remaining() > 0) {
            Message::query()
                ->where('status', MessageStatus::WaitingQuota)
                ->orderBy('id')
                ->limit(20)
                ->pluck('id')
                ->each(function (int $id) use (&$count) {
                    SendMessage::dispatch($id);
                    $count++;
                });
        }

        return $count;
    }

    /** Brevo menolak karena limit kredit/harian akun. Pesannya tidak baku, jadi dicocokkan kata kunci. */
    public static function isQuotaError(Throwable $e): bool
    {
        return (bool) preg_match('/(daily|sending)\s*limit|quota|credits?|exceed/i', $e->getMessage());
    }
}
