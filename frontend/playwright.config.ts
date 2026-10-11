import { defineConfig, devices } from '@playwright/test'

/**
 * Playwright — E2E lintas-backend (Laravel) + frontend (Vue).
 * webServer array: biarkan Playwright menyalakan keduanya (atau reuse yang
 * sudah jalan saat development). Precondition: DB sudah di-seed
 * (php artisan migrate:fresh --seed) — akun test@example.com / password.
 */
export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  fullyParallel: true,
  retries: process.env.CI ? 2 : 0,
  reporter: [['list']],
  use: {
    baseURL: 'http://localhost:5173',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: [
    {
      command: 'php artisan serve --port=8000',
      cwd: '..',
      // '/' sengaja tidak ada lagi (halaman web dibersihkan) — check pakai /login
      url: 'http://127.0.0.1:8000/login',
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
