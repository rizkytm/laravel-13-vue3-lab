<?php

namespace App\Providers;

use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\HttpPaymentGateway;
use App\Services\Payment\MidtransPaymentGateway;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\RefundablePaymentGateway;
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
        // Singleton = 1 instance per request lifecycle.
        // Driver dipilih dari config('payment.driver'):
        //   PAYMENT_DRIVER=fake     → FakePaymentGateway (dev/test)
        //   PAYMENT_DRIVER=http     → HttpPaymentGateway (provider asli)
        //   PAYMENT_DRIVER=midtrans → MidtransPaymentGateway (Snap + webhook)
        // Controller tetap hanya tahu PaymentGateway — ganti driver tanpa
        // menyentuh satu baris controller pun (Open/Closed).
        $this->app->singleton(PaymentGateway::class, function ($app) {
            return match ($app['config']['payment.driver']) {
                'http' => $app->make(HttpPaymentGateway::class),
                'midtrans' => $app->make(MidtransPaymentGateway::class),
                default => $app->make(FakePaymentGateway::class),
            };
        });

        // RefundService bergantung ke kontrak refund (ISP: tidak semua driver
        // wajib refund). Resolve ke instance driver YANG SAMA — Http driver
        // (tanpa refund) cukup diikat ke fake agar binding tetap valid; saat
        // dipakai, RefundService mengecek instanceof RefundablePaymentGateway.
        $this->app->bind(RefundablePaymentGateway::class, function ($app) {
            $driver = $app->make(PaymentGateway::class);

            return $driver instanceof RefundablePaymentGateway
                ? $driver
                : $app->make(FakePaymentGateway::class);
        });
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

        // Register: 3/menit per IP — cukup untuk manusia, mempersulit
        // pembuatan akun massal otomatis
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Password reset (forgot + reset): 3/menit per email+ip —
        // cegah email flood ke satu alamat & brute-force token
        RateLimiter::for('password', function (Request $request) {
            return Limit::perMinute(3)->by(
                strtolower($request->input('email')).'|'.$request->ip()
            );
        });

        // Checkout: 10/menit per user — proteksi payment gateway dari
        // burst order (retry klik ganda, bot). Validasi stok tetap atomik
        // di transaksi; ini lapisan ekonomi, bukan lapisan kebenaran.
        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Ubah password: 5/menit per user (payload TANPA email — limiter
        // 'password' by email tak bisa dipakai) — hambat brute-force
        // current_password dari sesi yang dicuri.
        RateLimiter::for('change-password', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // API v1 (ASVS 2.4.1): lapisan THROTTLE UMUM per user. Endpoint
        // spesifik (checkout 10, register 3, login 5) tetap berlaku dan
        // lebih ketat. 120/menit: cukup utk ASVS, longgar utk e2e
        // (≈40 request/run serial) & CI retry (×3 masih aman).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
