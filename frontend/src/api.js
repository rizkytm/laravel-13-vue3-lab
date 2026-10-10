// HTTP wrapper ke Laravel API — SATU tempat untuk base URL, header,
// token, dan interpretasi error. Component/pure function tidak perlu
// tahu detail fetch.

const TOKEN_KEY = 'lab_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token) {
  localStorage.setItem(TOKEN_KEY, token)
}

export function clearToken() {
  localStorage.removeItem(TOKEN_KEY)
}

export async function request(url, { method = 'GET', body } = {}) {
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

  const json = await res.json().catch(() => null)

  if (!res.ok) {
    // Error shape mengikuti kontrak Laravel: {message, errors}
    const error = new Error(json?.message || `HTTP ${res.status}`)
    error.status = res.status
    error.errors = json?.errors || {}
    throw error
  }

  return json
}

// Helper untuk endpoint versioned (/api/v1/*)
export const api = {
  get: (path) => request(`/api/v1${path}`),
  post: (path, body) => request(`/api/v1${path}`, { method: 'POST', body }),
}

// --- Sanctum SPA flow (cookie session) ---
// 1. GET /sanctum/csrf-cookie → Laravel menaruh cookie XSRF-TOKEN
// 2. POST /login dengan header X-XSRF-TOKEN (nilai cookie, di-decode)
// 3. Request berikutnya cukup credentials:'include' — auth via session

export function xsrfToken() {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function csrf() {
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
}

function authHeaders() {
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-XSRF-TOKEN': xsrfToken(),
  }
}

// SPA login → endpoint WEB (session), bukan /api/token
export async function spaLogin(email, password) {
  await csrf()
  const res = await fetch('/login', {
    method: 'POST',
    credentials: 'include',
    headers: authHeaders(),
    body: JSON.stringify({ email, password }),
  })
  const json = await res.json().catch(() => null)
  if (!res.ok) {
    const error = new Error(json?.message || 'Login gagal.')
    error.status = res.status
    throw error
  }
  return json
}

export async function spaLogout() {
  await csrf()
  await fetch('/logout', {
    method: 'POST',
    credentials: 'include',
    headers: authHeaders(),
  })
}

// Mode lama (token perangkat/mobile) — tetap tersedia, gak dipakai SPA
export async function login(email, password) {
  const data = await request('/api/token', {
    method: 'POST',
    body: { email, password },
  })
  setToken(data.token)
  return data
}
