# Debrief — Pertanyaan & Jawaban per Milestone

Kumpulan debrief Laravel 13 Lab (M1–F3). Pertanyaan diajukan setiap milestone
selesai; jawaban menyertai. Dipakai sebagai bahan bacaan ulang + persiapan
interview (kaitan roadmap poin 3: System Design).

---

## M1 — Architecture (container, DI, collections)

**1. Request `POST /checkout` masuk → siapa yang bikin object `FakePaymentGateway`?
Kenapa controller gak perlu `new`?**
Container Laravel. `AppServiceProvider::register()` punya binding
`PaymentGateway::class → FakePaymentGateway::class`. Saat controller minta
parameter bertipe `PaymentGateway`, container resolve binding itu dan
menginjeksinya — controller tahu kontrak (interface), bukan implementasi.
Keuntungan: ganti implementasi = ubah 1 baris binding.

**2. Beda `singleton()` dengan `bind()`?**
- `bind()` → container panggil factory **tiap resolve** → object baru tiap kali.
- `singleton()` → factory dipanggil **sekali**, hasilnya di-cache → semua resolve
  dalam 1 request mengembalikan object **yang sama**.

M1 pakai `singleton` karena controller dan test harus melihat log `charges`
yang sama. Kalau pakai `bind`: controller punya instance A, test resolve
instance B (charges kosong) → test `charges toHaveCount(1)` gagal.
`bind` cocok kalau tiap pemakai butuh state sendiri.

**3. Apa yang terjadi kalau `$product->price = 20000; save()` — apakah
`formatted_price` ikut berubah tanpa kolom baru?**
Ya. `formatted_price` = **accessor**: nilai turunan yang **dihitung saat dibaca**
(`'Rp '.number_format($this->price,...)`), bukan data tersimpan. Begitu `price`
berubah, bacaan berikutnya otomatis `"Rp 20.000"` — tanpa kolom tambahan.
Test membuktikan: `getAttributes()` gak punya key `formatted_price`.

---

## M2 — Database & Eloquent

**1. Kenapa `lockForUpdate` harus di dalam `DB::transaction`?**
Race condition: dua request baca stok 5 bersamaan → dua-duanya lolos cek →
dua-duanya tulis `5-3=2` → stok akhir 2 padahal terjual 6 (oversell).
Tanpa transaction, di MySQL row lock **lepas begitu statement selesai**
(autocommit) — kuncinya harus **hold dari baca sampai tulis selesai commit**,
baru request kedua blocking di SELECT dan membaca nilai yang sudah diperbarui.
Transaction = atomicity baca-cek-tulis; lock = serialization antar request.

**2. Kenapa jumlah query tetap ~4 walau order-nya 10? Isi 4 query apa saja?**
Karena `with(['user','items.product'])` + `withCount('items')`:
1. `SELECT * FROM orders`
2. `SELECT * FROM users WHERE id IN (...)`
3. `SELECT * FROM order_items WHERE order_id IN (...)`
4. `SELECT * FROM products WHERE id IN (...)`

`withCount` **bukan query terpisah** — itu subquery `(SELECT COUNT(*)...)`
di dalam query orders (no. 1), dijalankan database dalam satu round-trip.
Tanpa eager loading: 1 + 10 (user per order) + 10 (item) + 30 (product) ≈ 51 query.

**3. Kolom `price` vs accessor `formatted_price` — kalau price diubah, apakah
formatted ikut berubah tanpa kolom baru?**
Ya — sama seperti M1: `formatted_price` derifasi saat dibaca dari `$this->price`,
tidak pernah disimpan. Ubah `price` → save → baca ulang → nilai baru.

---

## M3 — Validation, Auth & Authorization

**1. `Auth::attempt` (web) vs `Hash::check` (token endpoint) — kenapa API gak
boleh pakai `Auth::attempt`?**
`Auth::attempt` = SessionGuard: sukses → **menulis user id ke session**,
session harus ada. Route API **stateless** (tanpa session middleware) — gak ada
session yang bisa ditulis dan memang tidak diinginkan; API autentikasi
per-request via Bearer token. Jadi API validasi manual `Hash::check` →
kalau cocok → terbitkan token. Web login pakai `attempt` justru karena kita
MAU session.

**2. Kenapa logout 3 langkah: `logout()` → `invalidate()` → `regenerateToken()`?**
- `Auth::logout()` → hanya hapus identitas dari request ini. Session id & data hidup.
- `session()->invalidate()` → **buang semua data + rotate session id**. Tanpa ini:
  sisa data (cart, flash) masih ada, dan **session fixation** tetap mungkin —
  id yang diketahui penyerang tidak pernah diganti.
- `session()->regenerateToken()` → **CSRF token baru**. Tanpa ini: form lama
  yang di-cache/di-embed penyerang masih punya CSRF token valid → bisa dipakai
  submit sesudah logout.

`logout()` saja = identitas hilang tapi "wadah" utuh. Dua lainnya menghancurkan wadahnya.

**3. Cek stok di rule `StockAvailable` + cek ulang di `Product::buy()` lock —
kenapa bukan DRY violation?**
DRY = hindari duplikasi pengetahuan **dengan tujuan sama**. Ini beda tujuan:
- **Rule** = UX check: baca tanpa lock, pesan jelas cepat ke user. Racy — stok
  bisa berubah antara validasi dan eksekusi.
- **`buy()` + lock** = jaminan konsistensi: authoritative, di dalam transaction.

Hapus rule → user dapat error generik. Hapus lock → oversell. Rule juga bisa
dilewati pemicu lain (queue, endpoint lain) — enforcement point tetap di `buy()`.
Defense in depth, bukan kode sama dua kali.

---

## M4 — API Layer

**1. Kenapa charge gateway setelah transaksi, bukan di dalam?**
Kalau di dalam: charge sukses lalu transaksi gagal → user ditagih tanpa order.
Setelah commit: order konsisten dulu; charge gagal → order tetap `Pending`,
bisa di-retry (persiapan M5 queue). Trade-off: charge gagal tapi order ada —
state yang jelas, bukan uang hilang.

**2. Apa yang terjadi saat 1 item stok kurang di checkout multi-item?**
`ValidationException` dilempar **di dalam** `DB::transaction` → keluar dari
closure → **rollback total**: order, item yang sudah dibuat, dan pengurangan
stok item pertama semua dibatalkan. Test: `Order::count() === 0`, stok item
pertama kembali utuh, gak ada charge.

**3. Offset vs cursor pagination — kapan pakai mana?**
- **Offset** (`?page=2`): butuh nomor halaman + total ("halaman 3 dari 10"),
  cocok untuk katalog statis. Kelemahan: data baru di-insert → halaman
  bergeser/duplikat.
- **Cursor** (`?cursor=token`): token posisi — stabil saat data berubah, murah
  di offset besar, tapi gak bisa lompat halaman sembarang.
- Produk → offset; order/feed → cursor.

**4. Kenapa `withCount` gak bisa dicek `relationLoaded()`?**
Dibuktikan di tinker: `relationLoaded('items_count')` = `false` tapi
`$o->items_count` = `3`. `withCount` mengisi **attribute** hasil subquery SELECT,
bukan me-load relasi ke `$model->relations`. Ceknya pakai `isset()`.

---

## M5 — Events, Queues, Notifications, Mail

**1. Kenapa `event()` + `dispatch()` setelah `DB::transaction` commit?**
Di dalam: listener/job bisa jalan memuat data yang belum konsisten — kalau
transaction gagal/rollback, queue sudah terlanjur pegang order yang tidak ada
(→ charge tanpa order). Setelah commit: data permanen; apapun yang terjadi di
event/job tidak mempengaruhi kebenaran DB.

**2. Apa yang mencegah double charge saat retry?**
Guard `paid_at` di awal `handle()`, dikombinasikan `SerializesModels`:
tiap retry object Order **di-refetch dari DB** — kalau attempt pertama sukses
charge+update tapi crash sebelum selesai, attempt kedua melihat `paid_at`
terisi → return tanpa charge. Test: 2x `handle()` = 1 charge.

**3. Kenapa notification `ShouldQueue` tapi listener sync?**
Listener (cek + panggil notify) murah → jalan di request, discovery-simple.
Notification (compose email + kirim mail + tulis DB) berat → di-queue, respons
tidak menunggu SMTP. Aturan praktis: yang berat/mudah gagal → queue; murah → sync.

**4. Bedanya `failed()` dengan try/catch?**
try/catch menangkap **1 kali**. `failed()` dipanggil **otomatis oleh queue
setelah `$tries` (3) habis** dengan backoff — logika "order gagal total" jalan
di ujung retry. Tanpa `failed()`, order menggantung `pending` selamanya walau
gateway down terus.

---

## M6 — Digging Deep (broadcast, cache, storage, i18n)

**1. Kenapa event `StockUpdated` dipindah keluar `buy()`?**
`buy()` dipanggil **di dalam** transaction checkout yang lebih besar. Event
di-queue dari dalam transaction → worker bisa memproses **sebelum commit /
saat rollback** → broadcast bilang stok 3 padahal DB kembali ke 5. Test
rollback menangkap bug ini. Prinsip: **side effect (event/job/broadcast) hanya
setelah commit terluar** — di model method yang bisa dipanggil nested, itu
mustahil dijamin → tanggung jawab caller.

**2. Bukti produk list SUDAH cache — apa?**
Test menulis langsung ke DB dengan `DB::table()->update()` (**tanpa model
event**) lalu GET ulang: halaman **masih menampilkan data lama** → tidak ada
query ke DB = cache hit. Kalau pakai `Product::update()`, model event `saved`
→ `Cache::forget` → data baru muncul. Satu paket: bukti cache aktif + invalidasi bekerja.

**3. Kenapa gambar disk privat + diserve controller, bukan `public` + symlink?**
Disk `public/` = file bisa diakses langsung tanpa lewat PHP (tanpa otorisasi,
tanpa audit). Lewat controller: `abort_unless` 404 kalau gak ada, nanti tinggal
tambah policy. Plus `store()` bikin path random — nama file user tidak pernah
dipakai (cegah path traversal/overwrite).

**4. Kenapa 2 tempat set locale (App + Carbon)?**
`App::setLocale` hanya mempengaruhi `trans()`/`__()`. Carbon punya translator
sendiri — tanpa `Carbon::setLocale()`, `isoFormat('D MMMM YYYY')` tetap
"October". Dua sistem, dua setelan.

---

## M7 — Security, Error Handling & Logging

**1. Kenapa render callback type-hint `AuthorizationException` gak kepanggil?**
`Handler::render()` menjalankan `prepareException()` **dulu**, lalu render
callbacks. `AuthorizationException` sudah di-convert jadi
`AccessDeniedHttpException`, `ModelNotFoundException` jadi `NotFoundHttpException`
— type-hint kelas aslinya tidak pernah cocok. Solusi: type-hint induk
`HttpException` + cek `getStatusCode()`. Pelajaran: framework punya pipeline
internal, baca source sebelum asumsi.

**2. Kenapa signed URL cocok untuk invoice, tapi tidak untuk ganti password?**
Signed URL = **possession-based**: siapa pun yang punya link dianggap sah.
Invoice via email (pemilik terverifikasi saat link dibuat) → cukup. Aksi
berisiko (ganti password, hapus data) butuh **autentikasi ulang** — link bisa
bocor/di-forward, dan tidak bisa dicabut per-user.

**3. Trade-off encrypted cast?**
`phone` ciphertext di DB, aplikasi decrypt otomatis: database bocor tanpa
`APP_KEY` → data aman. Konsekuensi: **gak bisa `WHERE phone = ...`** di SQL.
Kolom yang sering di-query (email) jangan di-encrypt — pilih berdasarkan pola
akses, bukan "sensitif semua".

**4. Kenapa `request_id` di `Log::withContext()` bukan ditulis manual tiap log?**
Context Monolog **nempel otomatis ke semua log dalam 1 request** dan hilang
sendiri di request berikutnya. Manual: lupa 1 tempat = baris tanpa trace, dan
harus bersih-bersih setelah request. Ini inti correlation ID: 1 request =
semua baris log saling terhubung lintas service.

---

## F1 — Vue 3 Composition API basics

**1. `ref` vs `reactive`?**
`ref` bungkus nilai apa pun, akses `.value` di script (template auto-unwrap),
dan **boleh di-assign ulang** (`loading.value = false`) — dipakai untuk state
primitif/array. `reactive` untuk object deep-reactive tapi **gak bisa diganti
total** (`state = {...}` putus reaktivitas) dan tanpa `.value`. Praktik:
primitif state → `ref`.

**2. `computed` vs fungsi biasa?**
Computed **di-cache by dependency** — hanya dihitung ulang saat dependensi
berubah, bukan tiap render. Fungsi biasa jalan tiap kali dipanggil. Prinsip
sama dengan `OrderSummary` di backend, tapi di frontend cache-nya penting
karena input user berubah sering.

**3. Kenapa fetch di `onMounted`, bukan top-level `<script setup>`?**
Top-level script = fase setup, belum ada DOM. `onMounted` = hook resmi
"komponen sudah terpasang" — aman untuk side-effect, tempat pairing cleanup
(`onUnmounted`/AbortController), dan render loading state dulu baru data datang.

**4. Kenapa test router harus punya route bernama?**
View memanggil `router.push({ name: 'product-detail' })` — router di test
adalah **instance terpisah** dari production router, jadi semua route
(termasuk namanya) harus didefinisikan ulang. Test router harus mirror router asli.

---

## F2 — Pinia + composables

**1. Pinia setup-store vs Vuex?**
Gak ada mutation (action langsung mutasi state — Vuex wajib mutation,
boilerplate ganda), state = `ref` biasa, getter = `computed`, gak ada module
registration. Satu file = state+getter+action, gaya Composition API sama
dengan komponen.

**2. Store (global) vs composable (lokal)?**
Store: **1 instance seluruh aplikasi** — semua komponen lihat keranjang sama
(badge di App.vue sinkron dengan halaman cart). Composable: **state per
komponen yang memanggil** — `useProducts()` di 2 komponen = 2 data terpisah.
Aturan: state yang harus dibagi → store; logic reusable tanpa state global → composable.

**3. Kenapa `watch(items, ..., { deep: true })`?**
`items` **tidak pernah diganti referensinya** — mutasi di dalam array
(`push`, `item.quantity = 3`). Tanpa `deep`, watch gak trigger. `watchEffect`
gak dipakai karena cuma ada 1 dependensi eksplisit dan efek samping tidak
boleh jalan saat dependency lain berubah.

**4. Kenapa `loadSaved()` pakai try/catch `JSON.parse`?**
`localStorage` = **input eksternal tak terpercaya** — bisa korup (edit manual,
quota). `JSON.parse('{rusak')` melempar exception → app crash di baris pertama.
Fallback `[]` = boundary validation, prinsip sama dengan validasi request backend.

---

## F3 — Integrasi full-stack (Sanctum SPA)

**1. Cookie session (SPA) vs Bearer token — kapan mana?**
- **Cookie**: frontend domain sama/terproxy — otomatis ikut request, logout
  server-side langsung berlaku, dilindungi CSRF.
- **Token**: klien tak kenal (mobile, third-party) — gak ada konsep cookie/CSRF,
  bisa direvoke per-token.
- Kita simpan dua: SPA pakai cookie, `/api/token` untuk konsumen eksternal.

**2. Kenapa csrf-cookie dulu, lalu header `X-XSRF-TOKEN`?**
Pola **double-submit cookie**: server taruh token acak di cookie yang bisa
dibaca JS; client harus mengembalikannya di header. Form lintas-site (CSRF)
bisa kirim cookie tapi **gak bisa membacanya** (same-origin policy) → gak bisa
meng-echo token → ditolak. Cookie session sendiri httpOnly (gak bisa dibaca
JS) — makanya dua cookie beda peran.

**3. Kenapa `beforeEach` guard harus async?**
Status login **tidak bisa dicek sinkron**: cookie httpOnly gak terbaca JS,
hanya backend yang tahu valid-tidaknya session. Tanya `/api/user` sekali →
simpan di **store** → navigasi berikutnya gak fetch ulang (cache `auth.user`).
Beda F1: token di localStorage → cek sinkron `!!getToken()`.

**4. Kenapa checkout kirim cuma `product_id` + `quantity`, bukan harga?**
Harga dari client = **tawaran, bukan fakta**. Kalau cart kirim `price: 1` dan
backend percaya = diskon 99% otomatis. Backend (M4) hitung ulang total dari DB
di dalam transaksi — client hanya bilang "mau beli apa dan berapa". Prinsip:
validasi & kebenaran selalu di server.
