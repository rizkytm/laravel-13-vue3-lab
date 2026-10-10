import { afterEach, describe, expect, it, vi } from 'vitest'
import { useApi } from '../src/composables/useApi'
import { useProducts } from '../src/composables/useProducts'

const jsonResponse = (body, ok = true, status = 200) => ({
  ok,
  status,
  json: async () => body,
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('useApi', () => {
  it('tracks loading and data on success', async () => {
    const { data, loading, error, execute } = useApi()

    expect(loading.value).toBe(false)

    const promise = execute(async () => 'hasil')
    expect(loading.value).toBe(true) // langsung true sebelum selesai

    await promise
    expect(data.value).toBe('hasil')
    expect(loading.value).toBe(false)
    expect(error.value).toBeNull()
  })

  it('captures error object (status tetap terbaca) instead of throwing', async () => {
    const { data, error, execute } = useApi()

    const result = await execute(async () => {
      const e = new Error('Keranjang kosong.')
      e.status = 422
      throw e
    })

    expect(result).toBeNull()
    expect(data.value).toBeNull()
    expect(error.value.message).toBe('Keranjang kosong.')
    expect(error.value.status).toBe(422)
  })
})

describe('useProducts', () => {
  it('load() fills products from api', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      jsonResponse({ data: [{ id: 1, name: 'Kopi', slug: 'kopi', price: 1, stock: 1 }] }),
    ))

    const { products, loading, error, load } = useProducts()
    await load()

    expect(products.value).toHaveLength(1)
    expect(loading.value).toBe(false)
    expect(error.value).toBe('')
  })

  it('maps 401 to friendly message', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      jsonResponse({ message: 'Unauthenticated.' }, false, 401),
    ))

    const { error, load } = useProducts()
    await load()

    expect(error.value).toBe('Silakan login terlebih dahulu.')
  })
})
