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
| M5 — Events, Queues, Notifications, Mail | ✅ | `feat(M5)` | 68 test: OrderPlaced event, ProcessPayment job (idempotent, backoff, failed()), notification mail+database, scheduler orders:cleanup |
| M6 — Digging Deep (broadcast, cache, storage, i18n) | ✅ | `feat(M6)` | 77 test: StockUpdated broadcast (after-commit!), cache list + invalidation, upload gambar disk privat, SetLocale ?lang= |
| M7 — Security, Error Handling, Logging | ⬜ | | lanjutan berikutnya |
| M8 — Testing polish & dokumentasi | ⬜ | | |
| F1 — Vue 3 setup + Composition API basics | ⬜ | | setelah M4 |
| F2 — Pinia + composables | ⬜ | | |
| F3 — Integrasi full-stack (Sanctum SPA) | ⬜ | | |
| F4 — TypeScript migration | ⬜ | | |

Test terakhir: **77 passed** (M6 selesai, commit `feat(M6)`).

## Pertanyaan tertunda

Jawab setelah seluruh implementasi selesai (M8 + fase F):

- "Bagaimana best practice try catch block / error handling dan logging pada sebuah endpoint?"
- Ingatkan Rizky untuk cek ulang docs Laravel (laravel.com/docs) — cari hal-hal yang
  mungkin belum terimplementasi di project ini (mis. fitur yang belum tersentuh milestone).

## Rencana M7 — Security, Error Handling & Logging (belum dimulai)

- Kaitkan `checklist-code-review.md` + ASVS: mass assignment (M2 ✓), policy (M3 ✓) — audit ulang
- Encryptable casts untuk field sensitif, signed URL (guest checkout link), password hashing
- Exception handling: `withExceptions` → custom format 404/422/500 JSON (perbaiki
  `shouldRenderJsonWhen` yang sudah ada)
- **Structured logging** (JSON) + `Log::context()` correlation id per request —
  kaitan roadmap poin 2 (ELK). Catatan pertanyaan tertunda di atas gak jauh dari ini

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
