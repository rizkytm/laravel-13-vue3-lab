<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            // onlyInput: input email tidak hilang saat form di-render ulang
            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        // regenerate: cegah session fixation — session id BARU setelah login,
        // id lama (yang mungkin diketahui penyerang) jadi tidak berlaku
        $request->session()->regenerate();

        return redirect()->intended(route('orders.index'));
    }

    /**
     * Logout. Tiga langkah wajib: hapus auth, invalidate session, token baru.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();      // semua data session dihapus
        $request->session()->regenerateToken(); // CSRF token baru (mencegah replay form lama)

        return redirect()->route('login');
    }
}
