# PROGRESS — Laravel 13 Lab

Overview & log progres belajar Laravel 13 + Vue 3. Rencana lengka di:
`~/Documents/playground/roadmap/plan-laravel-13-lab.md`

**Mode belajar:** assistant tulis semua kode + test, Rizky baca + debrief per milestone.
Test dijalankan assistant; fungsi dicek via browser oleh Rizky. Milestone = 1 commit.

## Tech stack

- PHP 8.5, Laravel 13 (Pest), SQLite
- Queue/cache/session: database driver (tanpa Redis)
- Mail: log driver. Broadcasting: log driver
- Frontend Vue 3 (fase F) belum dimulai

## Status

| Milestone | Status | Commit | Catatan |
|---|---|---|---|
| M1 — Architecture (container, DI, collections) | ✅ | `feat(M1)` | Debrief lulus (Q2 sempat gagal, sudah review) |
| M2 — Database & Eloquent | ✅ | `feat(M2)` | 27 test. Debrief lulus (Q1/Q3 perlu review → sudah dijelaskan, Q2 recheck lulus) |
| M3 — Validation, Auth & Authorization | 🔄 sedang | | |
| M4 — API Layer | ⬜ | | |
| M5 — Events, Queues, Notifications, Mail | ⬜ | | |
| M6 — Digging Deep (broadcast, cache, storage, i18n) | ⬜ | | |
| M7 — Security, Error Handling, Logging | ⬜ | | |
| M8 — Testing polish & dokumentasi | ⬜ | | |
| F1 — Vue 3 setup + Composition API basics | ⬜ | | setelah M4 |
| F2 — Pinia + composables | ⬜ | | |
| F3 — Integrasi full-stack (Sanctum SPA) | ⬜ | | |
| F4 — TypeScript migration | ⬜ | | |

Test terakhir: **27 passed** (sebelum M3).

## State sesi terakhir (M3 — baru mulai)

Eksplorasi selesai, temuan:
- `composer.json`: Sanctum **belum** install → perlu `composer require laravel/sanctum`
- `routes/` hanya `web.php` + `console.php` → API routing harus ditambah di `bootstrap/app.php` (`->withRouting(api: ...)`)
- `bootstrap/app.php`: `shouldRenderJsonWhen` sudah diset untuk `api/*`
- Base `Controller` kosong (gaya L11+) → `Gate::authorize()` manual, bukan trait `$this->authorize()`
- Rate limiter login: daftarkan `RateLimiter::for('login', ...)` di `AppServiceProvider`

Rencana M3 (belum ditulis):
1. Session auth manual: `LoginController` + FormRequest `LoginRequest` + route `login`
2. Custom rule `StockAvailable` + `BuyProductRequest` (pindah validasi buy dari M2)
3. `OrderPolicy` (ownership) + halaman `/orders/{order}` → 403 bukan owner
4. `/orders` wajib login (middleware `auth`) → update `OrdersPageTest` pakai `actingAs`
5. Sanctum: `POST /api/token` + `GET /api/user` (`auth:sanctum`)
6. Tests: 403 policy, 429 rate limit, 401/402 token, redirect guest
7. Perintah yang harus dijalankan Rizky:
   ```bash
   composer require laravel/sanctum
   php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
   php artisan migrate
   ```

## Perintah penting

```bash
php artisan serve            # cek browser: http://127.0.0.1:8000
php artisan test             # full suite — harus hijau sebelum lanjut
php artisan migrate:fresh --seed   # reset DB dev (hati-hati: data ilang)
```

URL: `/` checkout (M1), `/products` (M2), `/orders` (M2), login (M3)

## Aturan lanjut sesi

1. Baca file ini + `plan-laravel-13-lab.md`
2. `git log --oneline` untuk lihat milestone terakhir
3. `php artisan test` — pastikan hijau sebelum nambah apa pun
4. Lanjut milestone berikutnya sesuai urutan (jangan lompat)
