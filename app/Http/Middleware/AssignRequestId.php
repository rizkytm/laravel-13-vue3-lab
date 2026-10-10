<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlation id per request — disimpan sebagai log context, jadi SEMUA
 * baris log dalam 1 request membawa request_id yang sama → bisa ditelusuri
 * lintas service (konsep distributed tracing, bahan ELK nanti).
 *
 * Header masuk X-Request-Id dihormati (trace dari gateway/service lain),
 * selain itu generate UUID baru. request_id juga dikirim balik di header.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();

        // Context nempel ke semua log Monolog pada request INI saja
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
