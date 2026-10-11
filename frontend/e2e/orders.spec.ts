import { expect, test } from '@playwright/test'
import { login } from './helpers'

// Alur beli penuh — mandiri (membuat ordennya sendiri lewat UI),
// regresi: order detail (baru) + link invoice signed (D1).

test('checkout → detail order → link invoice signed', async ({ page }) => {
  await login(page)

  // Beli produk pertama
  await page.getByTestId('product-card').first().click()
  await page.getByTestId('add-to-cart').click()
  await page.goto('/cart')
  await page.getByTestId('checkout').click()
  await expect(page).toHaveURL(/\/orders$/)

  // Detail dari baris pesanan
  const firstLink = page.getByTestId('order-link').first()
  await expect(firstLink).toBeVisible()
  const id = (await firstLink.innerText()).replace('#', '')
  await firstLink.click()

  await expect(page).toHaveURL(new RegExp(`/orders/${id}$`))
  await expect(page.getByTestId('order-items')).toBeVisible()
  await expect(page.getByTestId('order-status')).toBeVisible()

  // Invoice = signed URL ke backend (30 menit) — klik harus MENAMPILKAN
  // halaman invoice (regresi: link jatuh ke SPA fallback → halaman kosong)
  const invoice = page.getByTestId('invoice-link')
  await expect(invoice).toBeVisible()
  await expect(invoice).toHaveAttribute('href', new RegExp(`/orders/${id}/invoice\\?expires=`))

  const [popup] = await Promise.all([
    page.context().waitForEvent('page'),
    invoice.click(),
  ])
  await popup.waitForLoadState()
  await expect(popup.getByRole('heading', { level: 1 })).toContainText(`Invoice Order #${id}`)
})
