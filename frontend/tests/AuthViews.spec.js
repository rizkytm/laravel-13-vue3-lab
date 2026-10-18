import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import RegisterView from '../src/views/RegisterView.vue'
import ForgotPasswordView from '../src/views/ForgotPasswordView.vue'
import ResetPasswordView from '../src/views/ResetPasswordView.vue'
import VerifyEmailView from '../src/views/VerifyEmailView.vue'
import { useAuthStore } from '../src/stores/auth'

const jsonResponse = (body, ok = true, status = 200) => ({
  ok,
  status,
  json: async () => body,
})

function makeRouter(initial = '/register') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'products', component: { template: '<div>products</div>' } },
      { path: '/login', name: 'login', component: { template: '<div>login</div>' } },
      { path: '/register', name: 'register', component: RegisterView },
      { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView },
      { path: '/reset-password', name: 'reset-password', component: ResetPasswordView },
      { path: '/verify-email', name: 'verify-email', component: VerifyEmailView },
    ],
  })
  router.push(initial)
  return router
}

async function mountView(View, initial) {
  const router = makeRouter(initial)
  await router.isReady()
  return { router, wrapper: mount(View, { global: { plugins: [router] } }) }
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('RegisterView', () => {
  it('submits name/email/password and navigates to verify-email', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({})) // POST /register (302)
      .mockResolvedValueOnce(jsonResponse({ id: 1, name: 'Rizky', email: 'r@example.com', email_verified_at: null }))
    vi.stubGlobal('fetch', fetchMock)

    const { router, wrapper } = await mountView(RegisterView, '/register')

    await wrapper.get('input[type="text"]').setValue('Rizky')
    await wrapper.get('input[type="email"]').setValue('r@example.com')
    await wrapper.get('input[type="password"]').setValue('rahasia123')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fetchMock.mock.calls[1][0]).toBe('/register')
    expect(router.currentRoute.value.name).toBe('verify-email')
  })

  it('shows validation error message from Laravel (duplicate email)', async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse(
        { message: 'Email sudah terdaftar.', errors: { email: ['Email sudah terdaftar.'] } },
        false,
        422,
      )))

    const { wrapper } = await mountView(RegisterView, '/register')

    await wrapper.get('input[type="text"]').setValue('Rizky')
    await wrapper.get('input[type="email"]').setValue('taken@example.com')
    await wrapper.get('input[type="password"]').setValue('rahasia123')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('Email sudah terdaftar.')
  })
})

describe('ForgotPasswordView', () => {
  it('submits email and shows generic sent message', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({ message: 'Jika email terdaftar, tautan reset password sudah dikirim.' }, true, 202))
    vi.stubGlobal('fetch', fetchMock)

    const { wrapper } = await mountView(ForgotPasswordView, '/forgot-password')

    await wrapper.get('input[type="email"]').setValue('r@example.com')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fetchMock.mock.calls[1][0]).toBe('/forgot-password')
    expect(wrapper.get('[data-testid="forgot-sent"]').text()).toContain('tautan reset password sudah dikirim')
    expect(wrapper.find('form').exists()).toBe(false)
  })
})

describe('ResetPasswordView', () => {
  it('shows hint to request a new link when token is missing', async () => {
    const { wrapper } = await mountView(ResetPasswordView, '/reset-password')

    expect(wrapper.text()).toContain('Tautan reset tidak lengkap')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('submits token/email from query and navigates to products when verified', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({})) // POST /reset-password (302)
      .mockResolvedValueOnce(jsonResponse({ id: 1, name: 'R', email: 'r@example.com', email_verified_at: '2026-10-18T00:00:00Z' }))
    vi.stubGlobal('fetch', fetchMock)

    const { router, wrapper } = await mountView(
      ResetPasswordView,
      '/reset-password?token=tok123&email=r%40example.com',
    )

    await wrapper.get('input[type="password"]').setValue('barusaja123')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const body = JSON.parse(fetchMock.mock.calls[1][1].body)
    expect(body).toMatchObject({ token: 'tok123', email: 'r@example.com', password: 'barusaja123' })
    expect(router.currentRoute.value.name).toBe('products')
  })

  it('navigates to verify-email when user email is still unverified', async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({})) // POST /reset-password
      .mockResolvedValueOnce(jsonResponse({ id: 1, name: 'R', email: 'r@example.com', email_verified_at: null })))

    const { router, wrapper } = await mountView(
      ResetPasswordView,
      '/reset-password?token=tok123&email=r%40example.com',
    )

    await wrapper.get('input[type="password"]').setValue('barusaja123')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('verify-email')
  })

  it('shows backend error for invalid token', async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({ message: 'Token reset tidak valid atau sudah kedaluwarsa.' }, false, 422)))

    const { wrapper } = await mountView(
      ResetPasswordView,
      '/reset-password?token=salah&email=r%40example.com',
    )

    await wrapper.get('input[type="password"]').setValue('barusaja123')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Token reset tidak valid')
  })
})

describe('VerifyEmailView', () => {
  it('shows done message when returning with ?status=verified', async () => {
    const { wrapper } = await mountView(VerifyEmailView, '/verify-email?status=verified')

    expect(wrapper.get('[data-testid="verify-done"]').text()).toContain('Email berhasil diverifikasi.')
  })

  it('shows notice with user email when unverified', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'R', email: 'r@example.com', email_verified_at: null }

    const { wrapper } = await mountView(VerifyEmailView, '/verify-email')

    expect(wrapper.text()).toContain('r@example.com')
    expect(wrapper.text()).toContain('Kami sudah mengirim tautan verifikasi')
  })

  it('resends verification link and shows backend message', async () => {
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'R', email: 'r@example.com', email_verified_at: null }

    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({ message: 'Tautan verifikasi baru sudah dikirim.' }, true, 202))
    vi.stubGlobal('fetch', fetchMock)

    const { wrapper } = await mountView(VerifyEmailView, '/verify-email')

    await wrapper.get('[data-testid="resend-verification"]').trigger('click')
    await flushPromises()

    expect(fetchMock.mock.calls[1][0]).toBe('/email/verification-notification')
    expect(wrapper.get('[data-testid="verify-sent"]').text()).toBe('Tautan verifikasi baru sudah dikirim.')
  })
})
