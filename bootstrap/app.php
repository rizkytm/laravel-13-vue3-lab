<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
        $exceptions->render(function (HttpException $e, Request $request) {
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
