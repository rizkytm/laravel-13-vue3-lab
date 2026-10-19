import { expect, test as setup } from '@playwright/test'
import { AUTH_STATE, USER } from '../helpers'

// Setup project: login SEKALI sebagai test@example.com → simpan cookie
// ke playwright-auth.json. Spec di project `app` memakai file ini
// (storageState) sehingga TIDAK mengirim POST /login lagi — penting karena
// rate limit login cuma 5/menit (pelajaran ISSUES.md: e2e pernah 429).

setup('authenticate', async ({ page }) => {
  await page.goto('/')
  await expect(page).toHaveURL(/\/login/)
  await page.locator('input[type="email"]').fill(USER.email)
  await page.locator('input[autocomplete="current-password"]').fill(USER.password)
  await page.locator('button[type="submit"]').click()
  await expect(page).toHaveURL('http://localhost:5173/')
  await expect(page.getByText(USER.name)).toBeVisible()

  await page.context().storageState({ path: AUTH_STATE })
})
