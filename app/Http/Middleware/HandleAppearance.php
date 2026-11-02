<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Selalu terang kecuali pengguna memilih Gelap di Pengaturan › Tampilan (tidak mengikuti tema sistem).
        View::share('appearance', $request->cookie('appearance') === 'dark' ? 'dark' : 'light');

        return $next($request);
    }
}
