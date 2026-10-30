<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\DeliveryTracker;
use App\Services\InboundProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Titik masuk webhook Brevo. Diautentikasi dengan rahasia di URL
 * (BREVO_WEBHOOK_SECRET) karena Brevo tidak menandatangani payload.
 * Selalu cepat membalas 2xx; pekerjaan berat di antrean.
 */
class BrevoWebhookController extends Controller
{
    public function inbound(Request $request, InboundProcessor $processor): JsonResponse
    {
        $count = $processor->store($request->json()->all());

        return response()->json(['received' => $count]);
    }

    public function events(Request $request, DeliveryTracker $tracker): JsonResponse
    {
        /** @var array<mixed> $body */
        $body = $request->json()->all();
        // Brevo mengirim satu objek per event, atau array bila opsi "batched" aktif.
        $events = array_is_list($body) ? $body : [$body];
        $matched = 0;

        foreach ($events as $event) {
            if (is_array($event) && $tracker->handle($event)) {
                $matched++;
            }
        }

        return response()->json(['matched' => $matched]);
    }
}
