import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '../src/stores/auth'

const jsonResponse = (body, ok = true, status = 200) => ({
  ok,
  status,
  json: async () => body,
})

const userJson = { id: 1, name: 'Test User', email: 'test@example.com' }

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('auth store', () => {
  it('fetchUser stores user when session valid', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(userJson)))

    const auth = useAuthStore()
    await auth.fetchUser()

    expect(auth.user).toEqual(userJson)
  })

  it('fetchUser sets null on 401 (guest)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      jsonResponse({ message: 'Unauthenticated.' }, false, 401),
    ))

    const auth = useAuthStore()
    await auth.fetchUser()

    expect(auth.user).toBeNull()
  })

  it('login flow: csrf-cookie → POST /login → GET /api/user (cookie session)', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse({}))                                  // /sanctum/csrf-cookie
      .mockResolvedValueOnce(jsonResponse({}))                                  // POST /login (302 → json? asumsi ok)
      .mockResolvedValueOnce(jsonResponse(userJson))                            // GET /api/user
    vi.stubGlobal('fetch', fetchMock)

    const auth = useAuthStore()
    await auth.login('test@example.com', 'password')

    expect(fetchMock.mock.calls.map((c) => c[0])).toEqual([
      '/sanctum/csrf-cookie',
      '/login',
      '/api/user',
    ])
    // Semua request cookie mode
    expect(fetchMock.mock.calls[1][1].credentials).toBe('include')
    expect(auth.user).toEqual(userJson)
  })

  it('login failure keeps user null and rethrows', async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({ message: 'Unauthorized.' }, false, 401))) // login gagal

    const auth = useAuthStore()

    await expect(auth.login('x@y.z', 'salah')).rejects.toThrow('Unauthorized.')
    expect(auth.user).toBeNull()
  })

  it('logout clears user and calls POST /logout', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse({}))       // csrf
      .mockResolvedValueOnce(jsonResponse({}))       // POST /logout
    vi.stubGlobal('fetch', fetchMock)

    const auth = useAuthStore()
    auth.user = userJson // state sudah login

    await auth.logout()

    expect(fetchMock.mock.calls[1][0]).toBe('/logout')
    expect(auth.user).toBeNull()
  })
})
