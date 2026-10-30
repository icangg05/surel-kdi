<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBrevoWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('kdimail.brevo.webhook_secret');

        abort_if($secret === '' || ! hash_equals($secret, (string) $request->route('secret')), 404);

        return $next($request);
    }
}
