import { expect, test } from '@playwright/test'

// T8 — Customer cancel order PENDING: tombol hanya tampil saat pending,
// klik → status cancelled (status machine + restock berjejak di backend).
//
// Kenapa order masih pending: Playwright TIDAK menjalankan queue worker
// (webServer cuma artisan serve + vite) → ProcessPayment tak pernah jalan →
// order tetap pending. Sempurna utk tes ini.

test('checkout → detail pending → cancel → status cancelled + tombol hilang', async ({ page }) => {
  // Checkout — intercept responsenya untuk dapat id order BARU (tanpa
  // menebak dari list, yang bisa berisi order lama dari run sebelumnya)
  await page.goto('/')
  await page.getByTestId('product-card').first().click()
  await page.getByTestId('add-to-cart').click()
  await page.goto('/cart')

  const [checkoutResp] = await Promise.all([
    page.waitForResponse((r) => r.url().includes('/api/v1/checkout') && r.request().method() === 'POST'),
    page.getByTestId('checkout').click(),
  ])
  expect(checkoutResp.status()).toBe(201)
  const { data: order } = (await checkoutResp.json()) as { data: { id: number } }
  await expect(page).toHaveURL(/\/orders$/)

  // Buka detail order baru
  await page.goto(`/orders/${order.id}`)
  await expect(page.getByTestId('order-status')).toHaveText('pending')
  await expect(page.getByTestId('cancel-order')).toBeVisible()

  // Klik cancel → status machine: pending → cancelled
  await page.getByTestId('cancel-order').click()
  await expect(page.getByTestId('order-status')).toHaveText('cancelled')
  // Tombol hilang (bukan pending lagi — tak bisa dibatalkan dua kali)
  await expect(page.getByTestId('cancel-order')).toHaveCount(0)

  // Reload → status tetap cancelled (persist di server)
  await page.reload()
  await expect(page.getByTestId('order-status')).toHaveText('cancelled')
})
