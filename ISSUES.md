# ISSUES — Bug manual E2E (SPA login, 2026-10-10)

Catatan bug nyata yang ditemukan saat tes manual E2E alur login SPA
(`frontend/` + Laravel). Format: laporan → akar masalah → fix → pelajaran.

---

## 1. Styling hilang saat akses langsung / reload `http://localhost:5173/login`

**Laporan:** redirect dari `/` ke `/login` bagus, tapi akses langsung/reload
halaman login → tampil polos tanpa Tailwind.

**Akar masalah:** proxy Vite `'/login'` menangkap **GET juga** — navigasi
browser ke `/login` diteruskan ke Laravel → balasan **blade login**, bukan
index.html SPA. Redirect client-side dari `/` tidak menyentuh server → tetap
SPA (makanya kelihatan beda).

**Fix:** plugin `spaLoginRoute` di `vite.config.js`: GET `/login*` ditulis
ulang ke `/index.html` sebelum proxy; POST `/login` tetap ke Laravel.

**Pelajaran:** kalau path yang sama dipakai SPA route DAN endpoint backend,
proxy harus diskriminasi per method — atau pisahkan path-nya.

---

## 2. Login gagal: `Failed to fetch`, tidak pindah halaman

**Laporan:** submit login dari halaman SPA → error `Failed to fetch`, tetap di
halaman login.

**Akar masalah:** `POST /login` via proxy → Laravel redirect absolut ke
`http://127.0.0.1:8000/orders` (Host yang dilihat Laravel = target proxy).
`fetch()` mengikuti 302 **lintas origin** (5173 → 8000) → CORS/network
blocked → `Failed to fetch`.

**Fix:** `changeOrigin: false` di semua proxy → Host asli dipertahankan →
`Location: http://localhost:5173/orders` (sesama origin SPA).

**Pelajaran:** redirect absolut dari backend lewat proxy = jebakan klasik
fetch/XHR. `changeOrigin:false` (atau `X-Forwarded-Host`) menjaga URL tetap
di origin front-end.

---

## 3. 419 Page Expired dari halaman login yang tak bergaya

**Laporan:** login dari versi "tanpa styling" → kadang 419.

**Akar masalah:** halaman "tanpa styling" = blade login yang bocor lewat proxy
(issue 1) — token CSRF blade + cookie session tercampur antara host
`localhost` dan `127.0.0.1` (cookie jar terpisah per host) → token gak cocok
→ 419. Setelah issue 1 diperbaiki, jalur ini hilang.

**Pelajaran:** campur `localhost` / `127.0.0.1` = cookie & session beda dunia
di browser. Seragamkan satu origin saat development.

---

## 4. `GET /orders` 404 setelah login sukses (302)

**Laporan:** network: `csrf-cookie 204`, `login 302`, **`orders 404`** →
login tetap dianggap gagal.

**Akar masalah:** `fetch()` mengikuti 302 ke `/orders` membawa header
`Accept: application/json` — fallback SPA Vite hanya melayani `text/html` →
404 → `parseOrThrow` melempar error.

**Fix:** `spaLogin` pakai `redirect: 'manual'` — response 302
(`opaqueredirect`/3xx) diabaikan; keberhasilan login **dibuktikan oleh
`GET /api/user`** di auth store (bukan bentuk response redirect).
Login gagal → `/api/user` 401 → error "Email atau password salah."

**Pelajaran:** redirect login = sinyal lemah (sukses & gagal sama-sama 302).
Bukti autentikasi yang kuat = endpoint yang butuh sesi. Jangan follow redirect
di fetch yang tujuannya cuma "lakukan aksi".

---

## 5. `/login` masih bisa diakses saat sudah login

**Fix:** guard router — `to.name === 'login'` + ada `auth.user` → redirect `/`.
Guest tetap diizinkan; `auth.user` dicek via `/api/user` kalau state belum ada.

---

## Enhancement yang menyusul

- Toggle password: teks → ikon SVG eye / eye-off (aria-label dipertahankan)
- Base URL pindah ke env: `frontend/.env` → `VITE_BACKEND_URL` (target proxy),
  `VITE_API_BASE` (prefix API) + `.env.example`

---

## Pelajaran umum debugging (jejak investigasi yang salah)

- `Auth::attempt` tinker `true` → kredensial bukan masalah; jangan langsung
  curigai seeder
- Curl **tanpa header `Referer`** → Sanctum `EnsureFrontendRequestsAreStateful`
  menganggap bukan request frontend → session middleware gak jalan → 401 palsu.
  Selalu replikasi header browser (Origin/Referer) saat debug auth SPA
- Nilai cookie Laravel didecrypt jadi `sha1|id` — itu **CookieValuePrefix**,
  bukan session id ganda; id asli = bagian setelah `|`
- Payload tabel `sessions` = **base64(JSON)**, bukan JSON mentah
- Red herring berbahaya: 401 bisa muncul di 3 lapis (cookie gak terkirim,
  session gak kebaca, guard menolak) — buktikan lapis per lapis sebelum
  menyimpulkan akar masalah

---

## 6. Link invoice SPA → halaman kosong (2026-10-11)

**Laporan:** klik "Lihat invoice" dari halaman order detail
(`http://localhost:5173/orders/9/invoice?expires=…&signature=…`) → halaman
tidak menampilkan apa-apa.

**Akar masalah:** `invoice_url` di-generate lewat proxy Vite → ikut ter-sign
untuk host `localhost:5173`. Vite meneruskan `/api` `/sanctum` `/login`
`/logout` saja — path `/orders/*/invoice` jatuh ke SPA fallback
(`index.html`), dan router SPA tidak punya route itu → kosong.

**Perbaikan:** proxy regex `^/orders/[^/]+/invoice` → backend (Host
dipertahankan → signature cocok); router SPA dapat catch-all redirect ke `/`;
e2e `orders.spec.ts` kini benar-benar MENGKLIK link dan memverifikasi heading
`Invoice Order #…` tampil.

**Pelajaran:** fitur yang menyentuh dua origin (SPA + backend) harus diuji
sampai halaman tujuan tampil, bukan sampai href-nya benar. E2E yang hanya
assert atribut href tidak menangkap bug ini.

## 7. Login 302 sukses, SPA tetap bilang "Email atau password salah." (2026-10-18)

**Laporan:** `make dev`, login `test@example.com` / `password` → pesan "Email
atau password salah." Padahal kredensial benar (user ada di DB, hash cocok).

**Akar masalah:** `POST /login` sebenarnya **302 sukses** — yang gagal adalah
`GET /api/user` (401) yang dipakai auth store sebagai BUKTI login. Sanctum
`EnsureFrontendRequestsAreStateful` hanya menyalakan session middleware kalau
Referer/Origin cocok dengan `config('sanctum.stateful')`. Daftar itu berisi
`localhost`, `localhost:3000`, `127.0.0.1`, `127.0.0.1:8000`, `::1` + APP_URL
— **`localhost:5173` (origin Vite dev) tidak ada** → request dianggap bukan
frontend → StartSession tidak jalan di `/api/*` → cookie session diabaikan →
401 → SPA melempar error kredensial palsu.

Bukti: curl dengan `Referer: http://localhost:5173/` → 401; dengan
`Referer: http://localhost:8000/` → 200. Request identik, beda referer saja.

**Fix:** `config/sanctum.php` — origin dev Vite (`localhost:5173`,
`127.0.0.1:5173`) masuk daftar default `stateful`; regression test
`SpaAuthTest` `lists the Vite dev origin as a stateful Sanctum domain`.

**Pelajaran:**
- Pesan error bisa berasal dari LAPISAN BERBEDA dari yang terlihat. "Email atau
  password salah." di UI = sintesis SPA (auth store), bukan respons Laravel —
  login sukses pun menghasilkan pesan yang sama. Bedakan siapa yang melempar
  sebelum menyelidiki kredensial.
- Test feature **tidak bisa** menangkap bug ini: instance app dibagi antar
  request dalam satu test, jadi guard tetap menganggap user login meski
  middleware session tidak pernah jalan. Test yang lolos = palsu. Yang
  menangkap: **e2e Playwright** (jalankan lewat origin asli) dan assertion
  konfigurasi langsung ke `sanctum.stateful`.
- `php artisan test` + Pint + vitest lulus semua tidak membuktikan alur
  lintas-origin bekerja. Selalu jalankan e2e yang menyentuh origin asli.
- Hati-hati saat debug: mencabut fix sementara untuk "membuktikan test gagal"
  bisa meninggalkan konfigurasi setengah jalan. Verifikasi ulang setelah
  restore (di sesi ini: 4 e2e sempat gagal karena bukan fix-nya, melainkan
  rate limiter `throttle:login` 5/menit yang kebablasan oleh test berulang).

---

## 8. `/admin` 403 padahal sudah login admin di SPA (2026-10-19)

**Laporan:** login `admin@example.com` di SPA (`http://localhost:5173`) →
sukses. Buka `http://127.0.0.1:8000/admin` → **403 Forbidden**. Tapi kalau
login dulu di `http://127.0.0.1:8000/login` → `/admin` bisa.

**Akar masalah:** cookie session **host-only** (`Set-Cookie` Laravel: `path=/;
httponly; samesite=lax`, tanpa atribut `Domain`). `localhost` dan `127.0.0.1`
dianggap **host berbeda** oleh browser → dua cookie jar terpisah, dua session
terpisah:

- SPA `localhost:5173` (proxy ke backend) menyetel cookie untuk host **`localhost`**.
- Membuka `127.0.0.1:8000` hanya mengirim cookie host **`127.0.0.1`** — session
  admin di `localhost` **tidak ikut**.
- Host `127.0.0.1` masih menyimpan session lama dari login langsung pakai
  `test@example.com` → terautentikasi tapi **bukan admin** → `canAccessPanel()`
  false → **403** (bukan redirect, karena bukan tamu).

Bukti (eksperimen terkontrol, curl cookie jar terpisah):

```
login ADMIN via localhost:8000  → cookie host=localhost  → GET /admin → 200
login NON-ADMIN via 127.0.0.1   → cookie host=127.0.0.1  → GET /admin → 403
```

**Fix:** tidak ada di kode (perilaku cookie memang benar & diinginkan) —
**docs** yang diperbaiki: semua URL diseragamkan ke **`localhost`**
(`APP_URL` sudah `http://localhost:8000`), plus peringatan di `FEATURES.md`
Prasyarat dan langkah G6 yang menyebut hostname eksplisit.
`SESSION_DOMAIN` **tidak** bisa menyembuhkan ini — `localhost`/`127.0.0.1`
tidak punya parent domain bersama.

**Pelajaran:**
- Pelajaran issue #3 ("campur `localhost`/`127.0.0.1` = cookie beda dunia")
  **sudah tertulis tapi dilanggar docs sendiri** — prasyarat lama menulis
  backend `127.0.0.1:8000` sementara SPA `localhost:5173`. Kalau ada aturan
  konsistensi, dokumen instruksinya ikut divalidasi, bukan hanya kode.
- Bedakan tiga kondisi dari satu kode status: **redirect** = tamu;
  **403** = terautentikasi non-admin; **200** = admin. Membaca status code
  sudah cukup untuk menebak isi session tanpa membuka devtools.
- Untuk menguji klaim semacam ini, buat eksperimen terkontrol (dua cookie
  jar, dua host, login beda akun) alih-alih berspekulasi — sekali jalan
  langsung ketahuan akar masalahnya.
