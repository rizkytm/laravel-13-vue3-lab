import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it } from 'vitest'
import LoginView from '../src/views/LoginView.vue'
import { i18n, saveLocale, savedLocale } from '../src/i18n'

async function mountLogin() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', name: 'login', component: LoginView },
      { path: '/', name: 'products', component: { template: '<div>products</div>' } },
    ],
  })
  router.push('/login')
  await router.isReady()
  return mount(LoginView, { global: { plugins: [router] } })
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
  i18n.global.locale.value = 'id'
})

describe('i18n', () => {
  it('default locale id = teks Indonesia', async () => {
    const wrapper = await mountLogin()
    await flushPromises()

    expect(wrapper.text()).toContain('Masuk')
    expect(wrapper.get('[data-testid="toggle-password"]').attributes('aria-label'))
      .toBe('Lihat password')
  })

  it('ganti locale ke en → teks berubah', async () => {
    const wrapper = await mountLogin()
    await flushPromises()

    i18n.global.locale.value = 'en'
    await flushPromises()

    expect(wrapper.text()).toContain('Sign in')
    expect(wrapper.get('[data-testid="toggle-password"]').attributes('aria-label'))
      .toBe('Show password')
  })

  it('pilihan locale persist di localStorage', () => {
    saveLocale('en')
    expect(savedLocale()).toBe('en')
  })
})
