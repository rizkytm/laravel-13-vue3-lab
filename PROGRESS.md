# PROGRESS — Laravel 13 Lab

Overview & log progres belajar Laravel 13 + Vue 3. Rencana lengka di:
`~/Documents/playground/roadmap/plan-laravel-13-lab.md`

**Mode belajar:** assistant tulis semua kode + test, Rizky baca + cek browser.
Test dijalankan assistant; fungsi dicek via browser oleh Rizky. Milestone = 1 commit.
**Debrief: assistant langsung berikan pertanyaan + jawaban sekaligus** (tanpa sesi tanya-jawab).

## Tech stack

- PHP 8.5, Laravel 13 (Pest), SQLite
- Queue/cache/session: database driver (tanpa Redis)
- Mail: log driver. Broadcasting: log driver
- Frontend Vue 3 (fase F) belum dimulai

## Status

| Milestone | Status | Commit | Catatan |
|---|---|---|---|
| M1 — Architecture (container, DI, collections) | ✅ | `feat(M1)` | 9 test |
| M2 — Database & Eloquent | ✅ | `feat(M2)` | 27 test |
| M3 — Validation, Auth & Authorization | ✅ | `feat(M3)` | 41 test: FormRequest, StockAvailable rule, session auth, OrderPolicy 403, rate limit 429, Sanctum token |
| M4 — API Layer | ⬜ | | lanjutan berikutnya |
| M5 — Events, Queues, Notifications, Mail | ⬜ | | |
| M6 — Digging Deep (broadcast, cache, storage, i18n) | ⬜ | | |
| M7 — Security, Error Handling, Logging | ⬜ | | |
| M8 — Testing polish & dokumentasi | ⬜ | | |
| F1 — Vue 3 setup + Composition API basics | ⬜ | | setelah M4 |
| F2 — Pinia + composables | ⬜ | | |
| F3 — Integrasi full-stack (Sanctum SPA) | ⬜ | | |
| F4 — TypeScript migration | ⬜ | | |

Test terakhir: **41 passed** (M3 selesai, commit `feat(M3)`).

## Rencana M4 — API Layer (belum dimulai)

- API Resource + ResourceCollection untuk order/product JSON
- Pagination: cursor vs offset (bandingkan, keduanya dipakai + di-test)
- HTTP Client: simulasi payment gateway eksternal (`Http::fake()` di test) —
  hubungkan ke `PaymentGateway` interface M1: implementasi `HttpPaymentGateway`
  yang benar-benar manggil URL, binding diganti via config
- Middleware custom (`EnsureCartNotEmpty`) untuk checkout API
- `apiResource` routes + versioning `/api/v1`
- Catatan: Sanctum sudah jalan (`/api/token`, `/api/user`) — tinggal perluas

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
