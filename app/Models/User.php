<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
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
        ];
    }
}
