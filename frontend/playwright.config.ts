import { defineConfig } from '@playwright/test'

/**
 * Playwright — E2E lintas-backend (Laravel) + frontend (Vue).
 * webServer array: biarkan Playwright menyalakan keduanya (atau reuse yang
 * sudah jalan saat development). Prasyarat sebelum jalankan:
 *
 *   php artisan migrate:fresh --seed   # data: test@, admin@, 12 produk, 6 order
 *   php artisan cache:clear            # reset rate limiter (login 5/mnt, register 3/mnt)
 *   npm run test:e2e
 *
 * 3 PROJECT — cara kita menghindari 429 rate limit (pelajaran ISSUES.md):
 * - `setup` : login SEKALI → simpan cookie ke playwright-auth.json
 * - `anon`  : spec tanpa session (auth login/logout, register, reset) —
 *             cuma ini yang menghitung POST /login (batas 5/menit)
 * - `app`   : spec yang butuh login → pakai storageState dari `setup`,
 *             TANPA login ulang (menghemat kuota rate limit login)
 */
export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  // SERIAL (workers:1): orders/cart/cancel SEMUA memanipulasi cart milik
  // user yang sama (test@example.com via storageState) — paralel = race
  // (checkout spec menghabiskan cart milik cart spec). Serial juga membuat
  // hitungan rate limit deterministik (register 3/menit, login 5/menit).
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 2 : 0,
  reporter: [['list']],
  use: {
    baseURL: 'http://localhost:5173',
    trace: 'retain-on-failure',
  },
  projects: [
    // 1. Setup — wajib jalan duluan (login sekali, tulis storageState)
    {
      name: 'setup',
      testMatch: /auth\.setup\.ts/,
    },
    // 2. Guest — spec yang memang butuh state bersih / uji login asli
    {
      name: 'anon',
      testMatch: [
        '**/auth.spec.ts',
        '**/register.spec.ts',
        '**/password-reset.spec.ts',
        '**/security-headers.spec.ts',
      ],
    },
    // 3. Login — spec berjalan dengan session hasil setup (tanpa login ulang)
    {
      name: 'app',
      dependencies: ['setup'],
      testMatch: [
        '**/orders.spec.ts',
        '**/products.spec.ts',
        '**/cart.spec.ts',
        '**/cancel-order.spec.ts',
        '**/admin.spec.ts',
      ],
      use: { storageState: 'playwright-auth.json' },
    },
  ],
  webServer: [
    {
      command: 'php artisan serve --port=8000',
      cwd: '..',
      // Host localhost (bukan 127.0.0.1) — samakan dengan baseURL & SPA,
      // pelajaran ISSUES #8: cookie host-only, beda host = session beda.
      url: 'http://localhost:8000/login',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
    },
    {
      command: 'npm run dev',
      url: 'http://localhost:5173',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
    },
  ],
})
