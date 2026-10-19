<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hardening ASVS: security headers + no-store di SEMUA response
        // (web, API, panel Filament) — append global = terakhir memproses
        // response, jadi header ikut menempel di error/redirect juga.
        $middleware->append(AddSecurityHeaders::class);

        // Append ke web group: jalan SESUDAH StartSession (butuh session
        // untuk persist pilihan locale), SEBELUM controller.
        $middleware->web(append: [
            SetLocale::class,
            AssignRequestId::class,
        ]);
        // Sanctum SPA auth: request dari domain stateful (Vite dev :5173)
        // di-wrap dengan session middleware → /api/* bisa auth via cookie
        // session, bukan hanya Bearer token.
        $middleware->api(prepend: [EnsureFrontendRequestsAreStateful::class]);
        $middleware->api(append: [AssignRequestId::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // PENTING: render callback dijalankan SETELAH prepareException —
        // AuthorizationException sudah di-convert ke AccessDeniedHttpException,
        // ModelNotFoundException ke NotFoundHttpException. Jadi type-hint ke
        // HttpException (induk) + cek status code, bukan exception aslinya.
        //
        // 404 bawaan membocorkan nama model internal
        // ("No query results for model [App\Models\Product] 999") → ganti
        // pesan konsisten tanpa info internal.

        // ASVS 16.3.2 — 401: exception-nya AuthenticationException (BUKAN
        // HttpException — callback di bawah tidak akan dipanggil untuk kasus
        // ini, jadi callback terpisah dengan type-hint yang cocok).
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            Log::warning('auth.unauthenticated', [
                'status' => 401,
                'path' => '/'.$request->path(),
                'user_id' => $request->user()?->getKey(),
                'ip' => $request->ip(),
            ]);

            return null; // biarkan handler default (redirect web / 401 JSON)
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            // ASVS 16.3.2 — audit penolakan otorisasi (403), SEMUA jalur
            // (api & web/panel). Structured, payload aman: path + siapa yang
            // mencoba — tanpa kredensial/token apa pun.
            if ($e->getStatusCode() === 403) {
                Log::warning('auth.denied', [
                    'status' => 403,
                    'path' => '/'.$request->path(),
                    'user_id' => $request->user()?->getKey(),
                    'ip' => $request->ip(),
                ]);
            }

            if (! $request->is('api/*')) {
                return null; // biarkan handler default (web/HTML) yang proses
            }

            return match ($e->getStatusCode()) {
                404 => response()->json(['message' => 'Resource tidak ditemukan.'], 404),
                403 => response()->json(['message' => 'Anda tidak punya akses ke resource ini.'], 403),
                default => null,
            };
        });
    })->create();
