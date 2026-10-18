import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useCartStore } from '../src/stores/cart'

const jsonResponse = (body, ok = true, status = 200) => ({
  ok,
  status,
  json: async () => body,
})

// Bentuk respons server: { data: [{product_id, name, price, quantity, stock}] }
const serverCart = (items) => ({ data: items })
const kopi = { product_id: 1, name: 'Kopi', price: 25000, quantity: 2, stock: 10 }

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('cart store (server-side)', () => {
  it('starts empty — no localStorage hydration anymore', () => {
    const cart = useCartStore()
    expect(cart.items).toEqual([])
    expect(cart.count).toBe(0)
    expect(cart.total).toBe(0)
  })

  it('fetch loads cart from GET /api/v1/cart', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(serverCart([kopi])))
    vi.stubGlobal('fetch', fetchMock)

    const cart = useCartStore()
    await cart.fetch()

    expect(fetchMock.mock.calls[0][0]).toBe('/api/v1/cart')
    expect(cart.items).toEqual([kopi])
    expect(cart.count).toBe(2)
    expect(cart.total).toBe(50000)
  })

  it('add posts product_id + quantity and replaces items from response', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(serverCart([kopi])))
    vi.stubGlobal('fetch', fetchMock)

    const cart = useCartStore()
    await cart.add(1, 2)

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('/api/v1/cart/items')
    expect(JSON.parse(init.body)).toEqual({ product_id: 1, quantity: 2 })
    expect(cart.items).toEqual([kopi])
  })

  it('setQuantity PATCHes the absolute quantity', async () => {
    const updated = { ...kopi, quantity: 5 }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(serverCart([updated])))
    vi.stubGlobal('fetch', fetchMock)

    const cart = useCartStore()
    await cart.setQuantity(1, 5)

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('/api/v1/cart/items/1')
    expect(init.method).toBe('PATCH')
    expect(JSON.parse(init.body)).toEqual({ quantity: 5 })
    expect(cart.items[0].quantity).toBe(5)
  })

  it('setQuantity < 1 removes the item instead of PATCHing', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(serverCart([])))
    vi.stubGlobal('fetch', fetchMock)

    const cart = useCartStore()
    await cart.setQuantity(1, 0)

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('/api/v1/cart/items/1')
    expect(init.method).toBe('DELETE')
    expect(cart.items).toHaveLength(0)
  })

  it('remove DELETEs the row; clear DELETEs the whole cart', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(serverCart([])))
    vi.stubGlobal('fetch', fetchMock)

    const cart = useCartStore()
    await cart.remove(1)
    await cart.clear()

    expect(fetchMock.mock.calls[0][1].method).toBe('DELETE')
    expect(fetchMock.mock.calls[0][0]).toBe('/api/v1/cart/items/1')
    expect(fetchMock.mock.calls[1][0]).toBe('/api/v1/cart')
    expect(fetchMock.mock.calls[1][1].method).toBe('DELETE')
  })

  it('all requests carry the session cookie (credentials include)', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(serverCart([])))
    vi.stubGlobal('fetch', fetchMock)

    const cart = useCartStore()
    await cart.fetch()
    await cart.add(1)

    for (const [, init] of fetchMock.mock.calls) {
      expect(init.credentials).toBe('include')
    }
  })

  it('server validation error rethrows (out of stock)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      jsonResponse({ message: 'Stok Kopi habis.', errors: { quantity: ['Stok Kopi habis.'] } }, false, 422),
    ))

    const cart = useCartStore()

    await expect(cart.add(1)).rejects.toThrow('Stok Kopi habis.')
    expect(cart.items).toHaveLength(0) // state tidak berubah saat gagal
  })
})
