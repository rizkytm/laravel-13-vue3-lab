import { expect, test } from '@playwright/test'
import { ADMIN, USER } from './helpers'

// T5+T6 — Panel Filament /admin:
// 1. non-admin (session test@example.com dari storageState) → 403
// 2. admin → login di panel → aksi Refund order paid → status refunded
//
// Host SELALU localhost (bukan 127.0.0.1) — cookie host-only, pelajaran
// ISSUES #8. Kedua tes dijalankan dengan storageState project `app`.

test('user non-admin ditolak 403 di /admin', async ({ page }) => {
  expect(USER.email).not.toBe(ADMIN.email) // pengingat: state = akun biasa

  const resp = await page.goto('http://localhost:8000/admin')
  expect(resp?.status()).toBe(403)
})

test('admin login di panel → Refund order paid → status refunded', async ({ page }) => {
  // Panel punya form login SENDIRI (bukan /login SPA). Buang session
  // test@example.com dari storageState dulu — kalau dibawa, /admin/login
  // di-guard Filament (user sudah login → redirect ke /admin → 403).
  await page.context().clearCookies()
  await page.goto('http://localhost:8000/admin/login')
  await page.locator('input[type="email"], input[name="email"]').first().fill(ADMIN.email)
  await page.locator('input[type="password"], input[name="password"]').first().fill(ADMIN.password)
  await page.locator('button[type="submit"]').click()

  // Masuk panel → menu Pesanan (resource list)
  await expect(page).toHaveURL(/localhost:8000\/admin(?!\/login)/)
  await page.goto('http://localhost:8000/admin/orders')

  // Aksi Refund — incar tombolnya LANGSUNG (unik di tabel). Jangan filter
  // tr by 'paid': hasText case-insensitive → header "Paid at" ikut cocok.
  const refund = page.getByRole('button', { name: 'Refund', exact: true })
  await expect(refund).toBeVisible()
  await refund.click()

  // requiresConfirmation → alertdialog muncul; tombol konfirmasinya
  // berlabel "Confirm" (bukan "Refund" — itu label aksi di baris)
  await page.getByRole('alertdialog').getByRole('button', { name: 'Confirm' }).click()

  // Berhasil: notifikasi + status baris berubah (Livewire update in-place)
  await expect(page.getByText('Refund berhasil')).toBeVisible()
  await expect(page.getByText('Refunded')).toBeVisible()
})
