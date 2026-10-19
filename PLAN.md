# Rencana: Hardening ASVS + E2E Playwright T1–T8

Dua fase = dua commit terpisah (sesuai pilihan "keduanya berurutan").
Kondisi awal: semua ter-push, working tree bersih, 175 PHP + 68 Vitest + vue-tsc hijau.

---

## Fase A — Commit 1: Hardening ASVS (security headers + cache-control)

Menutup gap ❌ `ASVS_AUDIT.md`: 3.4.1 (HSTS), 3.4.3 (CSP), 3.4.4 (nosniff),
3.4.5 (Referrer-Policy), 3.4.6 (frame-ancestors), 14.3.2 (Cache-Control) +
3.4.2 (CORS ⚠️, opsional kecil). Cookie Secure / `__Host-` / HTTPS **tetap
ditunda** (SCOPE.md sudah memutuskan non-blocking — jangan dikerjakan di sini).

### 1. Middleware baru: `app/Http/Middleware/AddSecurityHeaders.php`

Registrasi **global** di `bootstrap/app.php`:
`$middleware->append(AddSecurityHeaders::class)` → kena web, api, dan panel
Filament (`/admin`) sekaligus.

Header yang diset pada SEMUA response Laravel:

| Header | Nilai | Catatan |
|---|---|---|
| `X-Content-Type-Options` | `nosniff` | selalu aman |
| `X-Frame-Options` | `DENY` | anti-clickjacking (invoice buka tab baru, bukan iframe) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | **selalu dikirim** — browser mengabaikan atas HTTP, jadi aman untuk dev & mudah dites |
| `Cache-Control` | `no-store, private` | semua response dinamis (static file dilayani langsung tanpa middleware) |
| `Content-Security-Policy` | lihat di bawah | keputusan krusial |

### 2. CSP — strategi "aman dulu, jangan risaukan Filament"

**Bukti risiko:** `vendor/filament/.../components/layout/base.blade.php` berisi
banyak inline `<script>` (theme/Echo) + inline `<style>`; grep `nonce`/`CSP`
di `vendor/filament` dan `vendor/livewire` = **nol**. CSP ketat akan mematahkan
`/admin`.

**Directive yang dipakai (tidak menyentuh eksekusi script):**
```
frame-ancestors 'none'; object-src 'none'; base-uri 'self'; form-action 'self'
```
- `frame-ancestors` + `X-Frame-Options` → tutup 3.4.6
- `object-src 'none'` → blokir injeksi plugin
- `base-uri 'self'` → blokir base-tag hijack
- `form-action 'self'` → semua form (SPA via proxy, blade, Filament) same-origin
- **`script-src`/`style-src`/`default-src` TIDAK diset** di versi ini → Filament
  100% aman. ASVS 3.4.3 ditandai **⚠️ partial** dengan alasan eksplisit:
  script-src penuh butuh nonce, Filament v4 belum mendukung nonce.

**Enkripsi opsional (hanya kalau e2e admin lulus):** coba tambah
`script-src 'self' 'unsafe-inline' 'unsafe-eval'` (Alpine/Livewire mungkin
butuh eval) — nilainya memblokir script remote/CDN. Verifikasi via spec admin
Fase B + cek manual `/admin`. **Kalau `/admin` rusak → batalkan baris ini,
kembali ke CSP tanpa script-src** (fallback yang sudah aman).

*Alternatif `config/cors.php` (3.4.2):* publish dengan `allowed_origins`
dipersempit (SPA lewat proxy = same-origin, tak ada CORS cross-origin) —
satu file + 1 assert. Sertakan, murah.

### 3. Test

**Backend — `tests/Feature/SecurityHeadersTest.php` (baru):**
- tiap header ada di response web (`/login`), api (`/api/v1/products`), admin (`/admin/login`)
- `Cache-Control: no-store` pada endpoint auth + order
- CSP mengandung `frame-ancestors 'none'`
- HSTS ada; nosniff; X-Frame-Options `DENY`; Referrer-Policy

**Frontend — `frontend/e2e/security-headers.spec.ts` (baru):**
- `page.request.get('http://localhost:8000/login')` + API → assert header
  (headers tak terlihat dari halaman Vite di :5173 — Laravel tak melayani HTML
  SPA di dev, jadi target `localhost:8000` langsung)

### 4. Docs

- `ASVS_AUDIT.md`: flip status 3.4.1/3.4.4/3.4.5/3.4.6/14.3.2 → ✅, 3.4.3 → ⚠️
  (alasan nonce Filament), 3.4.2 → ✅ kalau cors ikut; prioritas daftar atas
  diperbarui.
- `SCOPE.md`: hardening tahap akhir (headers + cache-control) → selesai; sisanya
  (cookie Secure, HTTPS, token storage, dll) tetap catatan non-blocking.
- `FEATURES.md`: section baru cara tes manual (`curl -I http://localhost:8000/login`).
- `PROGRESS.md`: baris ringkas hardening.

---

## Fase B — Commit 2: E2E Playwright untuk alur T1–T8

Spec ada hari ini: `auth`, `orders`, `products` (6 test). Yang belum: register,
password reset, cart reload, cancel order, admin panel, refund.

### 1. Constraint rate limit (akar masalah 4 e2e pernah gagal — ISSUES.md)

Hitungan login `POST /login` (limiter 5/menit, per email+ip):
- Spec sekarang: auth 2 + products 2 + orders 1 = **5 (persis di batas)** →
  nambah spec login baru = pasti 429.

**Solusi: proyek Playwright terpisah (pola `storageState`):**
1. **Project `setup`** (`e2e/setup/auth.setup.ts`): login sekali sebagai
   `test@example.com` → simpan `playwright-auth.json` (gitignore).
2. **Project `anon`** — tanpa storageState, jalankan: `auth.spec.ts` (memang
   butuh login/logout asli) + spec baru guest-only (`register`, `password-reset`).
3. **Project `app`** — `dependencies: ['setup']`, `storageState` aktif,
   jalankan: `orders`, `products`, `cart`, `cancel-order`, `admin`, dan
   `security-headers`… → **hapus panggilan `login(page)`** dari spec ini
   (sudah terautentikasi).

Total `POST /login` per run: setup 1 + auth 2 = **3 ≤ 5** ✓.
Register: spec register 1 + password-reset 1 = **2 ≤ 3/IP** ✓.
Checkout: orders 1 + cancel 1 = **2 ≤ 10/user** ✓.
Catatan docs: rerun cepat bisa kena limiter → `php artisan cache:clear`
(menghapus hitungan rate limiter).

### 2. Seeder + SeederTest

- Tambah `admin@example.com` (`User::factory()->admin()`) → untuk spec admin.
- Buat 1 dari 5 order berstatus `paid` (+ `transaction_id`, `paid_at`) → untuk
  spec refund. (Catatan: stok tidak ikut terpotong — data test; invariant
  `SUM(movements)==stock` tetap terpenuhi karena stok juga tak berubah.)
- Perbarui `tests/Feature/SeederTest.php`: assert admin ada + ≥1 order paid.
  (Kontrak `Product::count()==12`, `Order::count()==5` tetap.)

### 3. Spec baru (semua ikut gaya/spec yang ada: `data-testid`, Bahasa default `id`)

| Spec | Proyek | Alur + harapan |
|---|---|---|
| `register.spec.ts` (T1) | anon | `/register` → isi form → auto-login → nav tampil nama; email unik (`Date.now()`) |
| `password-reset.spec.ts` (T1) | anon | register user sendiri → `/forgot-password` → **baca `storage/logs/laravel.log`** dari Node (slice dari offset, regex URL reset) → buka → set password baru → auto-login. Log-driver = link emang di log |
| `cart.spec.ts` (T3) | app | add item → **reload** → cart tetap (server-side) → ubah qty → hapus |
| `cancel-order.spec.ts` (T8) | app | checkout → detail (pending — **Playwright tak menjalankan queue worker** sehingga order tetap pending, sempurna untuk tes ini) → klik `cancel-order` → status `cancelled` + tombol hilang |
| `admin.spec.ts` (T5+T6) | app | buka `http://localhost:8000/admin` (host `localhost` — patuhi pelajaran ISSUES #8) → login admin → dashboard → `/admin/orders` → aksi **Refund** order paid → status `refunded`; ditambah 1 tes: user non-admin → 403 |

T4 (webhook = server-to-server) dan T7 (realtime no-op di dev) sengaja
**tidak** di-e2e — sudah tercakup 11 + 6 test unit/feature.

### 4. Konfigurasi `playwright.config.ts`

- Tiga project (setup/anon/app) dengan `testMatch` yang memisahkan spec.
- Ganti health-check `http://127.0.0.1:8000/login` → `http://localhost:8000/login`
  (konsistensi hostname, ISSUES #8; probe tidak lewat browser tapi seragamkan).
- `frontend/.gitignore`: tambah `playwright-auth.json` (state hasil setup).

### 5. Docs

- `FEATURES.md`: section cara jalankan e2e (`migrate:fresh --seed` →
  `cache:clear` → `npm run test:e2e`) + peta cakupan T1–T8.
- `PROGRESS.md`: baris e2e T1–T8.

---

## Verifikasi (sebelum tiap commit)

```bash
php artisan test                      # backend hijau (175 + SecurityHeadersTest)
cd frontend && npm test               # vitest hijau (tak berubah kecuali perlu)
npx --prefix frontend vue-tsc --noEmit -p frontend   # bersih
cd frontend && npm run lint           # eslint (e2e ikut ter-lint)
cd frontend && npm run test:e2e       # prasyarat: migrate:fresh --seed + cache:clear
```
Manual Fase A: `curl -I http://localhost:8000/login` (semua header) + buka
`http://localhost:8000/admin` masih render normal (regresi CSP).

## Risiko & mitigasi

1. **CSP merusak Filament** → CSP versi awal tanpa `script-src` (nol risiko);
   penambahan `script-src` hanya setelah spec admin lulus; fallback = batalkan.
2. **429 rate limit saat e2e** → arsitektur 3-project storageState (hitungan di
   atas); dokumentasi `cache:clear` untuk rerun.
3. **Password-reset spec rapuh (parsing log)** → baca log dari offset awal
   per test; spec terisolasi sendiri sehingga kegagalannya tak menular.
4. **Parallel vs shared DB** → register pakai email unik; admin/cancel bekerja
   pada order masing-masing; `fullyParallel` dipertahankan (aman untuk alur ini).
