import { expect, test } from '@playwright/test'
import { readFileSync, statSync } from 'node:fs'

// T1 — Password reset penuh dari browser: register user sendiri →
// forgot-password → ambil tautan reset dari LOG (mail = log driver, jadi
// link emang ada di storage/logs/laravel.log) → set password baru →
// auto-login.
//
// Kenapa register user sendiri (bukan test@example.com): mereset password
// user seed akan MENYIMPAN password baru → spec auth & setup run berikutnya
// gagal login. User e2e per-run = isolasi.

const LOG = new URL('../../storage/logs/laravel.log', import.meta.url)

test('forgot → tautan di log → reset password → auto-login', async ({ page }) => {
  const email = `e2e-reset-${Date.now()}@example.com`

  // 1. Register (user verified=false → setelah reset diarahkan ke verify-email)
  await page.goto('/register')
  await page.locator('input[autocomplete="name"]').fill('E2E Reset')
  await page.locator('input[autocomplete="email"]').fill(email)
  await page.locator('input[autocomplete="new-password"]').fill('password123')
  await page.locator('button[type="submit"]').click()
  await expect(page).toHaveURL(/\/verify-email$/)

  // Logout dulu: /forgot-password adalah halaman GUEST-ONLY — guard akan
  // membalikkan user yang masih login ke beranda (regresi ISSUES #5).
  await page.getByRole('button', { name: 'Logout' }).click()
  await expect(page).toHaveURL(/\/login/)

  // 2. Minta tautan reset — catat offset log SEBELUM submit, agar potongan
  //    yang dibaca cuma email BARU (bukan tautan lama dari run sebelumnya)
  const offset = statSync(LOG).size
  await page.goto('/forgot-password')
  await page.locator('input[autocomplete="email"]').fill(email)
  await page.locator('button[type="submit"]').click()
  await expect(page.getByTestId('forgot-sent')).toBeVisible()

  // 3. Ekstrak tautan reset dari potongan log baru
  //    Format: /reset-password?token=XXX&email=YYY (&amp; di bagian HTML)
  const chunk = readFileSync(LOG, 'utf8').slice(offset)
  const match = chunk.match(/reset-password\?token=([\w-]+)&(?:amp;)?email=([\w%.-]+)/)
  expect(match, `tautan reset tidak ditemukan di log:\n${chunk.slice(0, 2000)}`).not.toBeNull()

  // 4. Buka form reset langsung di SPA (token + email dari tautan)
  await page.goto(`/reset-password?token=${match![1]}&email=${match![2]}`)
  await page.locator('input[autocomplete="new-password"]').fill('password-sandi-baru')
  await page.locator('button[type="submit"]').click()

  // 5. Reset = bukti kepemilikan → auto-login (session baru, password lama
  //    tak berlaku lagi). Email belum diverifikasi → notice verifikasi.
  await expect(page).toHaveURL(/\/verify-email$/)
  await expect(page.getByText('E2E Reset')).toBeVisible()
})
