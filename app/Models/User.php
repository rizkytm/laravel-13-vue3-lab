<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone', 'is_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable; // HasApiTokens → createToken(), token auth

    /**
     * Order milik user — dipakai scoping di OrderController:
     * $request->user()->orders() (query builder, bukan koleksi).
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Keranjang server-side — checkout membaca dari sini, bukan payload. */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // 'encrypted': di-DB tersimpan CIPHERTEXT (AES-256-CBC via APP_KEY),
            // aplikasi baca → decrypt otomatis. Yang bobol cuma database file
            // TANPA APP_KEY dapat nomor telepon apa pun.
            // Trade-off: TIDAK bisa query/WHERE langsung kolom ini di SQL.
            'phone' => 'encrypted',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Gerbang panel Filament /admin — HANYA user dengan is_admin=true.
     * Tanpa ini, Filament menolak semua login di panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->is_admin;
    }
}
