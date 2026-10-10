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
| M4 — API Layer | ✅ | `feat(M4)` | 60 test: resources, offset+cursor pagination, checkout transaksional + rollback, payment driver config |
| M5 — Events, Queues, Notifications, Mail | ⬜ | | lanjutan berikutnya |
| M6 — Digging Deep (broadcast, cache, storage, i18n) | ⬜ | | |
| M7 — Security, Error Handling, Logging | ⬜ | | |
| M8 — Testing polish & dokumentasi | ⬜ | | |
| F1 — Vue 3 setup + Composition API basics | ⬜ | | setelah M4 |
| F2 — Pinia + composables | ⬜ | | |
| F3 — Integrasi full-stack (Sanctum SPA) | ⬜ | | |
| F4 — TypeScript migration | ⬜ | | |

Test terakhir: **60 passed** (M4 selesai, commit `feat(M4)`).

## Rencana M5 — Events, Queues, Notifications, Mail (belum dimulai)

Gap utama — belum pernah dipakai semua:
- **Events/Listeners** event discovery: `OrderPlaced` → `ReserveStock` (opsional, stok sudah
  di-lock M4), `SendOrderConfirmation`. Catatan: charge di CheckoutController M4 masih
  sync — pindahkan ke queue job `ProcessPayment` (retry, backoff, `failed()` hook)
- **Idempotency**: flag `paid_at`/`transaction_id` di order — retry gak double-charge
- **Notifications**: multi-channel — mail + database notification (tabel bawaan)
- **Mail**: Mailable + markdown + attachment invoice sederhana
- **Scheduler**: `routes/console.php` — cleanup abandoned order/carts + Artisan command custom
- **Test**: `Queue::fake()`, `Event::fake()`, `Notification::fake()`, `Mail::fake()`
- Queue driver: **database** (tabel `jobs` sudah ada dari migrasi bawaan)

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
