import { expect, test } from '@playwright/test'
import { login } from './helpers'

// Regresi bug manual E2E (ISSUES.md #1-#4): login via Vite proxy.

test('login sukses redirect ke / (regresi: fetch gagal diam / tetap di login)', async ({ page }) => {
  await login(page)
  await expect(page.getByText('Test User')).toBeVisible()
})

test('toggle password pakai icon, bukan teks (regresi ISSUES #2)', async ({ page }) => {
  await page.goto('/login')
  const password = page.locator('input[autocomplete="current-password"]')
  const toggle = page.getByTestId('toggle-password')

  await expect(password).toHaveAttribute('type', 'password')
  await expect(toggle).toHaveAttribute('aria-label', 'Lihat password')

  await toggle.click()
  await expect(password).toHaveAttribute('type', 'text')
  await expect(toggle).toHaveAttribute('aria-label', 'Sembunyikan password')
})

test('logout redirect ke /login (regresi laporan A1)', async ({ page }) => {
  await login(page)
  await page.getByRole('button', { name: 'Logout' }).click()
  await expect(page).toHaveURL(/\/login/)
})
