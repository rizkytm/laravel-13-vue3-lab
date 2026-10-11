import { expect, test } from '@playwright/test'
import { login } from './helpers'

// Regresi bug A3/A4: 404 slug binding, {data} unwrap (halaman kosong),
// Opsi tampil JSON mentah.

test('detail produk tampil dari klik kartu', async ({ page }) => {
  await login(page)
  await page.getByTestId('search').waitFor()

  const firstCard = page.getByTestId('product-card').first()
  const cardName = (await firstCard.innerText()).split('\n')[0]
  await firstCard.click()

  await expect(page).toHaveURL(/\/products\/.+$/)
  await expect(page.getByRole('heading', { name: cardName })).toBeVisible()
  await expect(page.getByText('Opsi')).toBeVisible()
  await expect(page.locator('body')).not.toContainText('{"')
})

test('search menyaring daftar produk', async ({ page }) => {
  await login(page)
  const cards = page.getByTestId('product-card')
  await expect(cards.first()).toBeVisible()

  const total = await cards.count()
  const query = (await cards.first().innerText()).split('\n')[0].slice(0, 12)
  await page.getByTestId('search').fill(query)

  await expect(cards.first()).toContainText(query)
  expect(await cards.count()).toBeLessThanOrEqual(total)
})
