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
