<?php

namespace Tests\Feature\Mail;

use App\Console\Commands\BrevoRegisterWebhooks;
use App\Services\Brevo;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BrevoApiTest extends MailTestCase
{
    public function test_no_webhooks_yet_is_an_empty_list_not_an_error(): void
    {
        Http::fake(['api.brevo.com/v3/webhooks*' => Http::response(['code' => 'document_not_found', 'message' => 'Webhook record does not exist'], 400)]);

        $this->assertSame([], app(Brevo::class)->webhooks('inbound'));
    }

    public function test_register_webhooks_creates_both_hooks_when_none_exist(): void
    {
        config(['app.url' => 'https://kdi.example.test']);
        Http::fake(function (Request $request) {
            return $request->method() === 'GET'
                ? Http::response(['code' => 'document_not_found', 'message' => 'Webhook record does not exist'], 400)
                : Http::response(['id' => 1], 201);
        });

        $this->artisan('brevo:register-webhooks')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['type'] === 'transactional' && str_ends_with($r['url'], '/webhooks/brevo/events/rahasia-uji'));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['type'] === 'inbound' && $r['domain'] === 'in.ilmifaizan.cloud');
    }

    public function test_webhooks_pointing_at_an_old_tunnel_url_are_replaced(): void
    {
        config(['app.url' => 'https://baru.trycloudflare.com']);
        Http::fake(function (Request $request) {
            if ($request->method() !== 'GET') {
                return Http::response(['id' => 1], $request->method() === 'POST' ? 201 : 204);
            }

            return Http::response(['webhooks' => str_contains($request->url(), 'type=inbound')
                ? [['id' => 8, 'url' => 'https://lama.trycloudflare.com/webhooks/brevo/inbound/rahasia-uji', 'domain' => 'in.ilmifaizan.cloud']]
                : [
                    ['id' => 7, 'url' => 'https://lama.trycloudflare.com/webhooks/brevo/events/rahasia-uji'],
                    ['id' => 9, 'url' => 'https://aplikasi-lain.example/hook'],
                ]]);
        });

        $this->artisan('brevo:register-webhooks')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), 'webhooks/7'));
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), 'webhooks/8'));
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), 'webhooks/9'));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['url'] === 'https://baru.trycloudflare.com/webhooks/brevo/inbound/rahasia-uji');
    }

    public function test_other_api_errors_still_surface(): void
    {
        Http::fake(['api.brevo.com/v3/webhooks*' => Http::response(['code' => 'unauthorized', 'message' => 'Key not found'], 401)]);

        $this->expectException(RequestException::class);
        app(Brevo::class)->webhooks('inbound');
    }

    public function test_rejected_inbound_webhook_reason_is_kept_for_admin_page(): void
    {
        config(['app.url' => 'https://kdi.example.test']);
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response(['code' => 'document_not_found', 'message' => 'Webhook record does not exist'], 400);
            }

            return $request['type'] === 'inbound'
                ? Http::response(['code' => 'invalid_parameter', 'message' => 'Domain is not valid'], 400)
                : Http::response(['id' => 1], 201);
        });

        $this->artisan('brevo:register-webhooks')->assertSuccessful();

        $errors = Cache::get(BrevoRegisterWebhooks::ERRORS_KEY);
        $this->assertSame('invalid_parameter: Domain is not valid', $errors['inbound:in.ilmifaizan.cloud']['message']);
        $this->assertArrayNotHasKey('transactional:', $errors);
    }
}
