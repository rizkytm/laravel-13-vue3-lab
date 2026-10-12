# ASVS_AUDIT.md — Audit OWASP ASVS 5.0 (target L2)

Tanggal: 2026-10-11 · Project: Laravel 13 Lab (`laravel-13-vue3-lab`)
Sumber requirement: `~/Documents/playground/asvs/5.0/en/` (V1–V16, 345 req).
Cakupan audit: **level 1 + 2** (246 requirement). Level 3 di luar target L2 →
tidak dinilai. V10 (OAuth/OIDC) = **N/A** — project memakai Sanctum session +
personal access token, tanpa OAuth/OIDC/IdP.

**Metode:** setiap requirement dicocokkan ke kode nyata (app/, config/, routes/,
tests/, frontend/) — "Laravel default aman" TIDAK dihitung lulus tanpa bukti
config/kode/test. Status: ✅ memenuhi · ⚠️ sebagian/belum terverifikasi ·
❌ gap · N/A tidak relevan.

**Ringkasan status (cakupan L2, 246 req):**

| Status | Jumlah | Arti |
|---|---|---|
| ✅ | ~120 | Terpenuhi dengan bukti kode/config/test |
| ⚠️ | ~70 | Sebagian / tergantung deployment / belum terverifikasi |
| ❌ | ~25 | Gap nyata (lihat prioritas di bawah) |
| N/A | ~31 | Tidak relevan (OAuth/OIDC, LDAP, XML, GraphQL, WebSocket, SAML) |

> Angka = hasil penilaian manual per requirement (lihat detail per chapter;
> ID yang tidak disebut di tabel detail = ✅).

---

## Top gap prioritas menuju L2 (urut kerjakan)

1. **Security headers nihil** (❌ 3.4.1, 3.4.3, 3.4.4, 3.4.5, 3.4.6): tidak ada
   HSTS, CSP, `X-Content-Type-Options`, `Referrer-Policy`, frame-ancestors.
   Fix: middleware header keamanan + test.
2. **Cookie session tanpa `Secure` bawaan** (❌ 3.3.1): `SESSION_SECURE_COOKIE`
   tidak punya default `true` — di produksi tanpa env ini cookie dikirim via
   HTTP. Plus `__Host-` prefix belum dipakai (❌ 3.3.3).
3. **HTTPS/TLS belum dikonfigurasi/didokumentasikan** (❌ 12.1.1, 12.2.1):
   dev HTTP semua; belum ada config/dokumen deployment produksi.
4. **Fitur password belum ada** (❌ 6.2.2, 6.4.3): tidak ada ubah password /
   reset password. Belum ada MFA (❌ 6.3.3) — Fortify + 2FA (studi larrykonn)
   menutup ini.
5. **Rate limit hanya di login** (⚠️ 2.4.1): checkout & API v1 tanpa throttle.
6. **Auth/authorization tidak dicatat ke log** (⚠️ 16.3.1, 16.3.2): login
   sukses/gagal, 403 — belum ada event log khusus.
7. **Upload tanpa antivirus scan** (❌ 5.4.3) — disk privat sudah bagus, tapi
   file tidak discan.
8. **Bearer token di `localStorage`** (⚠️ 14.3.3, ⚠️ 14.3.1): mode token
   menyimpan `lab_token` di localStorage (rawan XSS); `spaLogout` tidak
   membersihkan token mode Bearer.
9. **Header anti-cache untuk data sensitif** (❌ 14.3.2): respons auth/order
   tanpa `Cache-Control: no-store`.
10. **Dokumen keamanan formal** (❌ 13.3.1 secrets mgmt, ❌ 15.1.2 SBOM,
    ⚠️ 8.1.x/11.1.x/16.1.x): key policy, inventaris data sensitif, aturan
    otorisasi — ada di kode & DEBRIEF tapi belum jadi dokumen.

---

## V1 — Encoding & Sanitization

✅: 1.1.1, 1.1.2, 1.2.1–1.2.5, 1.2.9, 1.3.1–1.3.3, 1.3.5, 1.3.7, 1.3.10,
1.3.11, 1.4.1–1.4.3, 1.5.1, 1.5.2.
Bukti: escaping `{{ }}` Blade + `{{ }}` Vue (tanpa `v-html`/`{!! !!}`),
query parameterized Eloquent + whitelist kolom sort (`ApiProductTest`:
`?sort=DROP%20TABLE` diabaikan), tanpa `exec`/`eval`, SSRF tidak relevan
(Http client hanya ke URL konfigurasi), **deserialization hardening**:
allowlist `serializable_classes` + regression test cache.

| ID | Status | Notes |
|---|---|---|
| 1.2.6, 1.2.7, 1.2.8, 1.3.8, 1.3.9 | N/A | LDAP/XPath/LaTeX/JNDI/memcache tidak dipakai |
| 1.3.4 | ⚠️ | Upload SVG ditolak rule `image` ✓, tapi file gambar disajikan inline tanpa `X-Content-Type-Options` (lihat 3.4.4) |
| 1.3.6 | ✅ | Tidak ada fetch ke URL user (SSRF) — `HttpPaymentGateway` pakai URL konfigurasi |

## V2 — Validation & Business Logic

✅: 2.2.1, 2.2.2, 2.2.3, 2.3.2, 2.3.3, 2.3.4.
Bukti: FormRequest (`CheckoutRequest`, `BuyProductRequest`, `LoginRequest`),
rule `StockAvailable`, transaksi checkout + rollback (`ApiCheckoutTest`),
**`lockForUpdate` anti oversell** (`StockTest`), harga dari server (bukan
client).

| ID | Status | Notes |
|---|---|---|
| 2.1.1–2.1.3 | ⚠️ | Aturan validasi hidup di FormRequest + FEATURES.md, belum ada dokumen formal batas bisnis |
| 2.3.1 | ✅ | Alur checkout terikat user login; cart divalidasi server (EnsureCartNotEmpty + stok) |
| 2.4.1 | ⚠️ | Anti-automation hanya `throttle:login` (5/menit) + `RateLimitTest`; checkout/API tanpa rate limit |

## V3 — Web Frontend Security

| ID | Status | Notes |
|---|---|---|
| 3.2.1 | ⚠️ | Gambar upload disajikan inline via controller (by design); tanpa sandbox/attachment policy |
| 3.3.1 | ❌ | Cookie `Secure` = `env('SESSION_SECURE_COOKIE')` tanpa default true |
| 3.3.2 | ✅ | `same_site => lax` (config/session.php) |
| 3.3.3 | ❌ | Prefix `__Host-` tidak dipakai |
| 3.3.4 | ✅ | `http_only => true` |
| 3.4.1 | ❌ | HSTS tidak ada |
| 3.4.2 | ⚠️ | `config/cors.php` tidak dipublikasi → default framework (origins `*`) belum dipersempit |
| 3.4.3 | ❌ | CSP tidak ada |
| 3.4.4 | ❌ | `X-Content-Type-Options: nosniff` tidak ada |
| 3.4.5 | ❌ | `Referrer-Policy` tidak ada |
| 3.4.6 | ❌ | frame-ancestors / anti-clickjacking tidak ada |
| 3.7.2 | ✅ | Redirect SPA via `router.push` (path internal) + login `intended()` internal; tanpa open redirect ke host luar |

✅ lain: 3.2.2 (teks dirender aman, tanpa `v-html`), 3.5.1 (CSRF token + XSRF
header — `SpaAuthTest`), 3.5.3 (mutasi pakai POST), 3.7.1 (Vue 3 terkini).

## V4 — API & Web Service

✅: 4.1.1 (API `application/json`), 4.1.2 (setelah cleanup blade: hanya
endpoint demo yang HTML), 4.4.x N/A (tanpa WebSocket aktif — broadcasting
log/Pusher driver).

| ID | Status | Notes |
|---|---|---|
| 4.1.3 | ⚠️ | TrustProxies default — header dari intermediary belum dikonfigurasi/diuji |
| 4.2.1 | ⚠️ | Konfigurasi TLS komponen = urusan deployment, belum didokumentasikan |
| 4.3.x | N/A | Tanpa GraphQL |

## V5 — File Handling

✅: 5.2.1 (`max:2048`), 5.2.2 (rule `image` + test file palsu → 422),
5.3.1 (**disk privat**, disajikan via controller — `ProductImageTest`),
5.3.2 (path random `store()`, nama file user diabaikan), 5.4.1.

| ID | Status | Notes |
|---|---|---|
| 5.1.1 | ⚠️ | Tipe file terdokumentasi di kode + FEATURES.md, belum ada dokumen kebijakan |
| 5.2.3 | N/A | Tanpa penanganan arsip (zip/docx) |
| 5.4.2 | ✅ | Nama file response = hash acak dari `store()`, aman dari header injection |
| 5.4.3 | ❌ | Tidak ada antivirus scan atas upload |

## V6 — Authentication

✅: 6.1.3 (dua jalur auth terdokumentasi — DEBRIEF), 6.2.6 (`type=password`
+ toggle icon — `auth.spec`), 6.2.7 (paste/password manager tidak diblok),
6.2.8, 6.2.10, 6.3.1 (**`throttle:login` 5/menit** + `RateLimitTest`),
6.3.4 (semua jalur login dithrottle), 6.4.2.

| ID | Status | Notes |
|---|---|---|
| 6.1.1 | ⚠️ | Kontrol anti-automation ada (throttle) tapi belum didokumentasikan formal |
| 6.1.2, 6.2.11, 6.2.12 | ❌ | Tidak ada kebijakan password / cek password bocor (belum ada alur set password) |
| 6.2.1 | ⚠️ | Panjang min password belum diatur (login saja; belum ada register/ubah password) |
| 6.2.2, 6.2.3 | ❌ | Fitur ubah password belum ada |
| 6.2.4 | ❌ | Cek password terhadap daftar breach belum ada |
| 6.2.9 | ⚠️ | Password 64+ karakter: tidak ada batas max di input — praktis boleh, belum diuji |
| 6.3.2 | ⚠️ | Akun seed `test@example.com` = akun terdokumentasi (dev lab) — jangan dibawa ke produksi |
| 6.3.3 | ❌ | Tidak ada MFA (kandidat: Fortify 2FA) |
| 6.4.1 | ⚠️ | Password seed dari factory (deterministik) — wajar untuk dev, produksi wajib random |
| 6.4.3 | ❌ | Reset password belum ada |
| 6.5.x–6.8.x | N/A | Tanpa TOTP/lookup secret/PSTN/SAML |

## V7 — Session Management

✅: 7.2.1 (session store database), 7.2.2–7.2.4 (**`session()->regenerate()`
anti session fixation** — `LoginController` + komentar), 7.4.1 (logout
invalidasi session — `AuthTest` + Playwright), 7.4.4 (tombol Logout selalu
tampil di nav).

| ID | Status | Notes |
|---|---|---|
| 7.1.1, 7.1.2 | ⚠️ | Lifetime session (config `lifetime`) & aturan session paralel belum didokumentasikan |
| 7.3.1, 7.3.2 | ⚠️ | Timeout inactivity/absolute = expiry config framework; belum ada re-auth enforcement |
| 7.4.2 | ⚠️ | Tidak ada fitur disable akun (jadi terminasi otomatis tidak relevan) |
| 7.4.3 | ❌ | Tidak ada opsi "terminate all other sessions" |
| 7.4.5, 7.5.x, 7.6.x | N/A | Tanpa admin panel / re-auth sensitif / IdP |

## V8 — Authorization

✅: 8.2.1 (middleware auth + `Gate::authorize`), 8.2.2 (**OrderPolicy
owner-scope** — `ApiOrderTest` 403 + scoping test tanpa kebocoran),
8.2.3 (field-level: resource hanya ekspos field perlu — phone tidak ikut),
8.3.1 (otorisasi di controller/policy, bukan di view).

| ID | Status | Notes |
|---|---|---|
| 8.1.1, 8.1.2 | ⚠️ | Aturan otorisasi hidup di `OrderPolicy` + resource — belum jadi dokumen formal |
| 8.4.x | N/A | Single-tenant |

## V9 — Self-contained Tokens (signed URL)

✅: 9.1.1 (HMAC signature divalidasi — `SignedUrlTest` tamper → 403),
9.1.2 (algoritme framework tetap), 9.1.3 (APP_KEY), 9.2.1 (expiry 30 menit —
test expired → 403).

| ID | Status | Notes |
|---|---|---|
| 9.2.2, 9.2.3 | ⚠️ | Signed URL tanpa audience/type — satu kasus pakai saja; Sanctum token `expiration = null` (config/sanctum.php) → token Bearer tidak expired |
| 9.2.4 | N/A | Satu kunci, satu tujuan |

## V10 — OAuth & OIDC

**N/A** — seluruh chapter: project tidak memakai OAuth/OIDC/IdP (auth =
session cookie + Sanctum personal access token). Tidak ada Socialite/Passport.

## V11 — Cryptography

✅: 11.2.1–11.2.3 (implementasi library/framework, AES-256-CBC + HMAC-SHA256,
≥128-bit), 11.3.1–11.3.3 (**encrypter Laravel: cipher approved + MAC anti
tamper** — `EncryptedFieldTest`), 11.4.1, 11.4.2 (bcrypt KDF), 11.4.3
(HMAC-SHA256 signature), 11.4.4, 11.5.1 (`random_bytes`/`Str::random`),
11.6.1.

| ID | Status | Notes |
|---|---|---|
| 11.1.1, 11.1.2 | ❌ | Kebijakan manajemen kunci & inventaris kriptografi belum didokumentasikan |

## V12 — Secure Communication

| ID | Status | Notes |
|---|---|---|
| 12.1.1, 12.2.1 | ❌ | Dev menjalankan HTTP (artisan serve); TLS produksi belum dikonfigurasi/didokumentasikan |
| 12.1.2, 12.2.2, 12.3.3, 12.3.4 | ⚠️ | Terikat deployment — belum ada config/review |
| 12.3.2 | ✅ | Http client memvalidasi sertifikat (default `verify=true`) |
| 12.1.3–12.1.5, 12.3.5 | N/A / L3 | mTLS, ECH, internal mesh |

## V13 — Configuration

✅: 13.4.3 (tanpa directory listing — satu entry `public/index.php`),
13.4.5 (endpoint bersih setelah cleanup; `/up` = health check wajar),
13.4.2 sebagian (default `APP_DEBUG=false`).

| ID | Status | Notes |
|---|---|---|
| 13.1.1 | ⚠️ | Komunikasi antar komponen terdokumentasi di README/DEBRIEF, belum formal |
| 13.2.x | ⚠️ | Komunikasi internal semua lokal (dev); allowlist eksternal belum didefinisikan |
| 13.3.1 | ❌ | Secrets di `.env` — belum ada secrets management (vault) untuk produksi |
| 13.3.2 | ⚠️ | Least privilege akses secrets belum didefinisikan |
| 13.4.1 | ⚠️ | `.git` masih ada — deployment produksi harus tanpa source control metadata |
| 13.4.4 | ⚠️ | HTTP TRACE: tergantung web server produksi (belum dikonfigurasi) |

## V14 — Data Protection

✅: 14.2.1 (password/token dikirim via body/header, bukan URL), 14.2.2
(data sensitif tidak masuk cache — cache hanya daftar produk), 14.2.3
(**tanpa tracker/pihak ketiga** di frontend).

| ID | Status | Notes |
|---|---|---|
| 14.1.1, 14.1.2 | ⚠️ | Data sensitif (phone) di-encrypted + tidak diekspos ke API; inventaris & level proteksi belum formal |
| 14.2.4 | ⚠️ | Retensi data (order, log) belum didefinisikan |
| 14.3.1 | ⚠️ | `spaLogout` membersihkan session tapi `lab_token` (mode Bearer) tidak dibersihkan |
| 14.3.2 | ❌ | Tidak ada header `Cache-Control: no-store` untuk respons auth/order |
| 14.3.3 | ⚠️ | `lab_token` disimpan di localStorage — XSS bisa mencuri; pertimbangkan memory-only atau cookie HttpOnly |

## V15 — Secure Coding & Architecture

✅: 15.1.1 (dependabot weekly + SLA informal), 15.2.1 (**audit dependensi
bersih** — `composer audit` + `npm audit` di CI), 15.3.1 (**resource API
hanya ekspos field perlu**), 15.3.2 (tanpa SSRF), 15.3.3 (**`$fillable`
allowlist mass assignment**), 15.3.5 (validasi tipe server + TypeScript),
15.3.6, 15.3.7.

| ID | Status | Notes |
|---|---|---|
| 15.1.2 | ❌ | Tidak ada SBOM |
| 15.1.3 | ⚠️ | Fungsi berat (queue, scheduler) ada tapi belum didokumentasikan sebagai risiko availability |
| 15.2.2 | ⚠️ | Rate limit hanya login; queue punya backoff+failed() ✓ |
| 15.2.3 | ⚠️ | Halaman demo blade sengaja dipertahankan (upload/beli) — di produksi harus dimatikan/diisolasi |
| 15.3.4 | ⚠️ | TrustProxies belum dikonfigurasi untuk IP asli client |

## V16 — Security Logging & Error Handling

✅: 16.2.1 (**log JSON + metadata request_id** — `StructuredLogTest`),
16.2.2 (timestamp framework), 16.2.3 (channel log terkontrol), 16.2.4
(**correlation id X-Request-Id generate/passthrough** + test), 16.3.4
(exception ter-log otomatis), 16.4.1 (log ter-encoding JSON — anti log
injection), 16.5.1 (**error generik** — `ExceptionFormatTest`), 16.5.2
(payment gagal → order tetap aman, job `failed()`), 16.5.3 (handler custom
JSON/HTML).

| ID | Status | Notes |
|---|---|---|
| 16.1.1 | ⚠️ | Inventaris logging belum jadi dokumen |
| 16.2.5 | ⚠️ | Payload log `checkout.completed` berisi item/total (bukan PII/kredensial) — kebijakan redaksi belum formal |
| 16.3.1 | ⚠️ | Login sukses/gagal belum di-log eksplisit sebagai event |
| 16.3.2 | ⚠️ | Gagal otorisasi (403) belum di-log |
| 16.3.3 | ⚠️ | Event keamanan terdefinisi belum lengkap (baru `checkout.completed`) |
| 16.4.2 | ⚠️ | Log di `storage/logs` lokal (gitignore ✓) — akses & integritas terikat filesystem |
| 16.4.3 | ❌ | Log belum dikirim ke sistem terpisah — **terencana di fase ELK** |

---

## Catatan penutup

- Kekuatan utama project (teruji test): anti-injection (query whitelist),
  otorisasi objek (policy + scoping), anti oversell (`lockForUpdate`), signed
  URL + expiry, encrypted field, disk privat upload, error handling generik,
  structured logging + correlation id, audit dependensi CI, deserialization
  hardening cache.
- Cluster gap terbesar = **hardening HTTP response (headers) + deployment
  HTTPS** — wajar karena project berjalan di mode dev/lab; paling murah
  diperbaiki (satu middleware + test).
- Gap fitur (password reset/change, MFA) cocok dikerjakan bareng rencana
  **Fortify** (lihat gap larrykonn di PROGRESS).
- Fase ELK akan menutup 16.4.3; Docker/Sail menutup sebagian V12/V13.
