import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { api, getToken, setToken } from '../src/api'

const jsonResponse = (body, ok = true, status = 200) => ({
  ok,
  status,
  json: async () => body,
})

beforeEach(() => {
  localStorage.clear()
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('api wrapper', () => {
  it('prefixes /api/v1 and attaches Bearer token when present', async () => {
    setToken('token-123')
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [] }))
    vi.stubGlobal('fetch', fetchMock)

    await api.get('/products')

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('/api/v1/products')
    expect(init.headers.Authorization).toBe('Bearer token-123')
    expect(init.headers.Accept).toBe('application/json')
  })

  it('sends no Authorization header for guests', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [] }))
    vi.stubGlobal('fetch', fetchMock)

    await api.get('/products')

    expect(fetchMock.mock.calls[0][1].headers.Authorization).toBeUndefined()
  })

  it('throws Error carrying message + validation errors from Laravel shape', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      jsonResponse(
        { message: 'Keranjang kosong.', errors: { items: ['Keranjang tidak boleh kosong.'] } },
        false,
        422,
      ),
    ))

    await expect(api.post('/checkout', { items: [] }))
      .rejects
      .toThrow('Keranjang kosong.')

    try {
      await api.post('/checkout', { items: [] })
    } catch (e) {
      expect(e.status).toBe(422)
      expect(e.errors.items).toHaveLength(1)
    }
  })

  it('token helpers persist in localStorage', () => {
    setToken('abc')
    expect(getToken()).toBe('abc')
  })
})
