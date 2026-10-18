# FEATURES — Panduan Fitur & Tes Manual End-to-End

Setiap fitur: **cara kerja → lokasi kode → langkah tes manual → hasil yang diharapkan.
Centang [ ] saat lulus.** Bug = catat, jangan dibetulkan diam-diam (nanti jadi test
Playwright/regression pertama).

---

## Prasyarat (sekali jalan)

```bash
# Terminal 1 — API + web
php artisan migrate:fresh --seed     # reset + isi data demo
php artisan serve                    # http://127.0.0.1:8000

# Terminal 2 — queue worker (fitur 4 & 5)
php artisan queue:work

# Terminal 3 — SPA
cd frontend && npm run dev           # http://localhost:5173
```

Akun demo: `test@example.com` / `password`.
Buka log terpisah biar kelihatan efeknya: `tail -f storage/logs/laravel.log`.

---

## A. SPA Vue (frontend di :5173)

### A1. Login session (Sanctum SPA cookie)
- **Kode:** `frontend/src/stores/auth.ts`, `src/api.ts` (`spaLogin`), route `POST /login`
- **Langkah:** buka `http://localhost:5173` → kena redirect `/login` → isi form → Masuk.
  Reload halaman → tetap login. Logout → balik ke login.
- **Harapkan:** nav tampil nama user + Keranjang/Pesanan; reload gak minta login ulang
  (session cookie). Login salah → `Email atau password salah.`

### A2. Guard route async
- **Kode:** `frontend/src/router/index.ts` (`beforeEach`)
- **Langkah:** (tanpa login) langsung ketik `http://localhost:5173/orders` →
  juga ketik `http://localhost:5173/cart?redirect=x` dari guest.
- **Harapkan:** selalu mendarat di `/login`; setelah login → kembali ke halaman
  yang diminta awal.

### A3. Browse + search + detail
- **Kode:** `views/ProductListView.vue`, `components/ProductCard.vue`,
  composable `useProducts.ts`, API `GET /api/v1/products`
- **Langkah:** Produk → lihat daftar → ketik di kolom cari → klik 1 produk.
  Coba cari `zzz`. Klik tombol Kembali.
- **Harapkan:** daftar dari API (bukan statis), search filter real-time di client,
  `zzz` → `Tidak ada produk cocok.`, detail menampilkan harga/stok/opsi.

### A4. Cart (localStorage, Pinia)
- **Kode:** `stores/cart.ts` (watch persist), `views/CartView.vue`
- **Langkah:** detail produk → Tambah ke keranjang → badge nav `(1) Rp …` naik →
  buka Keranjang → ubah qty → Hapus 1 item → reload halaman.
- **Harapkan:** badge nav sinkron tanpa refresh; **cart bertahan setelah reload**
  (localStorage); qty < 1 → item hilang; `Kosongkan` → tabel kosong.

### A5. Checkout full-stack → queue → status paid
- **Kode:** `views/CartView.vue` → `POST /api/v1/checkout` →
  `app/Http/Controllers/Api/CheckoutController.php` (transaksi + stok
  `lockForUpdate`) → job `app/Jobs/ProcessPayment.php`
- **Langkah:** isi cart → Checkout → otomatis pindah ke Pesanan →
  amati baris order, **jangan** buka apa pun, tunggu ±2 detik (worker jalan)
  → klik `Muat ulang`.
- **Harapkan:** order muncul `pending` → setelah worker jadi **`paid`**
  (hijau) + kolom Transaction terisi `txn_…`. Tanpa `queue:work` terminal →
  status tetap `pending` (itu normal, bukan bug).
- **Bug-hunt:** beli product sampai stok 0 → checkout lagi qty lebih besar dari
  stok → harus muncul error validasi merah (`422`), order GAK dibuat,
  stok DB tidak berubah (cek tinker).

### A6. Order history + detail
- **Kode:** `Api/OrderController` (SPA), `OrderResource` (`invoice_url`),
  policy `app/Policies/OrderPolicy.php`
- **Langkah (SPA):** Pesanan → tabel berisi semua order user login → klik 1
  order → detail + link invoice.
- **Langkah (API):** pakai token user lain → `GET /api/v1/orders/{id}` milik
  user pertama (ganti id di URL).
- **Harapkan:** SPA hanya menampilkan milik sendiri (API filter user);
  order asing → **403** JSON `message` custom.

---

## B. Web blade (:8000 — fitur framework yang gak kelihatan di SPA)

### B1. Container singleton + DI (M1)
- **Kode:** `app/Providers/AppServiceProvider.php` (binding), `Services/Payment/`,
  `Api/CheckoutController.php` (pemakai binding saat checkout)
- **Langkah:** `php artisan tinker`:
  `app(App\Services\Payment\PaymentGateway::class) === app(...)` (alias 2x)
  → lalu checkout 2x (A5) — lihat `transaction_id` tiap order baru.
- **Harapkan:** `true` (instance identik, per-request scope); tiap checkout =
  `txn_` baru.

### B2. Eloquent: cache + accessor + relasi (M2)
- **Kode:** `Api/ProductController@index` (`Cache::remember` 5 menit +
  cache-versioning `products.version`), `Models/Product.php` (`casts`,
  `booted`, accessor `options`).
- **Langkah:**
  1. `GET /api/v1/products` (lihat E) → catat isi.
  2. ubah nama product di tinker: `Product::first()->update(['name'=>'UBAH'])` →
     GET ulang.
- **Harapkan:** daftar berubah **langsung** → bukti invalidation (version bump
  di `booted()`) jalan. Produk dengan banyak order → `order_items_count` tanpa
  N+1 (`withCount('orderItems')`).

### B3. Beli langsung + stok lock (M2)
- **Kode:** `ProductController@buy`, `Models/Product.php` `lockForUpdate`
- **Langkah:** `/products/{slug}` → tombol buy → refresh.
  Beli sampai stok habis.
- **Harapkan:** stok berkurang konsisten (gak minus — cek tinker);
  stok 0 → tombol disabled / ditolak.

### B4. Login throttle (M3)
- **Kode:** `routes/web.php` `throttle:login`, RateLimiter di `AppServiceProvider`
- **Langkah:** `POST /login` dengan password salah **6x berturut-turut**
  (bisa curl loop).
- **Harapkan:** percobaan ke-6 → **429** Too Many Attempts. Reset ≈ 1 menit.

### B5. Upload gambar produk (M6 — disk privat)
- **Kode:** `ProductController@uploadImage/@image`, route
  `POST|GET /products/{slug}/image`, disk privat
- **Langkah (curl — POST web route butuh token CSRF):**
  ```bash
  SLUG=$(php artisan tinker --execute='echo App\Models\Product::first()->slug;')

  # 1. ambil session + token dari halaman (form upload ada di products/show)
  curl -s -c /tmp/j.txt -o /tmp/p.html "http://127.0.0.1:8000/products/$SLUG"
  TOKEN=$(grep -o 'name="_token" value="[^"]*"' /tmp/p.html | head -1 | sed 's/.*value="//;s/"//')

  # 2. upload (sertakan _token + cookie jar)
  curl -b /tmp/j.txt -F "_token=$TOKEN" -F "image=@/tmp/tiny.jpg" \
    -H 'Accept: application/json' "http://127.0.0.1:8000/products/$SLUG/image"

  # 3. download + bukti byte identik
  curl -o /tmp/out.jpg "http://127.0.0.1:8000/products/$SLUG/image"
  cmp /tmp/tiny.jpg /tmp/out.jpg && echo IDENTIK

  # 4. negatif: file bukan gambar → ditolak (422)
  echo "bukan gambar" > /tmp/fake.jpg
  curl -b /tmp/j.txt -F "_token=$TOKEN" -F "image=@/tmp/fake.jpg" \
    -H 'Accept: application/json' "http://127.0.0.1:8000/products/$SLUG/image"
  ```
- **Harapkan:** upload OK + file masuk `storage/app/private/products/` (path
  random, nama file user diabaikan); download byte identik; file non-gambar
  → `422`; tanpa token → `419` (CSRF — memang diwajibkan).

### B6. i18n ?lang=id (M6)
- **Kode:** `Http/Middleware/SetLocale.php`, `lang/id.json`
- **Langkah:** halaman apa pun tambah `?lang=id` → bandingkan copy
  (mis. pesan error validasi 422).
- **Harapkan:** pesan berbahasa Indonesia; tanpa param → balik `en`.

---

## C. Queue, event & notification (M5/M6)

### C1. OrderPlaced → ProcessPayment → mail+database notification
- **Kode:** `Events/OrderPlaced.php`, `Listeners/SendOrderConfirmation.php`,
  `Notifications/OrderConfirmation.php`, `Jobs/ProcessPayment.php`
- **Langkah:** checkout (A5) sambil `tail -f storage/logs/laravel.log`.
  Cek `php artisan tinker` → `Notification::count()` / tabel `notifications`.
- **Harapkan:** urutan event → job → notification tercatat. Mail = log driver
  (lihat `storage/logs/laravel.log` baris mail, gak ada SMTP).

### C2. Job idempotent + retry
- **Kode:** `ProcessPayment` (`transaction_id` sudah ada → skip)
- **Langkah:** `php artisan queue:work` STOP di tengah (Ctrl+C) setelah order
  paid → jalankan lagi → atau `php artisan queue:retry all`.
- **Harapkan:** bayar dua kali → `transaction_id` **tetap sama**, `paid_at`
  gak berubah (lihat debrief M1).

### C3. Scheduler cleanup (M5)
- **Kode:** `Console/Commands/CleanupStaleOrders.php`, `routes/console.php`
- **Langkah:** buat order pending lama (tinker: ubah `created_at` -30 jam) →
  `php artisan orders:cleanup --hours=24`.
- **Harapkan:** order stale → status `expired`/dihapus sesuai logika; order
  fresh aman. `php artisan schedule:list` menampilkan perintahnya.

---

## D. Keamanan & error handling (M7)

### D1. Invoice signed URL (30 menit)
- **Kode:** `OrderResource` (`invoice_url` → temporarySignedRoute 30 menit),
  route `GET /orders/{id}/invoice` middleware `signed`, view `invoice.blade`
- **Langkah:**
  1. SPA → Pesanan → klik 1 order → "Lihat invoice" → tampil ✓
  2. ambil link → hapus 1 karakter query (`signature`) → buka →
  3. buka invoice tanpa login tapi **dengan** signature valid.
- **Harapkan:** (1) tampil; (2) **403/419 invalid signature**; (3) **tetap
  tampil** — otorisasi = signature, bukan session. Lewat 30 menit → kedaluwarsa.

### D2. Format error JSON konsisten (M7)
- **Kode:** `bootstrap/app.php` `withExceptions` render callbacks
- **Langkah (curl, terutama dari origin beda / header Accept: application/json):**
  ```bash
  curl -H 'Accept: application/json' http://127.0.0.1:8000/products/tidak-ada   # 404
  curl -H 'Accept: application/json' -X POST http://127.0.0.1:8000/api/v1/checkout  # 401
  curl -H 'Accept: application/json' http://127.0.0.1:8000/orders/9999            # 403/404
  ```
- **Harapkan:** JSON `{message, …}` + status tepat (404/401/403), **bukan**
  HTML error page. Pesan 403 custom milik aplikasi.

### D3. Validasi 422 (M3/M4)
- **Langkah:** checkout API tanpa `items` / qty 0 / product tidak ada
  (curl `POST /api/v1/checkout` dengan body kosong).
- **Harapkan:** `422` + `errors` per-field (bukan 500).

### D4. Encrypted attribute (M7)
- **Kode:** `Models/User.php` cast `'phone' => 'encrypted'`
- **Langkah:** tinker → `User::first()->phone` (terbaca normal) →
  `DB::table('users')->value('phone')`.
- **Harapkan:** di DB terlihat **ciphertext** (`base64:`/blok acak), bukan
  nomor telepon plaintext.

### D5. Log JSON + request_id (M7)
- **Kode:** `config/logging.php` channel `json`, `withContext`
- **Langkah:** buat 1 request (mis. login gagal) → `tail -f storage/logs/laravel.log`.
- **Harapkan:** tiap baris JSON 1 objek; ada `request_id` yang sama antar
  baris satu request; level `info` vs `error` sesuai peristiwa. Gak ada
  password/token di log.

---

## E. API v1 (curl — kontrak buat SPA & konsumen lain)

```bash
# 1. Token
TOKEN=$(curl -s -X POST http://127.0.0.1:8000/api/token \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com","password":"password"}' | python3 -c 'import sys,json;print(json.load(sys.stdin)["token"])')

# 2. Siapa saya
curl -s -H "Authorization: Bearer $TOKEN" http://127.0.0.1:8000/api/user

# 3. Produk (public via sanctum) + pagination
curl -s -H "Authorization: Bearer $TOKEN" 'http://127.0.0.1:8000/api/v1/products?per_page=2'

# 4. Orders milik sendiri
curl -s -H "Authorization: Bearer $TOKEN" http://127.0.0.1:8000/api/v1/orders

# 5. Checkout API
curl -s -X POST http://127.0.0.1:8000/api/v1/checkout \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"items":[{"product_id":1,"quantity":1}]}'

# 6. Tanpa token → 401; cart kosong → 422 (EnsureCartNotEmpty); 404 → JSON
curl -s -H 'Accept: application/json' http://127.0.0.1:8000/api/v1/orders   # 401
```

- **Kode:** `routes/api.php`, `Http/Resources/*` (bentuk response),
  `Middleware/EnsureCartNotEmpty.php`
- **Harapkan:** semua response JSON sesuai resource (field `data`/`meta`);
  401/404/422 berformat konsisten; order hanya milik token tsb.

---

## F. Checklist bug-hunt (dari PROGRESS)

- [x] A1–A6 SPA alur utama — lulus (bugs: A3 slug binding, A4 clamp stok,
      A1 login proxy/redirect — detail di `ISSUES.md`)
- [x] B1–B6 web/framework fitur — lulus (bug: cache `serializable_classes`
      → `/products` error "property name on string"; B5 langkah CSRF diperbaiki)
- [x] C1–C3 queue & scheduler — lulus
- [x] D1–D5 keamanan & error — lulus (D1: link invoice butuh implementasi
      Vue 3 nanti — lihat PROGRESS)
- [x] E API kontrak — lulus
- [x] UI Tailwind: focus ring terlihat (Tab kemana-mana) — lulus
- [x] mobile 375px aman; status `paid` = teks + warna hijau (bukan warna
      sendirian — lulus)
- [x] `npm run dev` tanpa warning
- [x] Catat temuan → `ISSUES.md` (6 isu + fix) → bug pertama jadi kandidat
      test Playwright

> Temuan manual E2E selanjutnya: tulis di `ISSUES.md` (format sama),
> jangan dibetulkan diam-diam.
