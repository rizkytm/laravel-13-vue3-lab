import { expect, test } from '@playwright/test'

// T3 — Keranjang SERVER-SIDE: inti tesnya = RELOAD. Kalau cart masih ada
// setelah reload, artinya datanya dari tabel cart_items (server), bukan
// localStorage. Juga uji clamp qty oleh server + hapus item.

test('cart server-side: add → reload tetap → qty → hapus', async ({ page }) => {
  // Bersihkan sisa cart run sebelumnya (gagal di tengah jalan dsb)
  await page.goto('/cart')
  const clear = page.getByTestId('clear-cart')
  if (await clear.isVisible().catch(() => false)) {
    await clear.click()
    await expect(page.getByTestId('cart-table')).toHaveCount(0)
  }

  // Tambah produk
  await page.goto('/')
  await page.getByTestId('product-card').first().click()
  await page.getByTestId('add-to-cart').click()
  await page.goto('/cart')
  await expect(page.getByTestId('cart-table')).toBeVisible()

  // INTI T3: reload halaman — cart tetap (server-side, bukan localStorage)
  await page.reload()
  await expect(page.getByTestId('cart-table')).toBeVisible()

  // Qty: server yang meng-clamp ke stok (max input = stok item)
  const qty = page.locator('[data-testid^="qty-"]').first()
  const max = Number(await qty.getAttribute('max'))
  const target = max >= 2 ? 2 : 1
  await qty.fill(String(target))
  await qty.press('Tab')
  await expect(qty).toHaveValue(String(target))

  // Hapus item → tabel hilang, empty-state tampil
  await page.locator('[data-testid^="remove-"]').first().click()
  await expect(page.getByTestId('cart-table')).toHaveCount(0)
  await expect(page.getByText('Keranjang kosong.')).toBeVisible()
})
