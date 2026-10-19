import { expect, test } from '@playwright/test'

// Hardening ASVS — verifikasi header lewat HTTP asli (bukan unit test).
// Target localhost:8000 (backend langsung): di dev, halaman SPA di :5173
// dilayani Vite, jadi header Laravel hanya menempel pada response backend.

const BACKEND = 'http://localhost:8000'

test('response web memuat security headers lengkap', async ({ request }) => {
  const res = await request.get(`${BACKEND}/login`)

  expect(res.status()).toBe(200)
  expect(res.headers()['x-content-type-options']).toBe('nosniff')
  expect(res.headers()['x-frame-options']).toBe('DENY')
  expect(res.headers()['referrer-policy']).toBe('strict-origin-when-cross-origin')
  expect(res.headers()['strict-transport-security']).toContain('max-age=31536000')
  expect(res.headers()['cache-control']).toBe('no-store, private')

  const csp = res.headers()['content-security-policy']
  expect(csp).toContain("frame-ancestors 'none'")
  expect(csp).toContain("object-src 'none'")
  expect(csp).toContain("base-uri 'self'")
  expect(csp).toContain("form-action 'self'")
})

test('response API juga memuat security headers + no-store', async ({ request }) => {
  // Tanpa token → 401; header keamanan HARUS tetap menempel di respons error
  // (scanner memeriksa juga halaman/endpoint yang menolak).
  const res = await request.get(`${BACKEND}/api/v1/products`)

  expect(res.status()).toBe(401)
  expect(res.headers()['x-content-type-options']).toBe('nosniff')
  expect(res.headers()['cache-control']).toBe('no-store, private')
})

test('panel admin /admin memuat CSP frame-ancestors (tanpa memecah Filament)', async ({ request }) => {
  // Bukan sekadar header: halaman login Filament harus tetap ter-serve (200)
  // dengan CSP menempel — regresi kalau CSP nanti ditambah script-src.
  const res = await request.get(`${BACKEND}/admin`)

  expect(res.status()).toBe(200)
  expect(res.headers()['content-security-policy']).toContain("frame-ancestors 'none'")
})
