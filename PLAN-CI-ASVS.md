# Rencana: CI E2E + Sisa ASVS Quick Wins

Dua fase = dua commit terpisah (pola repo: 1 unit = 1 commit + test backend
& frontend). Kondisi awal: ter-push bersih, 182 PHP + 68 Vitest + 16 e2e +
vue-tsc + eslint hijau.

> Catatan eksekusi: setelah plan ini disetujui, SALIN ke folder project
> sebagai `PLAN-CI-ASVS.md` (user minta plan baru ada di folder — sama seperti
> `PLAN.md` sebelumnya), lalu kerjakan Fase A → commit → Fase B → commit.

**Out of scope (butuh infra/keputusan besar, dicatat eksplisit):**
MFA (6.3.3, butuh Fortify), antivirus scan (5.4.3, butuh ClamAV/eksternal),
dokumen formal secrets-mgmt/SBOM (13.3.1/15.1.2), token storage pindah dari
localStorage (butuh keputusan UX: memory-only vs cookie HttpOnly),
cookie Secure/`__Host-` (dev HTTP — tetap non-blocking menurut SCOPE.md).

---

## Fase A — Commit 1: Masukkan e2e ke CI

**Masalah:** 16 spec Playwright hanya jalan manual — regresi alur utama
(register, cart, cancel, admin, headers) tak otomatis ketahuan CI.

### Perubahan: `.github/workflows/ci.yml` saja

1. **Filter** (`changes` job): tambah `'.github/workflows/ci.yml'` ke filter
   `backend` (perubahan workflow sendiri ikut teruji).
2. **Job baru `e2e`** — `needs: changes`, `if: backend == 'true' || frontend == 'true'`
   (jalur ini menguji integrasi keduanya):

   | Step | Detail |
   |---|---|
   | checkout | — |
   | setup-php 8.5 | `mbstring, sqlite3, pdo_sqlite` (salin dari job backend) |
   | setup-node 22 | `cache: npm`, `cache-dependency-path: frontend/package-lock.json` |
   | composer install | cache `vendor` (key hash composer.lock, sama dgn job backend) |
   | npm ci | `working-directory: frontend` |
   | Prepare env | `cp .env.example .env && php artisan key:generate` |
   | Seed DB | `touch database/database.sqlite && php artisan migrate:fresh --seed` (kontrak e2e: data test@+admin@+12 produk+6 order) |
   | Playwright browser | cache `~/.cache/ms-playwright` (key: versi playwright dari package-lock) lalu `npx playwright install --with-deps chromium` |
   | Run e2e | `npm run test:e2e` — `webServer` config menyalakan artisan serve + vite sendiri (di CI `CI=true` → `reuseExistingServer:false`) |
   | Upload trace | `actions/upload-artifact@v4`, `if: failure()`, path `frontend/test-results` (trace `retain-on-failure` sudah aktif) |

**Kenapa aman untuk CI:** rate limit — runner fresh per run (cache:clear tak
perlu); hitungan `POST /login` e2e cuma 3/menit & `workers:1` serial;
`retries: 2` di CI tidak menumpuk melewati limit 120 (lihat Fase B throttle).
Runtime tambahan ±1,5–2 menit.

**Verifikasi lokal (YAML tak bisa diuji penuh tanpa GitHub):**
- parse YAML via Python (`yaml.safe_load`) agar sintaks valid;
- review struktur identik dengan job existing (setup-php/node versi sama);
- e2e lokal tetap 16/16.

---

## Fase B — Commit 2: Sisa ASVS quick wins

Tiga gap: **6.2.2/6.4.3** (ubah password), **16.3.1/16.3.2** (auth logging),
**2.4.1** (throttle API v1).

### B1. Ubah password saat login (6.2.2 ❌, 6.4.3 ❌)

- **Backend**
  - `routes/web.php`: `POST /change-password` → `middleware(['auth', 'throttle:password'])`
    (limiter 3/menit email+ip — sudah ada, anti-bruteforce current password).
  - `ChangePasswordFormRequest`: `current_password` wajib valid (rule bawaan
    Laravel `CurrentPassword` → Hash::check), `password` policy (min 8,
    confirmed), larangan password baru = password lama.
  - `ChangePasswordController@store`: update (cast `hashed` → tak perlu Hash::
    manual), `$request->session()->regenerate()` (session fixation pasca-ubah
    kredensial), log `auth.password_changed` (user_id, ip — tanpa password).
- **Proxy** `frontend/vite.config.js`: tambah `'/change-password'` ke daftar
  proxy (POST tanpa ini jatuh ke SPA fallback → 419/404 — pola sama dgn
  `/register`).
- **Frontend**
  - Router: `{ path: '/change-password', name: 'change-password', component: ChangePasswordView, meta: { requiresAuth: true } }`.
  - `ChangePasswordView.vue` (3 field: current/new/confirm; sukses → pesan
    + redirect; 422 → tampilkan error field pertama — pola RegisterView).
  - Nav `App.vue`: link **"Ganti Password"** setelah Pesanan (tampil hanya
    saat login).
  - i18n id+en (`nav.changePassword`, `changePassword.*`).
  - `api.ts`: `spaChangePassword(current, password, passwordConfirmation)`
    → csrf-cookie + POST (pola `spaRegister`).
- **Test**
  - Backend `ChangePasswordTest` (~5): sukses 302 + `Hash::check` password
    baru; current salah → 422 + password lama tetap berlaku; password baru
    < 8 / tanpa konfirmasi → 422; current == new → 422; tanpa auth → 401/redirect;
    throttle 4× → 429.
  - Vitest `ChangePasswordView.spec` (~3): render 3 field + submit sukses;
    error 422 tampil; redirect ke login bila belum auth.
  - i18n spec: tambah key baru bila test-nya menghitung/mengecek key.

### B2. Auth logging (16.3.1 ⚠️, 16.3.2 ⚠️)

Semua **structured** (konsisten `checkout.completed`), payload aman
(email/user_id/ip/path — **tidak pernah** password/token):

- `LoginController::store`:
  - gagal → `Log::warning('auth.login_failed', [email, ip])` (sebelum `back()`)
  - sukses → `Log::info('auth.login', [user_id, email, ip])` (setelah regenerate)
- `LoginController::destroy` → `Log::info('auth.logout', [user_id, ip])`
- `bootstrap/app.php` render callback (yang sudah ada untuk 403/404):
  - 403 → `Log::warning('auth.denied', [path, user_id?])`
  - 401 → `Log::warning('auth.unauthenticated', [path])`
  (callback sudah dipanggil utk semua request; tambah log SEBELUM return match).
- **Test** `AuthLoggingTest` (~4) — pola `Log::spy()` dari StructuredLogTest:
  login salah → warning `auth.login_failed` berkonteks email;
  login benar → info `auth.login`; akses order orang lain (API) → warning
  `auth.denied`; assert TIDAK ada field `password` di context.

### B3. Throttle API v1 (2.4.1 ⚠️)

- `AppServiceProvider`: `RateLimiter::for('api', 120/menit ->by(user_id ?: ip))`
  — 120/menit: cukup ketat utk ASVS, longgar utk e2e (≈40 request/run) &
  retry CI (×3 masih aman).
- `routes/api.php`: group `v1` → tambah `'throttle:api'` ke daftar middleware
  (satu baris; endpoint spesifik — checkout 10, login 5, register 3 — TETAP
  lebih ketat karena middleware route menang/kumulatif).
- **Test** (~1): 121× `getJson('/api/v1/products')` (actingAs) → request
  ke-121 = 429; 1 request normal tetap 200.

### Docs (ikut commit 2)

- `ASVS_AUDIT.md`: flip 6.2.2 → ✅, 6.4.3 → ⚠️ (reset+ubah ✅, MFA ❌),
  16.3.1/16.3.2 → ✅, 2.4.1 → ✅; coret butir 4/5/6 di daftar prioritas
  (beri catatan sisa: MFA).
- `FEATURES.md`: section **J. Ubah password & hardening auth** (langkah tes
  manual: ganti password dari nav, lihat log `auth.*`).
- `PROGRESS.md`: 1 baris "ASVS lanjutan (CI e2e + quick wins)" + update
  jumlah test.
- `SCOPE.md`: catatan singkat bahwa quick-win lanjutan ini di luar scope
  asli tapi menutup gap ASVS tersisa.

---

## Urutan eksekusi & verifikasi (per commit)

```bash
# Fase A
python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/ci.yml'))"
cd frontend && npx playwright test   # 16/16 (prasyarat: reseed + cache:clear)

# Fase B
php artisan test                     # 182 + ~10 baru
cd frontend && npm test              # 68 + ~3 baru
npx --prefix frontend vue-tsc --noEmit -p frontend && npm run lint
cd frontend && npx playwright test   # pastikan throttle tak mengganggu e2e
```

## Risiko & mitigasi

1. **CI e2e gagal pertama kali** (tak bisa diuji penuh lokal) → parse YAML +
   tiru pola job existing persis; upload trace saat failure untuk debug cepat.
2. **Throttle 120 kena e2e/CI retry** → hitungan terukur (≈40/run, serial),
   angka 120 sengaja dipilih 3× margin; kalau ternyata mepet, naikkan ke 240.
3. **Proxy lupa** `/change-password` → bug silent (419) — masuk checklist
   verifikasi: uji submit form dari browser/e2e manual.
4. **Nav mobile** bertambah 1 item — cek 375px (aturan lama di PROGRESS).
