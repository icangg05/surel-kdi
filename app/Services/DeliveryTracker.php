<?php

namespace App\Services;

use App\Enums\ContactStatus;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\Message;
use Carbon\CarbonImmutable;

/**
 * Menerapkan event webhook transaksional Brevo (delivered, opened, bounce, ...)
 * ke baris Delivery. Event bisa datang ganda dan tidak berurutan.
 */
class DeliveryTracker
{
    private const EVENT_MAP = [
        'request' => DeliveryStatus::Sent,
        'sent' => DeliveryStatus::Sent,
        'delivered' => DeliveryStatus::Delivered,
        'opened' => DeliveryStatus::Opened,
        'unique_opened' => DeliveryStatus::Opened,
        'uniqueopened' => DeliveryStatus::Opened,
        'proxy_open' => DeliveryStatus::Opened,
        'click' => DeliveryStatus::Clicked,
        'clicked' => DeliveryStatus::Clicked,
        'soft_bounce' => DeliveryStatus::SoftBounce,
        'softbounce' => DeliveryStatus::SoftBounce,
        'deferred' => DeliveryStatus::SoftBounce,
        'hard_bounce' => DeliveryStatus::HardBounce,
        'hardbounce' => DeliveryStatus::HardBounce,
        'invalid_email' => DeliveryStatus::HardBounce,
        'invalid' => DeliveryStatus::HardBounce,
        'blocked' => DeliveryStatus::Blocked,
        'spam' => DeliveryStatus::Spam,
        'complaint' => DeliveryStatus::Spam,
        'error' => DeliveryStatus::Failed,
    ];

    /** @param array<string, mixed> $event satu objek event dari Brevo */
    public function handle(array $event): bool
    {
        $delivery = $this->findDelivery($event);
        $name = strtolower((string) ($event['event'] ?? ''));

        if (! $delivery || $name === '') {
            return false;
        }

        $at = $this->occurredAt($event);

        $inserted = DeliveryEvent::query()->insertOrIgnore([
            'delivery_id' => $delivery->id,
            'event' => $name,
            'occurred_at' => $at,
            'payload' => json_encode($event),
            'created_at' => now(),
        ]);

        if ($inserted === 0) {
            return true; // duplikat
        }

        $status = self::EVENT_MAP[$name] ?? null;

        if ($status) {
            $this->apply($delivery, $status, $at, (string) ($event['reason'] ?? ''));
        }

        if ($name === 'unsubscribed') {
            $delivery->contact?->suppress(ContactStatus::Blocked, 'Penerima berhenti berlangganan');
        }

        return true;
    }

    private function apply(Delivery $delivery, DeliveryStatus $status, CarbonImmutable $at, string $reason): void
    {
        $current = $delivery->status;
        $changes = match ($status) {
            DeliveryStatus::Delivered => ['delivered_at' => $delivery->delivered_at ?? $at],
            DeliveryStatus::Opened => ['opened_at' => $delivery->opened_at ?? $at, 'delivered_at' => $delivery->delivered_at ?? $at],
            DeliveryStatus::Clicked => ['clicked_at' => $delivery->clicked_at ?? $at, 'opened_at' => $delivery->opened_at ?? $at, 'delivered_at' => $delivery->delivered_at ?? $at],
            DeliveryStatus::HardBounce, DeliveryStatus::Blocked, DeliveryStatus::Failed, DeliveryStatus::Spam => ['failed_at' => $at, 'error' => $reason ?: $status->label()],
            DeliveryStatus::SoftBounce => ['error' => $reason ?: null],
            default => [],
        };

        // Status hanya naik; kegagalan hanya menimpa bila email belum tercatat diterima.
        $overrides = $status === DeliveryStatus::Spam
            || ($status->isFailure() && $current->rank() < DeliveryStatus::Delivered->rank())
            || (! $status->isFailure() && $status->rank() > $current->rank() && ! $current->isFailure());

        if ($overrides) {
            $changes['status'] = $status;
        }

        $delivery->update($changes);

        $suppress = match ($status) {
            DeliveryStatus::HardBounce => ContactStatus::Bounced,
            DeliveryStatus::Spam => ContactStatus::Complained,
            DeliveryStatus::Blocked => ContactStatus::Blocked,
            default => null,
        };

        if ($suppress) {
            $delivery->contact?->suppress($suppress, $reason ?: $status->label());
        }
    }

    /** @param array<string, mixed> $event */
    private function findDelivery(array $event): ?Delivery
    {
        $email = mb_strtolower((string) ($event['email'] ?? ''));
        $custom = (string) ($event['X-Mailin-custom'] ?? $event['x-mailin-custom'] ?? '');

        if (str_starts_with($custom, 'd:')) {
            return Delivery::query()->where('uuid', substr($custom, 2))->first();
        }

        if (str_starts_with($custom, 'm:')) {
            $messageId = Message::query()->where('uuid', substr($custom, 2))->value('id');

            if ($messageId) {
                return Delivery::query()->where('message_id', $messageId)->where('email', $email)->first();
            }
        }

        $header = trim((string) ($event['message-id'] ?? $event['message_id'] ?? ''), '<> ');

        return $header === '' ? null : Delivery::query()
            ->where('provider_message_id', $header)
            ->where('email', $email)
            ->first();
    }

    /** @param array<string, mixed> $event */
    private function occurredAt(array $event): CarbonImmutable
    {
        foreach (['ts_event', 'ts_epoch', 'ts'] as $key) {
            if (isset($event[$key]) && is_numeric($event[$key])) {
                $ts = (int) $event[$key];

                // ts_epoch dalam milidetik.
                return CarbonImmutable::createFromTimestamp($ts > 10_000_000_000 ? intdiv($ts, 1000) : $ts, config('app.timezone'));
            }
        }

        try {
            return isset($event['date']) ? CarbonImmutable::parse((string) $event['date'])->setTimezone(config('app.timezone')) : CarbonImmutable::now();
        } catch (\Throwable) {
            return CarbonImmutable::now();
        }
    }
}
