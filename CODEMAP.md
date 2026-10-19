# CODEMAP — Panduan Urutan Baca File per Fitur

Companion dari `FEATURES.md` (di sana: cara kerja + langkah tes manual).
File ini: **urutan MENGECEK file** — dari fondasi ke fitur, tiap grup
berurutan dependency-aware. Centang `[ ]` saat sudah dibaca.

Konvensi: **backend → frontend → test** per fitur. Test adalah "dokumen
ekspektasi" — baca setelah kode, jalankan untuk melihat perilaku.

```bash
# Verifikasi per fitur (ganti FILTER):
php artisan test --filter=NamaTest
cd frontend && npx vitest run tests/nama.spec.js
```

---

## 0. Entry points (baca sekali di awal)

- [ ] `README.md` — setup & struktur singkat
- [ ] `SCOPE.md` — peta fitur + urutan tahap T1–T8 + keputusan/deviasi
- [ ] `routes/web.php` + `routes/api.php` — daftar endpoint = peta permukaan aplikasi
- [ ] `bootstrap/app.php` — registrasi middleware global (security headers, auth
      logging), routing, handler 401/403/404

## 1. Fondasi backend (container, config, migrasi)

- [ ] `composer.json` — dependensi (Laravel 13, Filament 4, Pest)
- [ ] `app/Providers/AppServiceProvider.php` — binding `PaymentGateway` (DIP)
      + **semua rate limiter** (login/register/password/checkout/change-password/api)
- [ ] `config/payment.php`, `config/session.php`, `config/cors.php`, `config/app.php`
      (`spa_url`) — konfigurasi keputusan
- [ ] `database/migrations/` — baca urut kronologis; tabel kunci: `users` →
      `products` → `inventory_movements` → `cart_items` → `orders` + `order_items`
      → `add_is_admin_to_users`
- [ ] `database/seeders/DatabaseSeeder.php` — kontrak data test (admin + 6 order)
- [ ] `database/factories/` — `ProductFactory` (stok 1–50), `OrderFactory` (pending)
- [ ] `app/Http/Controllers/Controller.php` — titik awal semua controller

**Test:** `php artisan test --filter=ContainerBindingTest`

## 2. Domain inti: status machine + inventory (inti seluruh app)

> Semua fitur lain (payment, refund, cancel, admin) BERDIRI DI ATAS dua file ini.

- [ ] `app/Enums/OrderStatus.php` — `canTransitionTo()`: SATU-satunya daftar
      transisi sah (pending→paid/cancelled/failed, paid→refunded)
- [ ] `app/Models/Order.php` — `transitionTo()` (gerbang), `cancel()` +
      `refund()` (transisi + restock atomik)
- [ ] `app/Services/InventoryService.php` — satu-satunya jalan ubah stok;
      setiap perubahan tulis `inventory_movements`; invariant SUM == stock
- [ ] `app/Models/InventoryMovement.php` — jejak audit (in/out/adjust, signed delta)
- [ ] `app/Models/Product.php` — `buy()` → InventoryService; hook `created`
      (stok awal = movement pertama); `bumpCacheVersion()`

**Test:** `StatusMachineTest`, `InventoryMovementTest`, `StockTest`
→ lanjut `app/Http/Controllers/ProductController.php` + `app/Http/Resources/ProductResource.php`
   (cache list + version stamp) + `ProductCacheTest`.

## 3. Autentikasi — backend (session, token, verifikasi, ganti password)

- [ ] `app/Http/Requests/LoginRequest.php` — pola FormRequest + messages
- [ ] `app/Http/Controllers/LoginController.php` — attempt, regenerate session
      (anti-fixation), **log `auth.login`/`login_failed`/`logout`**
- [ ] `app/Http/Controllers/RegisterController.php` — auto-login + kirim verifikasi
- [ ] `app/Http/Controllers/ForgotPasswordController.php` — respons generik 202
      (anti user-enumeration)
- [ ] `app/Http/Controllers/ResetPasswordController.php` — broker token hashed,
      60 menit, single-use, auto-login
- [ ] `app/Http/Controllers/VerifyEmailController.php` — signed URL + idempoten
- [ ] `app/Http/Requests/ChangePasswordRequest.php` +
      `app/Http/Controllers/ChangePasswordController.php` — `current_password`,
      policy, session regenerate
- [ ] `app/Models/User.php` — casts (`hashed`, `encrypted` phone), `MustVerifyEmail`,
      `FilamentUser::canAccessPanel()`, relasi `orders`/`cartItems`
- [ ] `app/Http/Controllers/Api/TokenController.php` — Sanctum personal access token

**Test:** `AuthTest`, `RegisterTest`, `PasswordResetTest`, `EmailVerificationTest`,
`ChangePasswordTest`, `SanctumTest`, `RateLimitTest`

## 4. Autentikasi — frontend (SPA session via proxy Vite)

- [ ] `frontend/vite.config.js` — daftar proxy (login/register/reset/email/…)
      + `spaAuthRoutes` (GET vs POST dibedakan) — akar banyak bug SPA
- [ ] `frontend/src/api.ts` — `xsrfToken`, `csrf()`, `parseOrThrow` (bentuk
      `{message, errors}`), `spaPost` (`redirect:'manual'`), `api.raw` (prefix v1)
- [ ] `frontend/src/stores/auth.ts` — guard async (`fetchUser`), register/logout
- [ ] `frontend/src/router/index.ts` — guard `requiresAuth` + `guestOnly`
- [ ] `frontend/src/App.vue` — fetch user saat boot, sinkron cart per login,
      nav (termasuk link Ganti Password)
- [ ] Views: `LoginView.vue` → `RegisterView.vue` → `ForgotPasswordView.vue` →
      `ResetPasswordView.vue` → `VerifyEmailView.vue` → `ChangePasswordView.vue`
      (pola sama: ref, submit, error `{message, errors}`, i18n `t()`)

**Test:** `SpaAuthTest` (backend) · `auth.spec.js`, `AuthViews.spec.js`,
`ChangePasswordView.spec.js` (vitest) · `e2e/auth.spec.ts`, `e2e/register.spec.ts`,
`e2e/password-reset.spec.ts` (Playwright — login asli ada di project `anon`)

## 5. Keranjang server-side + checkout

- [ ] `app/Http/Controllers/Api/CartController.php` — additive POST, absolute
      PATCH, clamp stok oleh server
- [ ] `app/Http/Requests/CartItemRequest.php` — validasi per item
- [ ] `app/Http/Middleware/EnsureCartNotEmpty.php` — cek DB cart, bukan payload
- [ ] `app/Http/Controllers/Api/CheckoutController.php` — SATU transaksi: order +
      items + `buy()` + **kosongkan cart di dalam transaksi** (rollback = cart
      kembali); event SETELAH commit; dispatch ProcessPayment
- [ ] `frontend/src/stores/cart.ts` — async API, state di-replace dari respons
      server; `applyStockUpdate()` (clamp realtime)
- [ ] `frontend/src/views/CartView.vue` → `ProductDetailView.vue` (add-to-cart)

**Test:** `CartTest`, `ApiCheckoutTest` · e2e: `cart.spec.ts` (inti: reload tetap)

## 6. Payment gateway + refund (integrasi eksternal)

- [ ] `app/Services/Payment/PaymentGateway.php` — kontrak (DIP) — mulai dari sini
- [ ] `app/Services/Payment/FakePaymentGateway.php` — implementasi dev/test
- [ ] `app/Services/Payment/HttpPaymentGateway.php` — provider generik (Http::fake-able)
- [ ] `app/Jobs/ProcessPayment.php` — idempotency, backoff, `failed()`; **cek
      `instanceof AsyncPaymentGateway`** → JANGAN tandai paid (webhook yang akan)
- [ ] `app/Services/Payment/AsyncPaymentGateway.php` — marker (Midtrans dsb)
- [ ] `app/Services/Payment/MidtransPaymentGateway.php` — Snap `createTransaction`
      (Http::fake), `verifySignature` (sha512+hash_equals), `refund()`
- [ ] `app/Http/Controllers/PaymentWebhookController.php` — signature check
      SEBELUM DB → map status → `transitionTo()` → idempoten
- [ ] `app/Services/Payment/RefundablePaymentGateway.php` (ISP) +
      `app/Services/RefundService.php` — **uang dulu, status sesudah**
- [ ] `config/payment.php` — driver + kredensial Midtrans

**Test:** `PaymentGatewayTest`, `ProcessPaymentTest`, `MidtransWebhookTest`,
`RefundTest`

## 7. Events, queue, notification, scheduler

- [ ] `app/Events/OrderPlaced.php` → `app/Listeners/SendOrderConfirmation.php`
      → `app/Notifications/OrderConfirmation.php` (mail=log driver + database)
- [ ] `app/Events/StockUpdated.php` — `ShouldBroadcast`, after-commit
- [ ] `app/Console/Commands/CleanupStaleOrders.php` + `routes/console.php`
      (schedule harian)

**Test:** `OrderFlowTest`, `BroadcastTest` · manual: `php artisan queue:work`,
`php artisan orders:cleanup --hours=24`

## 8. Order API + cancel customer (T8)

- [ ] `app/Http/Controllers/Api/OrderController.php` — cursor pagination,
      `show` (policy), `cancel` (policy → `Order::cancel()`)
- [ ] `app/Policies/OrderPolicy.php` — view/update/cancel (pemilik + pending)
- [ ] `app/Http/Resources/OrderResource.php` — `when`/`whenLoaded`, `invoice_url`
- [ ] `frontend/src/views/OrdersView.vue` → `OrderDetailView.vue`
      (tombol cancel hanya saat `pending`)

**Test:** `ApiOrderTest`, `OrderPolicyTest`, `CancelOrderTest` ·
e2e: `cancel-order.spec.ts`

## 9. Admin Filament (T6)

- [ ] `app/Providers/Filament/AdminPanelProvider.php` — path `/admin`, middleware,
      widget (masih default — lihat catatan SCOPE)
- [ ] `app/Models/User.php::canAccessPanel()` + migration `add_is_admin` — gerbang
- [ ] `app/Filament/Resources/Orders/OrderResource.php` +
      `Tables/OrdersTable.php` — aksi **Batalkan/Refund memanggil METHOD DOMAIN**
      (bukan edit kolom status bebas); `Schemas/OrderForm.php` (status read-only)
- [ ] `app/Filament/Resources/Products/` — stok read-only (placeholder)
- [ ] `app/Filament/Resources/Users/` — toggle `is_admin`, phone tak bisa di-SQL-search

**Test:** `FilamentAdminTest` · e2e: `admin.spec.ts` (403 non-admin + refund flow)

## 10. Realtime stok (T7 — catatan: transport no-op tanpa Reverb)

- [ ] `frontend/src/realtime.ts` — `subscribeStockUpdates` (graceful no-op),
      titik sambung `window.__BROADCAST__` utk Reverb/Pusher
- [ ] `frontend/src/stores/cart.ts::applyStockUpdate()` — clamp qty lokal
- [ ] `frontend/src/App.vue` — wiring saat login (watch auth.user)

**Test:** `frontend/tests/realtime.spec.js` (6 — pakai broadcaster tiruan)

## 11. Keamanan: headers + audit log (hardening H + ASVS lanjutan)

- [ ] `app/Http/Middleware/AddSecurityHeaders.php` — HSTS/nosniff/frame-ancestors/
      CSP **tanpa script-src** (alasan: Filament inline-script tanpa nonce) /
      `Cache-Control: no-store`
- [ ] `config/cors.php` — origin ketat (ASVS 3.4.2)
- [ ] `bootstrap/app.php` — render callback: `auth.denied` (403) +
      `auth.unauthenticated` (callback `AuthenticationException` TERPISAH —
      callback HttpException tak dipanggil utk 401)
- [ ] `app/Http/Middleware/AssignRequestId.php` + `SetLocale.php` — middleware
      web group (context log, i18n `?lang=`)
- [ ] `app/Http/Middleware/EnsureCartNotEmpty.php` (contoh middleware custom lain)

**Test:** `SecurityHeadersTest`, `AuthLoggingTest`, `ApiRateLimitTest`,
`StructuredLogTest`, `LocaleTest`, `ExceptionFormatTest`

## 12. Blade demo (fitur framework yang tak terlihat di SPA)

- [ ] `resources/views/products/show.blade.php` + `ProductController::buy/uploadImage`
      — lockForUpdate demo + upload disk privat
- [ ] `resources/views/orders/invoice.blade.php` + `OrderController::invoice`
      — signed URL (30 menit) + Carbon locale + phone ter-decrypt
- [ ] `resources/views/auth/login.blade.php` — login blade (dipakai lewat proxy)

**Test:** `ProductImageTest`, `SignedUrlTest`, `EncryptedFieldTest`, `OrdersPageTest`

## 13. Infra: testing + e2e + CI

- [ ] `phpunit.xml` — env test (SQLite, cache array?)
- [ ] `tests/Feature/` — konvensi: 1 file = 1 fitur, `RefreshDatabase`,
      pattern `Log::spy()`, `Http::fake()`, `Sanctum::actingAs`
- [ ] `frontend/playwright.config.ts` — **3 project (setup/anon/storageState)**,
      `workers:1` (cart user sama = race kalau paralel), webServer auto-start
- [ ] `frontend/e2e/setup/auth.setup.ts` + `helpers.ts` — login sekali →
      `playwright-auth.json` (hindari 429 rate limit 5/menit)
- [ ] `.github/workflows/ci.yml` — jobs: changes (path filter) → backend
      (pint+test+audit) / frontend (lint+typecheck+vitest+audit) / e2e

**Jalankan:** `make test` · e2e: lihat prasyarat `FEATURES.md` section I

## 14. Frontend lintas fitur (baca setelah semua fitur)

- [ ] `frontend/src/types.ts` — kontrak tipe API
- [ ] `frontend/src/composables/useApi.ts` + `useProducts.ts` — pola generik
- [ ] `frontend/src/i18n/index.ts` — struktur id/en, kunci per view
- [ ] `frontend/src/style.css` — token Tailwind v4 (OKLCH)
- [ ] `frontend/src/components/ProductCard.vue`

## 15. Docs (urutan baca untuk konsolidasi / interview)

1. `SCOPE.md` — apa yang disepakati + keputusan deviasi (cara berpikir scope)
2. `FEATURES.md` A–J — per fitur: cara kerja → kode → tes manual
3. `DEBRIEF.md` — pertanyaan + jawaban per milestone (bahan interview)
4. `ASVS_AUDIT.md` — cara audit keamanan terhadap checklist nyata
5. `ISSUES.md` — investigasi bug asli (jejak debug = pelajaran paling berharga)
6. `PROGRESS.md` — riwayat keputusan; `PLAN.md`/`PLAN-CI-ASVS.md` — contoh
   rencana implementasi sebelum coding

---

> **Tips:** jangan baca berurutan linier 0→15 dalam sekali duduk. Pilih 1
> fitur → baca grupnya → jalankan test-nya → ubah sesuatu kecil → jalankan lagi.
> Grup 2 (domain inti) adalah fondasi — kuasai dulu sebelum grup lain.
