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

// Login → token disimpan di localStorage (dipakai semua request berikutnya)
export async function login(email, password) {
  const data = await request('/api/token', {
    method: 'POST',
    body: { email, password },
  })
  setToken(data.token)
  return data
}
