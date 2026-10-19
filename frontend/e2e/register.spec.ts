import { expect, test } from '@playwright/test'

// T1 — Register: POST /register (session) → AUTO-LOGIN → notice verifikasi.
// Project `anon` (tanpa storageState) — register limiter 3/menit/IP,
// serial workers:1 menjaga spec register lain (password-reset) tak tabrakan.

test('register → auto-login → pindah ke notice verifikasi email', async ({ page }) => {
  const email = `e2e-register-${Date.now()}@example.com`

  await page.goto('/register')
  await page.locator('input[autocomplete="name"]').fill('E2E Register')
  await page.locator('input[autocomplete="email"]').fill(email)
  await page.locator('input[autocomplete="new-password"]').fill('password123')
  await page.locator('button[type="submit"]').click()

  // Auto-login: guard verify-email (requiresAuth) lolos → user memang sudah login
  await expect(page).toHaveURL(/\/verify-email$/)
  // Nav memperlihatkan nama user (session aktif)
  await expect(page.getByText('E2E Register')).toBeVisible()
})
