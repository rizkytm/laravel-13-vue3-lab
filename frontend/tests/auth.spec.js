import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { spaForgotPassword, spaResetPassword, spaResendVerification } from '../src/api'
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

  it('register flow: csrf-cookie → POST /register → GET /api/user', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse({}))       // /sanctum/csrf-cookie
      .mockResolvedValueOnce(jsonResponse({}))       // POST /register (302 → diabaikan)
      .mockResolvedValueOnce(jsonResponse(userJson)) // GET /api/user
    vi.stubGlobal('fetch', fetchMock)

    const auth = useAuthStore()
    await auth.register('Test User', 'test@example.com', 'rahasia123')

    expect(fetchMock.mock.calls.map((c) => c[0])).toEqual([
      '/sanctum/csrf-cookie',
      '/register',
      '/api/user',
    ])
    // Body register memuat field + password_confirmation (kontrak backend)
    const body = JSON.parse(fetchMock.mock.calls[1][1].body)
    expect(body).toEqual({
      name: 'Test User',
      email: 'test@example.com',
      password: 'rahasia123',
      password_confirmation: 'rahasia123',
    })
    expect(auth.user).toEqual(userJson)
  })

  it('register failure keeps user null and rethrows', async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse(
        { message: 'The email has already been taken.', errors: { email: ['Email sudah terdaftar.'] } },
        false,
        422,
      )))

    const auth = useAuthStore()

    await expect(auth.register('X', 'taken@example.com', 'rahasia123'))
      .rejects.toThrow('The email has already been taken.')
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

describe('password reset helpers', () => {
  it('spaForgotPassword posts email via csrf flow and returns message', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({ message: 'Jika email terdaftar, tautan reset password sudah dikirim.' }, true, 202))
    vi.stubGlobal('fetch', fetchMock)

    const res = await spaForgotPassword('test@example.com')

    expect(fetchMock.mock.calls.map((c) => c[0])).toEqual([
      '/sanctum/csrf-cookie',
      '/forgot-password',
    ])
    expect(JSON.parse(fetchMock.mock.calls[1][1].body)).toEqual({ email: 'test@example.com' })
    expect(res.message).toContain('Jika email terdaftar')
  })

  it('spaResetPassword sends token + email + password_confirmation', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({})) // POST /reset-password (302 → diabaikan)
    vi.stubGlobal('fetch', fetchMock)

    await spaResetPassword('tok123', 'test@example.com', 'barusaja123')

    expect(fetchMock.mock.calls[1][0]).toBe('/reset-password')
    expect(JSON.parse(fetchMock.mock.calls[1][1].body)).toEqual({
      token: 'tok123',
      email: 'test@example.com',
      password: 'barusaja123',
      password_confirmation: 'barusaja123',
    })
  })

  it('spaResetPassword rethrows backend 422 (invalid token)', async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({ message: 'Token reset tidak valid atau sudah kedaluwarsa.' }, false, 422)))

    await expect(spaResetPassword('salah', 'test@example.com', 'x'))
      .rejects.toThrow('Token reset tidak valid atau sudah kedaluwarsa.')
  })

  it('spaResendVerification posts to /email/verification-notification', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({ message: 'Tautan verifikasi baru sudah dikirim.' }, true, 202))
    vi.stubGlobal('fetch', fetchMock)

    const res = await spaResendVerification()

    expect(fetchMock.mock.calls.map((c) => c[0])).toEqual([
      '/sanctum/csrf-cookie',
      '/email/verification-notification',
    ])
    expect(res.message).toBe('Tautan verifikasi baru sudah dikirim.')
  })
})
