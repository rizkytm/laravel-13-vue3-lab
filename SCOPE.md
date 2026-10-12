# SCOPE.md — E-commerce Production Grade

Scope resmi sebelum implementasi (disepakati 2026-10-11). Reminder "melengkapi
fitur e-commerce production grade" di PROGRESS menunjuk file ini.

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
   Fake tetap untuk test.
5. **Inventory movement** — `inventory_movements` (in/out/adjust + reason),
   stok tidak pernah diubah tanpa jejak; stok dihitung dari movement.

### P2 — Pelengkap

6. **Refund** — flow refund terkait transisi status + payment gateway.
7. **Stok realtime** — broadcasting `StockUpdated` disubscribe frontend
   (Reverb/Pusher) — SPA tampil stok tanpa refresh.

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

Register → Filament (butuh admin) → inventory movement + state machine →
keranjang server-side → payment gateway → refund → stok realtime.
Tiap tahap = 1 commit + test, jangan dikerjakan sekaligus.

## Definisi selesai

- Semua item P1 + P2 + Filament lulus test + verifikasi manual
- ASVS gap prioritas dari `ASVS_AUDIT.md` nomor 1-5, 8-9 ikut diperbaiki saat
  menyentuh area terkait (minimal: headers, rate limit checkout, cache-control)
