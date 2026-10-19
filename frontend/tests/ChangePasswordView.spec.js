import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import ChangePasswordView from '../src/views/ChangePasswordView.vue'

// ASVS 6.2.2 — view ubah password saat login (route requiresAuth diuji
// terpisah lewat router guard; di sini fokus perilaku form).

const jsonResponse = (body, ok = true, status = 200) => ({
  ok,
  status,
  json: async () => body,
})

async function mountView() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'products', component: { template: '<div>products</div>' } },
      { path: '/change-password', name: 'change-password', component: ChangePasswordView },
    ],
  })
  router.push('/change-password')
  await router.isReady()
  return { router, wrapper: mount(ChangePasswordView, { global: { plugins: [router] } }) }
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('ChangePasswordView', () => {
  it('submits 3 field → sukses: pesan tampil & field dikosongkan', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({ message: 'Password berhasil diperbarui.' })) // POST
    vi.stubGlobal('fetch', fetchMock)

    const { wrapper } = await mountView()

    const inputs = wrapper.findAll('input[type="password"]')
    expect(inputs).toHaveLength(3) // current, baru, konfirmasi
    await inputs[0].setValue('password-lama')
    await inputs[1].setValue('password-baru-9')
    await inputs[2].setValue('password-baru-9')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fetchMock.mock.calls[1][0]).toBe('/change-password')
    expect(fetchMock.mock.calls[1][1].body).toContain('current_password')
    expect(wrapper.get('[data-testid="change-success"]').text()).toBe('Password berhasil diperbarui.')
    // Field kosong — password tak tertinggal di DOM
    for (const input of wrapper.findAll('input[type="password"]')) {
      expect(input.element.value).toBe('')
    }
  })

  it('menampilkan error validasi Laravel (password lama salah)', async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({
        message: 'The given data was invalid.',
        errors: { current_password: ['Password lama salah.'] },
      }, false, 422)))

    const { wrapper } = await mountView()

    const inputs = wrapper.findAll('input[type="password"]')
    await inputs[0].setValue('salah')
    await inputs[1].setValue('password-baru-9')
    await inputs[2].setValue('password-baru-9')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[data-testid="change-error"]').text()).toContain('Password lama salah.')
    expect(wrapper.find('[data-testid="change-success"]').exists()).toBe(false)
  })

  it('error jaringan tanpa errors field → pakai message umum', async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce(jsonResponse({})) // csrf
      .mockResolvedValueOnce(jsonResponse({ message: 'HTTP 500' }, false, 500)))

    const { wrapper } = await mountView()

    const inputs = wrapper.findAll('input[type="password"]')
    await inputs[0].setValue('x')
    await inputs[1].setValue('password-baru-9')
    await inputs[2].setValue('password-baru-9')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[data-testid="change-error"]').text()).toContain('HTTP 500')
  })
})
