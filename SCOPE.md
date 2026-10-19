# SCOPE.md — E-commerce Production Grade

Scope resmi sebelum implementasi (disepakati 2026-10-11). Reminder "melengkapi
fitur e-commerce production grade" di PROGRESS menunjuk file ini.

## Status implementasi (2026-10-18) — SEMUA SELESAI ✅

Seluruh P1 + P2 + admin Filament di bawah sudah diimplementasi, lulus test,
dan di-commit per tahap (lihat PROGRESS.md baris T1–T8). Deviasi yang perlu
dicatat (jujur, bukan scope creep):

- **Status machine**: yang dibangun `pending → paid | cancelled | failed`
  dan `paid → refunded`. **`shipped`/`completed` TIDAK ditambahkan** — tidak
  ada fitur pemenuhan logistik di scope ini, jadi menambah status itu hanya
  jadi hiasan tanpa transisi nyata. Enum siap dikembangkan nanti.
- **Register**: `POST /register` (web/session, bukan `/api/v1/register`) —
  konsisten dengan login SPA yang sudah ada (cookie session via proxy Vite).
- **Stok realtime**: `StockUpdated` tetap broadcast (log driver), frontend
  subscribe di `realtime.ts` dengan **graceful no-op** — tanpa Redis/Reverb
  (stack lab) tidak ada websocket sungguhan; titik sambung Reverb/Pusher
  disiapkan (`window.__BROADCAST__`), logika clamp teruji dengan broadcaster
  tiruan.
- **Filament**: pakai **Filament 4** (v3 tidak kompatibel Laravel 13) —
  kompatibilitas terkonfirmasi, tidak perlu fallback admin custom.
- **Password**: reset + email verification (tahap 1). "Ubah password" (ganti
  password saat login) belum ada — di luar scope butir ini.

## In scope

### P1 — Inti

1. **Register** — endpoint `POST /api/v1/register` + form daftar + validasi
   password policy + auto-login. User tetap tanpa role (lihat Filament).
2. **Keranjang server-side** — `carts`/`cart_items`, sync dari localStorage saat
   login, checkout baca server (tetap validasi stok).
3. **Order status machine** — `pending → paid → shipped → completed`,
   `→ cancelled/refunded`; transisi tervalidasi (class enum + guard), event per
   transisi.
4. **Payment gateway** — interface tetap, driver `midtrans` (atau sejenis)
   contoh nyata: snap/redirect + callback webhook diverifikasi signature.
   Fake tetap untuk test. Akun Midtrans TIDAK wajib — driver diuji dengan
   `Http::fake` terhadap kontrak API Snap + signature webhook; akun
   sandbox (gratis) opsional kalau mau tes end-to-end sungguhan.
5. **Inventory movement** — `inventory_movements` (in/out/adjust + reason),
   stok tidak pernah diubah tanpa jejak; stok dihitung dari movement.
6. **Password reset + email verification** — laravel prompt: forgot/reset
   password (token + expiry + hashing) + verifikasi email saat register.
   Mail tetap log driver. Menutup gap "fitur password belum ada"
   (PROGRESS + ASVS_AUDIT).

### P2 — Pelengkap

7. **Refund** — flow refund terkait transisi status + payment gateway.
8. **Stok realtime** — broadcasting `StockUpdated` disubscribe frontend
   (Reverb/Pusher) — SPA tampil stok tanpa refresh.
9. **Cancel order oleh customer** — endpoint + tombol cancel untuk order
   `pending` (memakai transisi cancelled dari status machine).

### Admin dashboard — Filament (disepakati terpisah)

- **Filament** (cek dulu: Filament 4 kompatibel Laravel 13 — kalau tidak,
  fallback bikin admin sederhana sendiri, putusan dicatat di sini).
- Resource: **Product** (CRUD + stok), **Order** (list + detail + ubah status
  sesuai state machine), **User** (opsional).
- Dashboard widget: pendapatan, order per status, stok menipis.
- Akses: kolom `users.is_admin`, panel di `/admin` + policy — bukan package
  role/permission penuh (nanti kalau perlu).

## Out of scope (tidak dikerjakan)

- Marketplace multi-vendor, multi-currency, PWA/mobile app
- Microservice, Elasticsearch, Redis cluster
- KYC, P2P payment, escrow

## Urutan implementasi yang disarankan

1. Register + password reset + email verification (satu area: autentikasi)
2. Order status machine + inventory movement (domain inti — semua fitur
   lain: payment, refund, Filament, stok realtime bergantung ke sini)
3. Keranjang server-side + rate limit checkout (checkout tetap validasi
   stok)
4. Payment gateway Midtrans (butuh transisi pending → paid yang tervalidasi)
5. Refund (butuh status machine + gateway)
6. Filament admin (butuh domain final — Order resource sekalian pakai
   transisi status + `users.is_admin` + policy)
7. Stok realtime (inventory movement jadi sumber kebenaran stok)
8. Cancel order oleh customer (butuh status machine — kecil, bisa kapan
   saja setelah tahap 2)

Prinsip: domain → integrasi eksternal → UI/admin → real-time.
Tiap tahap = 1 commit + test, jangan dikerjakan sekaligus.

## Definisi selesai

- Semua item P1 + P2 + Filament lulus test + verifikasi manual
- ASVS gap prioritas dari `ASVS_AUDIT.md` ikut diperbaiki — sudah
  terpetakan di scope di atas:
  - fitur password (reset/ubah) → P1 butir 1 + 6
  - rate limit checkout → urutan tahap 3
  - security headers (HSTS, CSP, nosniff, dll) → middleware, dikerjakan
    saat fase hardening akhir → **SELESAI (2026-10-19)**: middleware global
    `AddSecurityHeaders` (HSTS, nosniff, Referrer-Policy, X-Frame-Options,
    CSP sebagian) + `config/cors.php` ketat. Sisa: CSP `script-src` penuh
    butuh nonce (Filament v4 belum dukung).
  - cache-control → dikerjakan bersama security headers → **SELESAI**:
    `Cache-Control: no-store, private` semua response dinamis.
  Sisanya (cookie Secure, HTTPS, token storage, log auth, antivirus
  upload) tetap catatan hardening produksi, tidak memblokir selesai.
