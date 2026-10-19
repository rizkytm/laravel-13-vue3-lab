<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hardening ASVS L3 (V3.4 + V14.3) — dipasang GLOBAL (bootstrap/app.php)
 * supaya kena web, API, dan panel Filament sekaligus.
 *
 * Header yang diset:
 * - nosniff            → cegah MIME sniffing (ASVS 3.4.4)
 * - X-Frame-Options    → anti-clickjacking, pelengkap CSP frame-ancestors
 *                        (ASVS 3.4.6). Invoice dibuka di tab baru, bukan iframe.
 * - Referrer-Policy    → batasi bocor URL lintas-origin (ASVS 3.4.5)
 * - HSTS               → paksa HTTPS di produksi (ASVS 3.4.1). SELALU dikirim:
 *                        browser hanya memprosesnya atas HTTPS, jadi di dev
 *                        HTTP header ini diabaikan — aman & mudah dites.
 * - Cache-Control      → no-store utk semua response dinamis (ASVS 14.3.2).
 *                        Static file (public/, aset Vite) dilayani server
 *                        langsung TANPA middleware ini → tetap bisa cache.
 * - Content-Security-Policy → sengaja TANPA script-src/style-src/default-src:
 *                        Filament v4 (panel /admin) memakai inline <script> &
 *                        <style> tanpa dukungan nonce — CSP script-src ketat
 *                        akan mematahkan admin panel. Directive yang dipasang
 *                        hanya yang TIDAK menyentuh eksekusi script:
 *                          frame-ancestors 'none'  → anti-clickjacking
 *                          object-src 'none'       → blokir plugin/embed asing
 *                          base-uri 'self'         → blokir base-tag hijack
 *                          form-action 'self'      → form cuma boleh submit
 *                                                     ke origin sendiri
 */
class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // no-store, private: browser & proxy tak boleh simpan response dinamis
        // (data login/order/pesanan) di cache bersama.
        $response->headers->set('Cache-Control', 'no-store, private');

        $response->headers->set(
            'Content-Security-Policy',
            implode('; ', [
                "frame-ancestors 'none'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ]),
        );

        return $response;
    }
}
