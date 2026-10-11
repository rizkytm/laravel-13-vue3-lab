import { expect, type Page } from '@playwright/test'

export const USER = { email: 'test@example.com', password: 'password' }

/** Login lewat SPA — preconditon: user dari DatabaseSeeder. */
export async function login(page: Page): Promise<void> {
  await page.goto('/')
  await expect(page).toHaveURL(/\/login/)
  await page.locator('input[type="email"]').fill(USER.email)
  await page.locator('input[autocomplete="current-password"]').fill(USER.password)
  await page.locator('button[type="submit"]').click()
  await expect(page).toHaveURL('http://localhost:5173/')
}
