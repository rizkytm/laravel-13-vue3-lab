<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pilih locale dari query ?lang=xx (disimpan di session → persisten),
 * lalu set locale aplikasi. View memakai __('Key') → terjemahan dari lang/.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($lang = $request->query('lang')) {
            $request->session()->put('locale', $lang);
        }

        App::setLocale($request->session()->get('locale', config('app.locale')));

        // Carbon locale terpisah dari app locale — tanpa ini,
        // isoFormat/translatedFormat tetap pakai bahasa Inggris.
        Carbon::setLocale(App::getLocale());

        return $next($request);
    }
}
