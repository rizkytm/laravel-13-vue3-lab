# Audit — Checklist Code Review vs Project Ini

Audit `~/Documents/playground/roadmap/checklist-code-review.md` terhadap
laravel13-lab. Acuan: OWASP ASVS Level 2 (kaitan roadmap poin 6).

## Security

| Checklist | Status | Bukti |
|---|---|---|
| XSS via v-html | ✅ n/a | Semua view Blade `{{ }}` (auto-escape); tidak ada `{!! !!}`/v-html |
| Mass assignment | ✅ | `$fillable` eksplisit di semua model; `#[Fillable]` attribute di User (M2/M3) |
| IDOR / tanpa authorization | ✅ | `OrderPolicy` + `Gate::authorize` web & API (M3), scoping `$request->user()->orders()` (M4) |
| Rate limit login | ✅ | `RateLimiter::for('login')` 5/menit → 429 (M3), dipakai juga di `/api/token` |
| Raw query / SQL injection | ✅ | Semua query builder + binding; `exists:` rule; tidak ada string concat SQL |
| Encrypted data at rest | ✅ | `phone` cast `encrypted` (M7) — ciphertext di DB |
| Signed URL untuk resource guest | ✅ | `/orders/{id}/invoice` middleware `signed` + expiry 30 menit (M7) |
| Password hashing | ✅ | cast `hashed` (bcrypt, BCRYPT_ROUNDS=12) |
| Error gak bocorkan info internal | ✅ | Custom render: 404/403 API message generik, tanpa nama model (M7) |
| Upload validation | ✅ | `image` + `max:2048`, path random, disk privat (M6/M7) |
| CSRF | ✅ | `@csrf` semua form web; stateless API pakai token (framework bawaan) |

## Clean Code / Performance / Observability

| Checklist | Status | Bukti |
|---|---|---|
| SRP / fat controller | ✅ | Controller tipis → Service (M1), Job (M5), FormRequest (M3/M4) |
| Computed vs method v-if | ✅ n/a | Vue di fase F |
| v-for `:key` unik | ✅ n/a | Vue di fase F |
| N+1 / eager loading | ✅ | `with()`+`withCount`, test query count < 10 (M2) |
| Logika di raw query | ✅ | Carbon range/binding; tidak ada `CURDATE()`/`whereRaw` tanggal |
| EventBus listener leak | ✅ n/a | Vue di fase F |
| Kerja berat sync → queue | ✅ | `ProcessPayment` job + notification `ShouldQueue` (M5) |
| Cache + invalidation | ✅ | `Cache::remember` + `Product::saved/deleted` (M6) |
| Structured logging | ✅ | Channel JSON + `Log::withContext(request_id)` correlation (M7) |
| Event/job setelah commit | ✅ | Test rollback `assertNothingPushed`; caller-fired events (M5/M6) |

## Gap diketahui (belum ditangani — kandidat M8 / fase F / infra)

- **Security headers** (CSP, HSTS, X-Frame-Options) — belum diset
- **APP_DEBUG=true** di `.env` — wajib `false` di produksi (debug = stack trace bocor)
- Token Sanctum belum punya expiry/rotation policy
- Tidak ada audit log perubahan data (activity log)
- Vue fase F: XSS v-html, key unik, listener leak — dicek saat F1-F3
