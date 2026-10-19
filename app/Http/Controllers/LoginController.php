<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Proses login. Validasi → attempt → regenerate session.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // attempt: cocokkan email+password (hashed) lalu bikin session login
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            // ASVS 16.3.1 — audit event auth gagal. Payload aman: email+ip,
            // TANPA password. (Format terstruktur, konsisten checkout.completed)
            Log::warning('auth.login_failed', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
            ]);

            // onlyInput: input email tidak hilang saat form di-render ulang
            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        // regenerate: cegah session fixation — session id BARU setelah login,
        // id lama (yang mungkin diketahui penyerang) jadi tidak berlaku
        $request->session()->regenerate();

        // ASVS 16.3.1 — audit event auth sukses
        Log::info('auth.login', [
            'user_id' => Auth::id(),
            'email' => $request->input('email'),
            'ip' => $request->ip(),
        ]);

        // Setelah login web → ke SPA (session dibagi lintas port localhost).
        // SPA sendiri mengabaikan redirect ini (fetch redirect:'manual').
        return redirect()->intended(config('app.spa_url'));
    }

    /**
     * Logout. Tiga langkah wajib: hapus auth, invalidate session, token baru.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // ASVS 16.3.1 — audit sebelum sesi dibuang
        Log::info('auth.logout', ['user_id' => Auth::id(), 'ip' => $request->ip()]);

        Auth::logout();

        $request->session()->invalidate();      // semua data session dihapus
        $request->session()->regenerateToken(); // CSRF token baru (mencegah replay form lama)

        return redirect()->route('login');
    }
}
