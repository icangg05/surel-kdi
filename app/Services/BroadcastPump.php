<?php

namespace App\Services;

use App\Enums\BroadcastStatus;
use App\Enums\DeliveryStatus;
use App\Jobs\SendBroadcastEmail;
use App\Mail\OutgoingMail;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\Delivery;
use App\Support\Settings;
use App\Support\SmtpStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Siaran dikirim bertahap: scheduler memanggil pump() tiap menit, yang
 * melepas paling banyak `per_minute` email dan tidak melebihi sisa kuota
 * harian. Sisa penerima otomatis lanjut keesokan hari setelah kuota reset.
 */
class BroadcastPump
{
    public function __construct(private QuotaManager $quota) {}

    /**
     * Kontak tujuan dari pilihan grup + kontak, unik per alamat.
     *
     * @param  array{groups?: list<int>, contacts?: list<int>}|null  $audience
     * @return Collection<int, Contact>
     */
    public function audience(?array $audience): Collection
    {
        $groups = $audience['groups'] ?? [];
        $ids = $audience['contacts'] ?? [];

        if ($groups === [] && $ids === []) {
            return new Collection;
        }

        return Contact::query()
            ->where(fn ($q) => $q
                ->whereIn('id', $ids)
                ->orWhereHas('groups', fn ($g) => $g->whereIn('contact_groups.id', $groups)))
            ->orderBy('name')
            ->get()
            ->unique('email')
            ->values();
    }

    public function launch(Broadcast $broadcast, ?CarbonInterface $at = null): void
    {
        $contacts = $this->audience($broadcast->audience);
        $domain = $broadcast->mailbox->domain->name;
        $now = now();

        DB::transaction(function () use ($broadcast, $contacts, $domain, $now, $at) {
            $broadcast->deliveries()->delete();

            foreach ($contacts->chunk(500) as $chunk) {
                $rows = $chunk->map(fn (Contact $c) => [
                    'uuid' => $uuid = (string) Str::uuid(),
                    'broadcast_id' => $broadcast->id,
                    'contact_id' => $c->id,
                    'email' => $c->email,
                    'name' => $c->name,
                    'kind' => 'to',
                    // Kontak yang pernah bounce/spam tidak dikirimi lagi, tapi tetap tercatat.
                    'status' => $c->status->value === 'active' ? DeliveryStatus::Pending->value : DeliveryStatus::Skipped->value,
                    'error' => $c->status->value === 'active' ? null : 'Kontak berstatus '.$c->status->label(),
                    'reply_token' => 'b'.Str::lower(Str::random(11)),
                    'provider_message_id' => $uuid.'@'.$domain,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                Delivery::query()->insert($rows);
            }

            $scheduled = $at !== null && $at->isFuture();

            $broadcast->forceFill([
                'total' => $contacts->count(),
                'status' => $scheduled ? BroadcastStatus::Scheduled : BroadcastStatus::Sending,
                'scheduled_at' => $scheduled ? $at : null,
                'started_at' => $scheduled ? null : $now,
                'completed_at' => null,
            ])->save();
        });
    }

    /** Satu putaran pemompaan. Mengembalikan jumlah email yang dilepas ke antrean. */
    public function pump(): int
    {
        Broadcast::query()
            ->where('status', BroadcastStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->update(['status' => BroadcastStatus::Sending, 'started_at' => now()]);

        $budget = Settings::perMinute();
        $released = 0;

        $running = Broadcast::query()
            ->whereIn('status', BroadcastStatus::running())
            ->orderBy('started_at')
            ->get();

        foreach ($running as $broadcast) {
            $pending = $broadcast->deliveries()->where('status', DeliveryStatus::Pending);

            if (! $pending->exists()) {
                $this->completeIfDone($broadcast);

                continue;
            }

            $take = min($budget - $released, $this->quota->remaining(), $pending->count());

            if ($take <= 0) {
                if ($this->quota->remaining() <= 0) {
                    $broadcast->update(['status' => BroadcastStatus::WaitingQuota]);
                }

                continue;
            }

            if (! $this->quota->reserve($take)) {
                $broadcast->update(['status' => BroadcastStatus::WaitingQuota]);

                continue;
            }

            $ids = $broadcast->deliveries()->where('status', DeliveryStatus::Pending)->orderBy('id')->limit($take)->pluck('id');
            Delivery::query()->whereIn('id', $ids)->update(['status' => DeliveryStatus::Queued]);

            // Status diset sebelum dispatch: job bisa selesai (dan menandai siaran
            // Selesai) sebelum baris ini sempat jalan bila antrean sinkron/cepat.
            $broadcast->update(['status' => BroadcastStatus::Sending]);
            $ids->each(fn (int $id) => SendBroadcastEmail::dispatch($id));
            $released += $ids->count();
        }

        return $released;
    }

    /** Dipanggil job untuk satu penerima; kreditnya sudah direservasi oleh pump(). */
    public function sendOne(Delivery $delivery): void
    {
        $delivery->loadMissing('broadcast.mailbox.domain');
        $broadcast = $delivery->broadcast;

        if ($delivery->status !== DeliveryStatus::Queued || ! $broadcast) {
            return;
        }

        if ($broadcast->status === BroadcastStatus::Paused || $broadcast->status === BroadcastStatus::Cancelled) {
            $this->quota->release(1);
            $delivery->update(['status' => $broadcast->status === BroadcastStatus::Paused ? DeliveryStatus::Pending : DeliveryStatus::Cancelled]);

            return;
        }

        try {
            Mail::send(OutgoingMail::forBroadcast($broadcast, $delivery));
        } catch (TransportExceptionInterface $e) {
            $this->quota->release(1);

            if (Outbox::isQuotaError($e)) {
                $this->quota->markExhausted();
                $delivery->update(['status' => DeliveryStatus::Pending]);
                $broadcast->update(['status' => BroadcastStatus::WaitingQuota]);

                return;
            }

            // IP/login ditolak: jeda siaran (penerima kembali menunggu) sampai admin membereskan Brevo.
            if (SmtpStatus::isConfigError($e)) {
                $delivery->update(['status' => DeliveryStatus::Pending, 'error' => SmtpStatus::fail($e)]);
                $broadcast->update(['status' => BroadcastStatus::Paused]);

                return;
            }

            throw $e;
        }

        SmtpStatus::ok();

        $delivery->update(['status' => DeliveryStatus::Sent, 'sent_at' => now(), 'error' => null]);
        $delivery->contact?->forceFill(['last_contacted_at' => now()])->save();
        $this->completeIfDone($broadcast);
    }

    public function markFailed(Delivery $delivery, Throwable $e): void
    {
        $delivery->update(['status' => DeliveryStatus::Failed, 'failed_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 250)]);

        if ($delivery->broadcast) {
            $this->completeIfDone($delivery->broadcast);
        }
    }

    public function pause(Broadcast $broadcast): void
    {
        $broadcast->update(['status' => BroadcastStatus::Paused]);
    }

    public function resume(Broadcast $broadcast): void
    {
        $broadcast->update(['status' => BroadcastStatus::Sending]);
    }

    public function cancel(Broadcast $broadcast): void
    {
        $broadcast->deliveries()->where('status', DeliveryStatus::Pending)->update(['status' => DeliveryStatus::Cancelled]);
        $broadcast->update(['status' => BroadcastStatus::Cancelled, 'completed_at' => now()]);
    }

    private function completeIfDone(Broadcast $broadcast): void
    {
        $open = $broadcast->deliveries()->whereIn('status', [DeliveryStatus::Pending, DeliveryStatus::Queued])->exists();

        if (! $open && in_array($broadcast->status, BroadcastStatus::running(), true)) {
            $broadcast->update(['status' => BroadcastStatus::Completed, 'completed_at' => now()]);
        }
    }
}
