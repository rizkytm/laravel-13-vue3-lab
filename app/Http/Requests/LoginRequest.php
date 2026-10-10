<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi login dipindah dari controller ke FormRequest —
 * controller cukup terima data yang SUDAH valid ($request->only(...)).
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi per-form. Login memang terbuka untuk semua → true.
        // Di FormRequest lain (misal update resource), return $this->user()->can(...)
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ];
    }
}
