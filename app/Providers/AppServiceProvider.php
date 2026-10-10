<?php

namespace App\Providers;

use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\PaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Container binding: interface → implementasi.
        // Singleton = 1 instance per request lifecycle — semua yang
        // resolve PaymentGateway melihat instance (dan log charge) yang sama.
        // Ganti payment provider asli nanti? Cukup ubah 1 baris ini.
        $this->app->singleton(PaymentGateway::class, FakePaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Brute-force protection: 5 percobaan gagal per menit
        // per kombinasi email+ip → attempt ke-6 dapat 429.
        // Dipakai via middleware 'throttle:login' di routes/web.php.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                strtolower($request->input('email')).'|'.$request->ip()
            );
        });
    }
}
