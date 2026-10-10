import { defineStore } from 'pinia'
import { ref } from 'vue'
import { clearToken, request, spaLogin, spaLogout } from '../api'
import type { ApiError, User } from '../types'

/**
 * Auth store — sumber kebenaran "siapa yang login".
 * State global: App.vue (nav), router guard, dan halaman checkout
 * semua membaca user yang sama.
 */
export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null) // null = belum/tidak login

  /**
   * Cek session ke backend (GET /api/user — auth:sanctum).
   * Endpoint ini di LUAR prefix /v1 — pakai request() langsung,
   * bukan api.get() (yang menambah /api/v1).
   * Jalan saat boot & saat guard butuh kepastian.
   */
  async function fetchUser(): Promise<void> {
    try {
      user.value = await request<User>('/api/user')
    } catch {
      user.value = null // 401 = guest
    }
  }

  async function login(email: string, password: string): Promise<void> {
    await spaLogin(email, password) // csrf-cookie → POST /login (session)
    await fetchUser()
    // Redirect /login bisa beres apa pun hasilnya (selalu 200 HTML SPA) —
    // keberhasilan autentikasi dibuktikan oleh /api/user, bukan bentuk response.
    if (!user.value) {
      const error = new Error('Email atau password salah.') as ApiError
      error.status = 401
      throw error
    }
  }

  async function logout(): Promise<void> {
    try {
      await spaLogout() // POST /logout — invalidate session + CSRF baru
    } finally {
      user.value = null
      clearToken() // bersihkan juga mode token kalau sempat terpakai
    }
  }

  return { user, fetchUser, login, logout }
})
