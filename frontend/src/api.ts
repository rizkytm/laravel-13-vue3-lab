import type { ApiError, Paginated } from './types'

// HTTP wrapper ke Laravel API — SATU tempat untuk base URL, header,
// token, dan interpretasi error. Component/pure function tidak perlu
// tahu detail fetch. (F4: generik T = bentuk response yang diharapkan)

const TOKEN_KEY = 'lab_token'

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token)
}

export function clearToken(): void {
  localStorage.removeItem(TOKEN_KEY)
}

interface RequestOptions {
  method?: string
  body?: unknown
}

export async function request<T = unknown>(url: string, { method = 'GET', body }: RequestOptions = {}): Promise<T> {
  const token = getToken()

  const res = await fetch(url, {
    method,
    // credentials: cookie session (Sanctum SPA) ikut dikirim ke Laravel.
    // Kombinasi: token di localStorage dipakai KALAU ADA (mode Bearer),
    // selain itu session cookie yang mengautentikasi.
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  })

  const json: unknown = await res.json().catch(() => null)

  if (!res.ok) {
    // Error shape mengikuti kontrak Laravel: {message, errors}
    const message = typeof json === 'object' && json !== null && 'message' in json
      ? String((json as { message: unknown }).message)
      : `HTTP ${res.status}`
    const error = new Error(message) as ApiError
    error.status = res.status
    error.errors = (json as { errors?: Record<string, string[]> } | null)?.errors ?? {}
    throw error
  }

  return json as T
}

// Helper untuk endpoint versioned (/api/v1/*)
export const api = {
  get: <T = unknown>(path: string) => request<Paginated<T>>(`/api/v1${path}`),
  // Bentuk spesifik (bukan paginator) — dipakai auth & cart store
  raw: <T = unknown>(path: string, options: RequestOptions = {}) =>
    request<T>(`/api/v1${path}`, options),
  post: <T = unknown>(path: string, body?: unknown) => request<T>(`/api/v1${path}`, { method: 'POST', body }),
}

// --- Sanctum SPA flow (cookie session) ---
// 1. GET /sanctum/csrf-cookie → Laravel menaruh cookie XSRF-TOKEN
// 2. POST /login dengan header X-XSRF-TOKEN (nilai cookie, di-decode)
// 3. Request berikutnya cukup credentials:'include' — auth via session

export function xsrfToken(): string {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function csrf(): Promise<void> {
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
}

function authHeaders(): Record<string, string> {
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-XSRF-TOKEN': xsrfToken(),
  }
}

async function parseOrThrow(res: Response): Promise<unknown> {
  const json: unknown = await res.json().catch(() => null)
  if (!res.ok) {
    const message = typeof json === 'object' && json !== null && 'message' in json
      ? String((json as { message: unknown }).message)
      : 'Login gagal.'
    const error = new Error(message) as ApiError
    error.status = res.status
    throw error
  }
  return json
}

// SPA login → endpoint WEB (session), bukan /api/token
async function spaPost(path: string, body: unknown): Promise<unknown> {
  await csrf()
  const res = await fetch(path, {
    method: 'POST',
    credentials: 'include',
    // Jangan follow 302: targetnya rute SPA (/orders dst) yang dilayani
    // index.html — fetch Accept: application/json di-fallback 404 oleh Vite.
    // Sukses/gagal login SAMA-SAMA 302 → dibuktikan GET /api/user oleh auth store.
    redirect: 'manual',
    headers: authHeaders(),
    body: JSON.stringify(body),
  })
  // opaqueredirect (browser) / 3xx mentah (impl lain) = redirect diterima
  if (res.type === 'opaqueredirect' || (res.status >= 300 && res.status < 400)) {
    return null
  }
  return parseOrThrow(res)
}

export async function spaLogin(email: string, password: string): Promise<void> {
  await spaPost('/login', { email, password })
}

export async function spaRegister(name: string, email: string, password: string): Promise<void> {
  await spaPost('/register', { name, email, password, password_confirmation: password })
}

// Password reset (respons JSON 202/422, tanpa redirect)
export async function spaForgotPassword(email: string): Promise<{ message: string }> {
  return spaPost('/forgot-password', { email }) as Promise<{ message: string }>
}

export async function spaResetPassword(token: string, email: string, password: string): Promise<void> {
  await spaPost('/reset-password', { token, email, password, password_confirmation: password })
}

export async function spaResendVerification(): Promise<{ message: string }> {
  return spaPost('/email/verification-notification', {}) as Promise<{ message: string }>
}

export async function spaLogout(): Promise<void> {
  await csrf()
  await fetch('/logout', {
    method: 'POST',
    credentials: 'include',
    headers: authHeaders(),
  })
}

// Mode lama (token perangkat/mobile) — tetap tersedia, gak dipakai SPA
export async function login(email: string, password: string): Promise<void> {
  const data = await request<{ token: string }>('/api/token', {
    method: 'POST',
    body: { email, password },
  })
  setToken(data.token)
}
