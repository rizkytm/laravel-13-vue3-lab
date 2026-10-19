import { expect, type Page } from '@playwright/test'

export const USER = { name: 'Test User', email: 'test@example.com', password: 'password' }

export const ADMIN = { email: 'admin@example.com', password: 'password' }

/** File hasil login setup — dipakai project `app` sebagai storageState. */
export const AUTH_STATE = 'playwright-auth.json'

/** Login lewat SPA — preconditon: user dari DatabaseSeeder.
 *  HANYA dipakai project `anon` (auth.spec) — spec `app` memakai storageState. */
export async function login(page: Page): Promise<void> {
  await page.goto('/')
  await expect(page).toHaveURL(/\/login/)
  await page.locator('input[type="email"]').fill(USER.email)
  await page.locator('input[autocomplete="current-password"]').fill(USER.password)
  await page.locator('button[type="submit"]').click()
  await expect(page).toHaveURL('http://localhost:5173/')
}
