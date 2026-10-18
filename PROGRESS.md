# PROGRESS — Laravel 13 Lab

Overview & log progres belajar Laravel 13 + Vue 3. Rencana lengkap di:
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
| F2 — Pinia + composables | ✅ | `feat(F2)` | 25 test Vitest: cart store (add/qty/getter, watch persist localStorage, corrupted safe), useProducts/useApi composables, CartView |
| F3 — Integrasi full-stack (Sanctum SPA) | ✅ | `feat(F3)` | 35 test Vitest + 94 PHP: cookie session SPA (csrf-cookie/XSRF), auth store, async guard, checkout cart→API, order history |
| F4 — TypeScript migration | ✅ | `feat(F4)` | Semua src .ts/.vue lang=ts: types.ts kontrak API, generik useApi<T>, type-based props/emits; vue-tsc --noEmit bersih |
| T1 — Register + password reset + email verification | ✅ | `feat(auth)` | +23 PHP: /register auto-login, forgot/reset (token hashed, expiry 60m, generik anti-enumeration), verifikasi email signed+idempotent; 4 view SPA |
| T2 — Status machine + inventory movement | ✅ | `feat(domain)` | +17 PHP: OrderStatus::canTransitionTo + Order::transitionTo/cancel; inventory_movements (in/out/adjust, signed delta, stock_after), InventoryService satu-satunya jalan ubah stok, invariant SUM==stock |
| T3 — Keranjang server-side + rate limit checkout | ✅ | `feat(cart)` | +10 PHP: cart_items per user, server otoritatif clamp, checkout baca cart DB + clear dalam transaksi, throttle checkout 10/mnt |
| T4 — Payment gateway Midtrans | ✅ | `feat(payment+admin)` | +9 PHP: MidtransPaymentGateway (Snap Http::fake + verifySignature sha512), webhook publik diverifikasi signature → transisi paid, idempoten |
| T5 — Refund | ✅ | `feat(payment+admin)` | +8 PHP: status Refunded (paid→refunded), RefundablePaymentGateway (ISP), RefundService uang-dulu-status-sesudah |
| T6 — Filament admin (v4) | ✅ | `feat(payment+admin)` | +5 PHP: panel /admin hanya is_admin, resource Order (aksi via machine)/Product (stok read-only)/User (toggle admin) |
| T7 — Stok realtime | ✅ | `feat(payment+admin)` | +6 Vitest: realtime.ts subscribe StockUpdated, graceful no-op tanpa broadcaster, cart.applyStockUpdate clamp qty |
| T8 — Cancel order customer | ✅ | `feat(payment+admin)` | +6 PHP +2 Vitest: POST /orders/{id}/cancel + policy (pemilik+pending), tombol SPA, restock berjejak |

Test terakhir: **172 passed (PHP) + 68 passed (Vitest) + `vue-tsc --noEmit` bersih**.
**Seluruh implementasi selesai (M1-M8 + F1-F4 + scope T1-T8)** — commit `feat(F4)` + `089f0e5`.
Scope e-commerce production grade (SCOPE.md) lulus penuh; deviasi tercatat di SCOPE.md.
Selanjutnya: kerjakan item di "Pertanyaan tertunda" (jawaban + reminder).

Run test frontend: `cd frontend && npm test`. Dev: 2 server (`php artisan serve` +
`npm run dev` di `frontend/`, buka http://localhost:5173).

## Pertanyaan tertunda

Jawab setelah seluruh implementasi selesai (M8 + fase F):

> Semua debrief M1–F3 sudah terkumpul di **`DEBRIEF.md`** — baca ulang sebagai
> bahan persiapan interview. Dua pertanyaan pertama (try/catch+logging, isolasi
> test, rasio unit vs feature) **sudah terjawab** di bagian "Pertanyaan Tertunda
> (Terjawab)" di `DEBRIEF.md` — tinggal reminder aksi di bawah.

## Reminder aksi — sudah dikerjakan
- ~~Ingatkan Rizky untuk cek ulang docs Laravel~~ — SELESAI (2026-10-11).
  Sudah dipakai: Eloquent (casts incl. encrypted, events, factories, relations,
  scopes, accessor), Query Builder, Collections, Container/DI, Middleware
  custom, Form Requests, API Resources (when/whenLoaded), Policies+Gate,
  Events+Listeners, Queues, Notifications, Scheduler, Storage privat, custom
  validation rule, Rate limiting, Localization, Signed URL, Sanctum
  (token+SPA), Broadcasting, Http client, Cache, structured logging,
  cursor + offset pagination. Belum dipakai & layak dicoba di project ini:
  1. Strict mode Eloquent (`Model::preventLazyLoading()`) — cegah N+1 otomatis
  2. Laravel Scout — search server-side (menutup catatan pencarian client-side)
  3. Pennant — feature flag untuk rollout fitur production-grade
  4. Precognition — validasi form SPA dari validator Laravel
  5. Blade components (`<x-…>`) — merapikan sisa blade (login/invoice/show)
  6. Markdown mailable — email konfirmasi order lebih rapi
  7. Job batching (`Bus::batch`) + Horizon — dashboard queue
  8. Sanctum token abilities — otorisasi token granular
  VERIFIKASI terhadap laravel.com/docs/13.x (2026-10-11, katalog ~90 section):
  8 item di atas valid. Tambahan yang KETEMU dari dokumen asli (belum masuk
  daftar awal — sebelumnya mengandalkan ingatan):
  9. **Passwords** (reset password) & **Verification** (email verified) —
     fitur production-grade yang belum ada sama sekali
  10. **Context** — baggage per-request (padanan terstruktur request_id kita)
  11. **Concurrency** — jalankan proses paralel
  12. **Images** — manipulasi gambar (kita cuma simpan, belum resize/thumbnail)
  13. **Pulse / Telescope** — observability & monitoring (relevan ke ELK nanti)
  14. **Socialite / Fortify / Passport** — alternatif auth (Sanctum sudah pas)
  15. **Sail / Octane / Reverb** — infra & performa (Sail relevan ke fase Docker)
  16. **Boost / AI / MCP** — tooling AI agent resmi Laravel — cocok dengan
      roadmap poin 7 (AI Engineering)
  17. Ada section **"search"** terpisah dari Scout di docs 13.x — cek isinya
      saat mengerjakan search server-side
- ~~Ingatkan untuk membuatkan docs mengenai fitur-fitur~~ — SELESAI: **`FEATURES.md`**
  (per fitur: cara kerja, lokasi kode, langkah tes manual + expected — sekaligus
  checklist tes manual E2E section A–F).
- ~~Ingatkan untuk push ke repo baru~~ — SELESAI: https://github.com/rizkytm/laravel-13-vue3-lab (public)
- ~~Ingatkan untuk membuatkan GitHub Actions (CI)~~ — SELESAI: `.github/workflows/ci.yml`
  (path-filtered backend: pint + 92 PHP test + composer audit; frontend: eslint +
  vue-tsc + 44 Vitest + npm audit critical) + `.github/dependabot.yml` (weekly)
- ~~Ingatkan untuk menjalankan `npm audit fix` (root & `frontend/`)~~ — SELESAI:
  `npm audit` root + frontend = 0 vulnerabilities, `composer audit` = bersih,
  gak ada yang perlu difix. CI jaga otomatis tiap push.
- ~~Ingatkan untuk gunakan Tailwind CSS untuk styling frontend~~ — SELESAI:
  Tailwind v4 (Vite plugin), token di `src/style.css` (`@theme` OKLCH: warm
  neutrals + accent terracotta), system font + mono buat ID, semua view restyle.
- ~~Ingatkan untuk **menghapus halaman web blade yang tidak perlu**~~ —
  SELESAI (2026-10-11): dihapus — checkout blade + web CheckoutController,
  `products/index`, `orders/index`, `orders/show`, `welcome`, `ExampleTest`,
  `CheckoutTest`. Disisakan demo fitur framework: `auth/login` (session auth,
  dipakai SPA), `products/show` (demo beli lockForUpdate + upload disk
  privat), `orders/invoice` (signed URL + Carbon locale + encrypted phone).
  Cache demo pindah ke API products (cache-versioning) — regression
  `serializable_classes` tetap terjaga. Test: 92 PHP (dari 98) semua hijau —
  LocaleTest/OrdersPageTest/OrderPolicyTest/SpaAuthTest/SignedUrlTest/
  EncryptedFieldTest/StructuredLogTest/SeederTest disesuaikan.
- ~~Ingatkan untuk implementasi **link invoice signed di Vue 3**~~ — SELESAI
  (2026-10-11): `OrderDetailView.vue` (`/orders/:id`) + `invoice_url` di
  `OrderResource` (temporarySignedRoute 30 menit) + link dari tabel Pesanan.
  Test: API (signed link tanpa auth, signature utak-atik → 403), Vue (render
  items + link, 403, tanpa invoice_url), Playwright (checkout → detail → invoice).
- ~~Ingatkan untuk buat **halaman order detail** di SPA~~ — SELESAI: lihat di atas.
- ~~Ingatkan untuk mempelajari best practice dari repo larrykonn~~ — SELESAI
  (2026-10-11, studi clone lokal `~/Documents/playground/larrykonn` =
  Laravel React starter kit + AI). Gap fitur yang DIPAKAI di sana tapi BELUM
  dicoba di project kita:
  1. **Fortify** — scaffolding auth: registrasi, reset password, **2FA**
     (nutup gap Passwords/Verification di gap check docs)
  2. **laravel/ai + pattern Agents** (`app/Agents/ResearchAgent`, `AnalysisAgent`
     + Jobs) — bangun fitur AI di Laravel (roadmap poin 7)
  3. **Wayfinder** — typed routes frontend dari route Laravel
  4. **Reverb + `routes/channels.php`** — websocket server sendiri + channel
     auth (kita pakai Pusher driver)
  5. **Boost (MCP)** — tooling AI agent resmi (AGENTS.md auto-generated)
  6. **Pail** — live log viewer (`php artisan pail`)
  7. **Sail** — dev environment Docker (masuk fase Docker kita)
  8. **S3 flysystem** — storage cloud (kita masih local disk privat)
  9. **Resend** — mail transactional provider
  10. **Actions pattern** (`app/Actions/*`) + trait validasi bersama
      (`app/Concerns/PasswordValidationRules`)
  11. **Settings pattern** — controller+FormRequest khusus (profile, password,
      2FA) sebagai section terpisah
  12. **Bulk endpoint** (`BulkStoreResearchItemRequest`) — insert massal
  13. **HandleAppearance middleware** — persist tema (dark mode) di session
  14. **Solo** (`solo.yml`) — process runner (bandingan dengan Makefile kita)
  15. **Pest 4** — style testing berbeda (kita sudah Pest 5 — beda versi/style)
  16. **Custom stubs** (`stubs/`) — kustomisasi artisan generator
  Pola menarik (bukan fitur): AGENTS.md berisi konvensi wajib untuk AI agent,
  "verification scripts dilarang kalau test sudah mencakup".
- ~~Ingatkan untuk implementasi **pagination & sorting untuk produk**~~ —
  SELESAI (2026-10-11): API `?sort=name|price|created_at&dir=` (whitelist,
  tie-breaker id) + SPA sort select + pagination footer (Prev/Next, "Halaman
  X dari Y"). Deviasi dari rencana: offset pagination (bukan cursor) — nomor
  halaman bisa di-share & API sudah pakai `paginate()`. Catatan: pencarian
  masih client-side per halaman.
- ~~Ingatkan untuk implementasi **i18n di Vue 3**~~ — SELESAI (2026-10-11):
  vue-i18n (composition mode) di `src/i18n/index.ts`, locale id/en, switcher
  ID/EN di header (persist `localStorage`), semua view pakai `t()`. Default
  'id' → test lama tak berubah. +3 test i18n.
- ~~Ingatkan untuk mengetes seluruh fitur secara manual end-to-end~~ —
  SELESAI: checklist `FEATURES.md` A–F lulus semua (2026-10-11). Temuan bug
  terdokumentasi di `ISSUES.md` (6 isu, semuanya sudah diperbaiki).
  Langkah berikut: **Playwright** — bug dari ISSUES.md jadi test pertama.
- E2E automation (keputusan): **Playwright**, bukan Cypress (multi-tab, auto-wait,
  standar industri). ~~Pasang SETELAH manual pass~~ — **SELESAI (2026-10-11)**:
  `frontend/playwright.config.ts` (webServer array: artisan serve + vite,
  reuse saat dev), 5 test regresi pertama dari bug ISSUES.md (`e2e/auth.spec.ts`,
  `e2e/products.spec.ts`) — semua hijau. Jalankan: `npm run test:e2e` (precondition
  DB ter-seed). Tambah test baru dari bug berikutnya, jangan dari skenario sempurna.
- ~~Ingatkan untuk fix warning yang muncul di `npm run dev`~~ — SELESAI:
  bersih (eslint --fix 62 warning style tadi).
- ~~Ingatkan untuk **audit project berdasarkan OWASP ASVS**~~ — SELESAI
  (2026-10-11): **`ASVS_AUDIT.md`** — ASVS 5.0, cakupan L1+L2 (246 requirement,
  V1-V16; V10 N/A tanpa OAuth). Hasil: ~120 ✅ / ~70 ⚠️ / ~25 ❌ / ~31 N/A.
  Gap prioritas: security headers (HSTS/CSP/nosniff/dll), cookie Secure +
  `__Host-`, HTTPS produksi, fitur password (ubah/reset/MFA), rate limit
  checkout, log auth, antivirus upload, token di localStorage, cache-control.

## Reminder aksi — belum dikerjakan

- ~~Ingatkan untuk **melengkapi fitur e-commerce production grade**~~ —
  **SELESAI (2026-10-18)**: seluruh scope `SCOPE.md` lulus (T1–T8, lihat
  tabel Status + deviasi tercatat di SCOPE.md). Gap ASVS terkait (fitur
  password reset/verification, rate limit checkout) ikut tertutup; sisanya
  (security headers, cache-control, cookie Secure, HTTPS, token storage,
  log auth, antivirus upload) tetap catatan hardening produksi.
- ~~Ingatkan tentang **fitur register**~~ — SELESAI (2026-10-11): diputuskan
  **masuk scope** — lihat `SCOPE.md` P1 butir 1.
- ~~Ingatkan untuk mengecek apakah bisa implement Laravel Filament~~ —
  SELESAI (2026-10-18): pakai **Filament 4** (v3 tak kompatibel Laravel 13),
  panel /admin + resource Order/Product/User aktif.

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

URL web :8000: `/login` (M3), `/register` + `/forgot-password` + `/reset-password`
(T1), `/products/{slug}` (M2 demo beli + upload), `/orders/{id}/invoice` (M7,
butuh signature valid), `/admin` (T6, Filament, hanya is_admin).
SPA :5173: `/`, `/products`, `/cart`, `/orders`, `/orders/:id`, `/verify-email`
(F1–F4 + T1/T8). API `/api/v1/*` (M4, butuh token; cart + checkout T3,
`/orders/{id}/cancel` T8) + `/api/payments/midtrans/webhook` (T4, publik),
`?lang=id` (M6).

## Aturan lanjut sesi

1. Baca file ini + `plan-laravel-13-lab.md`
2. `git log --oneline` untuk lihat milestone terakhir
3. `php artisan test` — pastikan hijau sebelum nambah apa pun
4. Lanjut milestone berikutnya sesuai urutan (jangan lompat)
