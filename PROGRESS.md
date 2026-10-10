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
| M7 — Security, Error Handling, Logging | ✅ | `feat(M7)` | 90 test: signed URL invoice, encrypted phone, custom 404/403 JSON, JSON log + request_id context, AUDIT.md |
| M8 — Testing polish & dokumentasi | ✅ | `feat(M8)` | 91 test: SeederTest, README portofolio |
| F1 — Vue 3 setup + Composition API basics | ✅ | `feat(F1)` | 11 test Vitest: router+guard, api wrapper (Bearer), ref/computed/onMounted, props/emit, search filter, navigasi detail |
| F2 — Pinia + composables | ⬜ | | |
| F3 — Integrasi full-stack (Sanctum SPA) | ⬜ | | |
| F4 — TypeScript migration | ⬜ | | |

Test terakhir: **91 passed (PHP) + 11 passed (Vitest)** — F1 selesai, commit `feat(F1)`.
Backend M1-M8 lengkap; fase Vue mulai F1.

Run test frontend: `cd frontend && npm test`. Dev: 2 server (`php artisan serve` +
`npm run dev` di `frontend/`, buka http://localhost:5173).

## Pertanyaan tertunda

Jawab setelah seluruh implementasi selesai (M8 + fase F):

- "Bagaimana best practice try catch block / error handling dan logging pada sebuah endpoint?"
- Saat menjalankan test unit/feature: apakah semua data di-mock, atau jalan di database
  transaction, atau jalan di database khusus testing? (bahas mekanisme RefreshDatabase
  + driver di phpunit.xml di project ini)
- Ingatkan Rizky untuk cek ulang docs Laravel (laravel.com/docs) — cari hal-hal yang
  mungkin belum terimplementasi di project ini (mis. fitur yang belum tersentuh milestone).
- Ingatkan untuk membuatkan docs mengenai fitur-fitur yang ada di project ini
  (penjelasan per fitur: cara kerja, cara pakai, lokasi kode — dari isi PROGRESS,
  AUDIT, dan README yang sudah ada).
- Ingatkan untuk push ke repo baru (repository GitHub terpisah untuk project ini).
- Ingatkan untuk membuatkan GitHub Actions (CI: jalankan `php artisan test` +
  `npm test` di setiap push).

## Rencana M8 — Testing polish & dokumentasi (milestone backend terakhir)

- Review semua test: `RefreshDatabase` konsisten, 1 test pakai seed
- Pastikan tidak ada test rapuh (dependensi urutan/waktu)
- Tulis `README.md` project: setup, struktur, fitur per milestone → portofolio ✅
- ~~1 LinkedIn post ringkas~~ — dibatalkan (tidak diperlukan)
- Setelah M8: lanjut fase F1 (Vue 3). **Pertanyaan tertunda dijawab setelah seluruh
  implementasi selesai (M8 + fase F)** — jangan dijawab dulu di sini.

## Perintah penting

```bash
# Server & test
php artisan serve                    # cek browser: http://127.0.0.1:8000
php artisan test                     # full suite — harus hijau sebelum lanjut

# Database
php artisan migrate                  # jalankan migrasi baru saja
php artisan migrate:fresh --seed     # reset DB dev (hati-hati: data ilang)

# Queue & scheduler (M5) — terminal terpisah saat dev
php artisan queue:work               # proses ProcessPayment + notification
php artisan queue:failed             # lihat job yang gagal total
php artisan queue:retry all          # retry semua job gagal
php artisan schedule:work            # aktifkan scheduler harian (orders:cleanup)
php artisan orders:cleanup --hours=24  # jalankan cleanup manual

# Debug & inspect
php artisan tinker                   # REPL interaktif
php artisan tinker --execute='dd(App\Models\Product::count())'   # one-liner
tail -f storage/logs/laravel.log     # log JSON (channel=json, ada request_id)
php artisan route:list               # semua route + middleware

# Git
git log --oneline                    # riwayat milestone
```

URL: `/` checkout (M1), `/products` (M2), `/orders` login dulu (M3),
`/api/v1/*` (M4, butuh token), `?lang=id` (M6), link invoice signed (M7).

## Aturan lanjut sesi

1. Baca file ini + `plan-laravel-13-lab.md`
2. `git log --oneline` untuk lihat milestone terakhir
3. `php artisan test` — pastikan hijau sebelum nambah apa pun
4. Lanjut milestone berikutnya sesuai urutan (jangan lompat)
