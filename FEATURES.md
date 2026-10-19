# FEATURES — Panduan Fitur & Tes Manual End-to-End

Setiap fitur: **cara kerja → lokasi kode → langkah tes manual → hasil yang diharapkan.
Centang [ ] saat lulus.** Bug = catat, jangan dibetulkan diam-diam (nanti jadi test
Playwright/regression pertama).

---

## Prasyarat (sekali jalan)

```bash
# Terminal 1 — API + web
php artisan migrate:fresh --seed     # reset + isi data demo
php artisan serve                    # http://localhost:8000

# Terminal 2 — queue worker (fitur 4 & 5)
php artisan queue:work

# Terminal 3 — SPA
cd frontend && npm run dev           # http://localhost:5173
```

Akun demo: `test@example.com` / `password`.
Buka log terpisah biar kelihatan efeknya: `tail -f storage/logs/laravel.log`.

> **⚠️ Pakai SATU hostname di semua URL browser: `localhost`.**
> Cookie session itu **host-only** — `localhost` dan `127.0.0.1` dianggap
> dua dunia berbeda oleh browser (lihat `ISSUES.md` #3). Contoh: login di
> `http://localhost:5173` (SPA) **tidak** terbawa ke `http://127.0.0.1:8000/admin`
> → yang terkirim justru session lama non-admin → **403**. Buka
> `http://localhost:8000/admin` supaya host-nya sama dengan SPA. `SESSION_DOMAIN`
> tak bisa menyembuhkan ini — `localhost`/`127.0.0.1` tak punya parent domain
> bersama.

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
  curl -s -c /tmp/j.txt -o /tmp/p.html "http://localhost:8000/products/$SLUG"
  TOKEN=$(grep -o 'name="_token" value="[^"]*"' /tmp/p.html | head -1 | sed 's/.*value="//;s/"//')

  # 2. upload (sertakan _token + cookie jar)
  curl -b /tmp/j.txt -F "_token=$TOKEN" -F "image=@/tmp/tiny.jpg" \
    -H 'Accept: application/json' "http://localhost:8000/products/$SLUG/image"

  # 3. download + bukti byte identik
  curl -o /tmp/out.jpg "http://localhost:8000/products/$SLUG/image"
  cmp /tmp/tiny.jpg /tmp/out.jpg && echo IDENTIK

  # 4. negatif: file bukan gambar → ditolak (422)
  echo "bukan gambar" > /tmp/fake.jpg
  curl -b /tmp/j.txt -F "_token=$TOKEN" -F "image=@/tmp/fake.jpg" \
    -H 'Accept: application/json' "http://localhost:8000/products/$SLUG/image"
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
  curl -H 'Accept: application/json' http://localhost:8000/products/tidak-ada   # 404
  curl -H 'Accept: application/json' -X POST http://localhost:8000/api/v1/checkout  # 401
  curl -H 'Accept: application/json' http://localhost:8000/orders/9999            # 403/404
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
TOKEN=$(curl -s -X POST http://localhost:8000/api/token \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com","password":"password"}' | python3 -c 'import sys,json;print(json.load(sys.stdin)["token"])')

# 2. Siapa saya
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/user

# 3. Produk (public via sanctum) + pagination
curl -s -H "Authorization: Bearer $TOKEN" 'http://localhost:8000/api/v1/products?per_page=2'

# 4. Orders milik sendiri
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/v1/orders

# 5. Checkout API
curl -s -X POST http://localhost:8000/api/v1/checkout \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"items":[{"product_id":1,"quantity":1}]}'

# 6. Tanpa token → 401; cart kosong → 422 (EnsureCartNotEmpty); 404 → JSON
curl -s -H 'Accept: application/json' http://localhost:8000/api/v1/orders   # 401
```

- **Kode:** `routes/api.php`, `Http/Resources/*` (bentuk response),
  `Middleware/EnsureCartNotEmpty.php`
- **Harapkan:** semua response JSON sesuai resource (field `data`/`meta`);
  401/404/422 berformat konsisten; order hanya milik token tsb.

> Catatan alur cart/checkout di E5: sejak tahap 3 payload checkout TIDAK
  membawa `items` — keranjang dibaca dari tabel `cart_items`. Isi cart dulu
  (A4 / `POST /api/v1/cart/items`), baru `POST /api/v1/checkout` tanpa body.

---

## G. Scope e-commerce (tahap T1–T8 — SCOPE.md)

Prasyarat tambahan (sekali jalan) untuk T5/T6:

```bash
php artisan tinker --execute='App\Models\User::factory()->admin()->create(["email"=>"admin@example.com","password"=>"password"])'
```

Mail = `log driver` — semua link verifikasi & reset muncul di log, bukan inbox:
`tail -f storage/logs/laravel.log` (grep `http://`).

### G1. Register + password reset + email verification (T1)

- **Kode:** `RegisterController`/`ForgotPasswordController`/`ResetPasswordController`/
  `VerifyEmailController`, route `POST /register` & `/forgot-password` (& throttle
  `register` 3/mnt, `password` 3/mnt), views `frontend/src/views/{Register,ForgotPassword,ResetPassword,VerifyEmail}View.vue`
- **Langkah:** buka `http://localhost:5173/register` → isi → submit. Lalu
  `→ /forgot-password` → isi email → ambil link dari log → `/reset-password?token=…`
  → set password baru. Untuk verifikasi: daftar, ambil link `/email/verify/…` dari log → buka.
- **Harapkan:**
  - Register → **auto-login** (langsung ke home, banner cek email).
  - Forgot-password → pesan generik `202` (anti user-enumeration — jangan bocorkan
    email tidak terdaftar).
  - Buka link reset → auto-login dengan password baru; **link kedua → token sudah
    dipakai** (single-use).
  - Verifikasi: buka link → `sudah terverifikasi`; buka **lagi → tetap 200** (idempoten).
  - Register 4× cepat → `429`.

### G2. Status machine + inventory movement (T2)

- **Kode:** `Enums/OrderStatus.php` (`canTransitionTo`), `Models/Order.php`
  (`transitionTo`), `Services/InventoryService.php`, `Models/Product.php` (`buy`)
- **Langkah:** buka `/products/{slug}` → lihat stok (mis. 5) → **Tambah ke keranjang
  → stok BELUM turun** (cart tidak mereservasi) → isi qty 2 → Checkout → buka lagi
  detail produk → stok turun jadi 3. Verifikasi invariant di tinker:
  `php artisan tinker --execute='foreach(App\Models\Product::all() as $p) echo $p->id.": stock={$p->stock} sum=".intval($p->movements()->sum("quantity")).PHP_EOL;'`
- **Harapkan:**
  - Stok hanya berkurang **saat checkout** (bukan saat add-to-cart).
  - Setiap perubahan stok meninggalkan baris `inventory_movements`; **SUM(quantity)
    == stock** untuk tiap produk (khususnya stok awal = gerakan `in` pertama).
  - Bila list produk menampilkan stok lama setelah checkout → **bug cache** (sudah
    diperbaiki `e5b1496` — versi cache harus ikut naik).

### G3. Keranjang server-side + rate limit checkout (T3)

- **Kode:** `Api/CartController.php`, `Api/CheckoutController.php`,
  `Middleware/EnsureCartNotEmpty.php`, migrasi `cart_items`, `stores/cart.ts`
- **Langkah:**
  1. Tambah item → klik **Tambah** 2× → di Keranjang qty = 2 (additive, bukan timpa).
  2. **Reload tab** → keranjang tetap (dari DB `cart_items`, bukan localStorage).
  3. Ubah qty → hapus item → kosongkan.
  4. Qty melebihi stok → server clamp ke stok.
  5. Checkout 11× berturut-turut dalam <1 menit.
- **Harapkan:**
  - Keranjang identik setelah reload & **hanya milik user login** (logout → cart
    kosong; login user lain → cart berbeda).
  - Clamp stok terjadi di **server** (bukan UI yang sembunyikan).
  - Checkout ke-11 → `429` (limiter `checkout` 10/menit per user).
  - Cart kosong → checkout → `422` (`EnsureCartNotEmpty`).

### G4. Payment gateway Midtrans (T4)

> **Kenapa tidak kelihatan di browser:** default `PAYMENT_DRIVER=fake` — checkout
> langsung `paid` oleh `FakePaymentGateway`, Midtrans tidak pernah dipanggil.
> T4 diuji lewat **webhook** (server-to-server) + test `Http::fake`.

- **Kode:** `Services/Payment/MidtransPaymentGateway.php` (Snap + `verifySignature`),
  `Http/Controllers/PaymentWebhookController.php`, route `POST /api/payments/midtrans/webhook`
  (publik, tanpa CSRF), `Services/Payment/AsyncPaymentGateway.php` + `Jobs/ProcessPayment.php`
- **Langkah — generate payload bertanda tangan** (ambil order `pending` terakhir):
  ```bash
  php artisan tinker --execute='
  $o = App\Models\Order::where("status", App\Enums\OrderStatus::Pending)->orderByDesc("id")->first();
  if (! $o) { echo "TIDAK ADA ORDER PENDING — checkout dulu lewat SPA."; exit(1); }
  $sid = "200"; $ga = (string) $o->total;
  $sig = hash("sha512", $o->id.$sid.$ga.config("payment.midtrans.server_key"));
  echo json_encode([
    "order_id" => (string) $o->id, "status_code" => $sid, "gross_amount" => $ga,
    "transaction_status" => "settlement", "transaction_id" => "mid-".uniqid(),
    "signature_key" => $sig,
  ]);'
  ```
- **Langkah — kirim ke webhook** (tempel output di atas):
  ```bash
  curl -i -X POST http://localhost:8000/api/payments/midtrans/webhook \
    -H 'Content-Type: application/json' -d 'PASTE_JSON'
  php artisan tinker --execute='echo App\Models\Order::latest("id")->first()->status->value;'
  ```
- **Harapkan:**
  - `settlement` → **HTTP 200**, order `pending` → **`paid`** + `transaction_id` terisi.
  - Signature korup (ubah `gross_amount` tanpa hitung ulang sig) → **403** — dan
    status TIDAK berubah.
  - Payload sama dikirim 2× → **200** kedua kali, status tetap `paid` (idempoten).
  - `transaction_status` `cancel` → `cancelled`; `deny` → `failed`.
  - `order_id` tidak ada → **404**.
  - Regresi penting: dengan `PAYMENT_DRIVER=midtrans`, checkout → `ProcessPayment`
    **TIDAK** menandai `paid` (job log `payment.awaiting_webhook`, biarkan `pending`)
    — **webhook satu-satunya jalan ke `paid`**. Dengan `fake` tetap `paid` di job.
- **Uji otomatis:** `php artisan test --filter=MidtransWebhookTest` (11 test).

### G5. Refund (T5)

- **Kode:** `Services/RefundService.php`, `Models/Order.php` (`refund()`),
  `Services/Payment/RefundablePaymentGateway.php`, transisi `paid → refunded`
- **Langkah:** login `/admin` (G6) → `/admin/orders` → order **paid** → aksi **Refund**
  → konfirmasi. Cek stok produk & log refund.
- **Harapkan:**
  - Status `paid` → **`refunded`**; stok order me-**restock** + jejak movement `in`.
  - Urutan benar: **uang kembali dulu, status sesudahnya** — kalau gateway menolak,
    status tetap `paid` dan stok tidak berubah (uji lewat test, driver fake menolak
    via tiruan gateway).
  - Refund doang → kedua kali **ditolak** (`refunded` terminal), uang hanya sekali.
  - Order non-paid → refund ditolak (status machine).
- **Uji otomatis:** `php artisan test --filter=RefundTest` (8 test).

### G6. Filament admin (T6)

- **Kode:** `Providers/Filament/AdminPanelProvider.php` (`/admin`),
  `Models/User.php` (`canAccessPanel`, kolom `is_admin`), `app/Filament/Resources/{Orders,Products,Users}`
- **Langkah:**
  1. Buka **`http://localhost:8000/admin`** (hostname **wajib `localhost`** —
     sama dengan SPA; lihat peringatan di Prasyarat) → login `admin@example.com`
     (lihat prasyarat) → lihat ada Dashboard + Pesanan/Produk/Pengguna.
  2. Coba login dengan `test@example.com` (bukan admin) di `/admin`.
  3. `/admin/products` → buka produk → kolom **Stok** tampil sebagai teks (bukan input).
  4. `/admin/users` → buka user → toggle **Admin** bisa diubah.
  5. Logout lalu buka `/admin` tanpa login.
- **Harapkan:**
  - Non-admin → **403** (ditolak masuk); tamu → redirect ke `/admin/login`.
  - **Dapat 403 padahal sudah login admin di SPA?** → buka pakai `127.0.0.1:8000`
    (beda host) — cookie tidak menyilang. Bukan bug; solusi = samakan `localhost`
    (lihat `ISSUES.md` #8).
  - Stok di form produk **read-only** (perubahan hanya lewat `InventoryService`).
  - Status order di form **read-only** — ubah hanya lewat aksi tabel
    **Batalkan**/**Refund** (lewat status machine), bukan dropdown bebas.
- **Uji otomatis:** `php artisan test --filter=FilamentAdminTest` (5 test).

### G7. Stok realtime (T7)

- **Kode:** `frontend/src/realtime.ts` (subscribe `products`/`StockUpdated`),
  `stores/cart.ts` (`applyStockUpdate`), wiring `App.vue`; event `Events/StockUpdated.php`
- **Langkah (tanpa Reverb — default lab):**
  1. `cd frontend && npx vitest run tests/realtime.spec.js` (6 test).
  2. Dari browser: stok update **belum** terlihat real-time — karena
     `BROADCAST_DRIVER=log` + tanpa Redis/Reverb **tidak ada websocket sungguhan**.
     Sebagai gantinya stok segar muncul lewat **refresh** (T2, sudah lulus).
  3. (Opsional, produksi) pasang Reverb/Pusher lalu expose broadcaster:
     `window.__BROADCAST__ = echoInstance` sebelum app mount — `realtime.ts`
     otomatis aktif, tanpa ubah file.
- **Harapkan:**
  - Tanpa broadcaster → `subscribeStockUpdates` **no-op aman** (tak error, unsubscribe
    tetap boleh dipanggil) — diuji di spec.
  - Payload malformed (`null`, `product_id` bukan angka) → **diabaikan**.
  - Dengan broadcaster tiruan: event `StockUpdated` → `cart.applyStockUpdate` →
    **quantity di-clamp** ke stok baru (qty 5 & stok 2 → qty 2; stok naik → qty tidak
    ikut naik); produk lain di cart tidak tersentuh.
- **Catatan:** bagian browser step 2 = **expected**, bukan bug (lihat SCOPE.md
  "Status implementasi" → deviasi stok realtime).

### G8. Cancel order customer (T8)

- **Kode:** `Api/OrderController::cancel`, `Policies/OrderPolicy::cancel`
  (pemilik + pending), route `POST /api/v1/orders/{order}/cancel`,
  `views/OrderDetailView.vue` (tombol), `stores`
- **Langkah:**
  1. Checkout (biarkan `pending` — jangan tunggu worker) → buka Pesanan →
     buka order itu → tombol **Batalkan order**.
  2. Klik tombol → amati status + stok produk.
  3. Buka order yang sudah `paid` → periksa tombol.
  4. (otorisasi) dengan user lain / tanpa login → `POST .../cancel` via curl.
- **Harapkan:**
  - Tombol **hanya tampil untuk `pending`**; `paid` → tombol tidak ada.
  - Klik → status `cancelled`, **stok me-restock** + jejak movement `in`, tombol hilang.
  - Order `paid` → **403** (refund = urusan admin, G5); order user lain → **403**;
    tanpa login → **401**.
  - Cancel doang → kedua kali **403** (bukan pending lagi), stok tidak dobel-restock.
- **Uji otomatis:** `php artisan test --filter=CancelOrderTest` (6 test).

---

## H. Hardening ASVS — security headers (fase hardening akhir)

- **Kode:** `Http/Middleware/AddSecurityHeaders.php` (registrasi global di
  `bootstrap/app.php`), `config/cors.php`, test `tests/Feature/SecurityHeadersTest.php`
  + `frontend/e2e/security-headers.spec.ts`
- **Langkah (curl -I, header kelihatan langsung):**
  ```bash
  curl -I http://localhost:8000/login
  curl -I http://localhost:8000/api/v1/products        # 401 tapi header tetap ada
  curl -I http://localhost:8000/admin                  # 200/redirect, CSP menempel
  ```
  Buka juga `http://localhost:8000/admin` di browser → panel Filament **tetap
  normal** (CSP sengaja tanpa `script-src` — lihat catatan di bawah).
- **Harapkan:**
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Strict-Transport-Security: max-age=31536000; includeSubDomains`
  - `Cache-Control: no-store, private` (semua response dinamis — ASVS 14.3.2)
  - `Content-Security-Policy: frame-ancestors 'none'; object-src 'none';
    base-uri 'self'; form-action 'self'`
  - CORS: `curl -I -H 'Origin: https://evil.example' …/api/v1/products` →
    **tanpa** `access-control-allow-origin`; pakai `http://localhost:5173` →
    header ACAO ada (daftar origin ketat, bukan `*`).
- **Catatan CSP (ASVS 3.4.3 ⚠️ partial):** `script-src` TIDAK dipasang —
  Filament v4 memakai inline `<script>`/`<style>` **tanpa dukungan nonce**
  (`vendor/filament/.../layout/base.blade.php`), jadi CSP script-src ketat
  akan mematahkan `/admin`. Directive yang dipasang hanya yang tak menyentuh
  eksekusi script. Full `script-src` = tunda sampai Filament dukung nonce.
- **Uji otomatis:** `php artisan test --filter=SecurityHeadersTest` (7 test);
  `cd frontend && npx playwright test e2e/security-headers.spec.ts` (3 test).

---

## I. E2E Playwright — peta cakupan T1–T8

**Cara jalankan (prasyarat WAJIB — lihat catatan rate limit & state di bawah):**
```bash
php artisan migrate:fresh --seed   # data segar: test@ + admin@ + 12 produk + 6 order
php artisan cache:clear            # reset rate limiter (login 5/mnt, register 3/mnt)
cd frontend && npm run test:e2e    # 16 test, jalankan serial (workers:1)
```

**Arsitektur 3 project (`playwright.config.ts`) — cara hindari 429:**

| Project | Spec | Kenapa terpisah |
|---|---|---|
| `setup` | `setup/auth.setup.ts` | login SEKALI → tulis `playwright-auth.json` |
| `anon` | `auth`, `register`, `password-reset`, `security-headers` | butuh state bersih (uji login/guest asli) |
| `app` | `orders`, `products`, `cart`, `cancel-order`, `admin` | pakai storageState — **tanpa login ulang** |

**Peta cakupan:**

| Tahap | Spec | Alur |
|---|---|---|
| T1 | `register.spec.ts` | register → auto-login → notice verifikasi |
| T1 | `password-reset.spec.ts` | register → forgot → **tautan dari `laravel.log`** → reset → auto-login |
| T3 | `cart.spec.ts` | add → **reload tetap (server-side)** → clamp qty → hapus |
| T8 | `cancel-order.spec.ts` | checkout (pending — queue worker tak jalan) → cancel → status `cancelled` |
| T5 | `admin.spec.ts` | panel → login admin → aksi **Refund** order paid → `refunded` |
| T6 | `admin.spec.ts` | non-admin → **403**; admin → dashboard → resource orders |
| H | `security-headers.spec.ts` | header via HTTP asli `localhost:8000` |
| T2/T4/T7 | — | sengaja TIDAK di-e2e: tercakup test unit/feature (11 webhook, 6 realtime, inventory) |

**Catatan penting (semua dari bug nyata saat pengembangan):**
- **Serial `workers:1`** — `orders`/`cart`/`cancel-order` memanipulasi cart
  user YANG SAMA (test@) → paralel = race (checkout menghabiskan cart spec lain).
- **Jangan reset password `test@example.com`** di test — spec reset memakai
  user e2e khusus (per-run), kalau tidak, spec auth run berikutnya gagal.
- **State refund bersifat sekali-pakai** — `admin.spec` mengubah order paid
  seed → `refunded`. Run ulang TANPA reseed = test refund gagal. Selalu
  `migrate:fresh --seed` dulu.
- `/forgot-password` itu **guest-only** — user masih login di-guard ke
  beranda (ISSUES #5); spec reset logout dulu setelah register.
- **`hasText: 'paid'` case-insensitive** — kena header kolom "Paid at";
  incar tombol aksanya langsung, jangan filter baris by status teks.
- Tombol konfirmasi Filament berlabel **"Confirm"**, bukan label aksi.

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

### Checklist scope T1–T8 (SCOPE.md)

- [x] G1 (T1) register/reset/verifikasi — lulus manual + 23 test
- [x] G2 (T2) status machine + inventory movement — lulus (bug ketemu: cache list
      stok basi setelah checkout → fix `e5b1496`, regresi ditambahkan)
- [x] G3 (T3) keranjang server-side + rate limit checkout — lulus
- [x] G4 (T4) Midtrans webhook signature — lulus (bug ketemu: `ProcessPayment`
      paid prematur untuk gateway async → fix `63582ce`, regresi ditambahkan)
- [x] G5 (T5) refund — lulus via admin + 8 test
- [x] G6 (T6) Filament admin `/admin` (hanya `is_admin`) — lulus + 5 test
- [x] G7 (T7) stok realtime — lulus (deviasi terdokumentasi: no-op tanpa Reverb,
      spec 6 test)
- [x] G8 (T8) cancel order customer — lulus manual + 6 test

> Temuan manual E2E selanjutnya: tulis di `ISSUES.md` (format sama),
> jangan dibetulkan diam-diam.
