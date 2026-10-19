<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ubah password saat login (ASVS 6.2.2). Tiga jaring pengaman:
 * - current_password → wajib buktikan kepemilikan (Hash::check)
 * - min:8 + confirmed → konsisten dgn kebijakan password lain
 * - different:current_password → password baru tak boleh = password lama
 *   (rule 'different' membandingkan dgn input field lain; 'not_in' hanya
 *   membandingkan dgn literal — jangan dipakai untuk kasus ini)
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi = route middleware 'auth' (sudah login)
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Password lama wajib diisi.',
            'current_password.current_password' => 'Password lama salah.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.different' => 'Password baru tidak boleh sama dengan password lama.',
        ];
    }
}
