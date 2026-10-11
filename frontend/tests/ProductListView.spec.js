import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import ProductListView from '../src/views/ProductListView.vue'

const jsonResponse = (body, ok = true, status = 200) => ({
  ok,
  status,
  json: async () => body,
})

const products = [
  { id: 1, name: 'Kopi Robusta', slug: 'kopi-robusta', price: 25000, stock: 5, options: {} },
  { id: 2, name: 'Teh Melati', slug: 'teh-melati', price: 15000, stock: 3, options: {} },
]

function makeRouter() {
  // Nama route WAJIB ada — view mem-push by name ('product-detail'),
  // tanpa nama → router error "no matching named route"
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'products', component: ProductListView },
      { path: '/products/:slug', name: 'product-detail', component: { template: '<div>detail</div>' } },
      { path: '/login', name: 'login', component: { template: '<div>login</div>' } },
    ],
  })
}

afterEach(() => {
  vi.unstubAllGlobals()
  localStorage.clear()
})

describe('ProductListView', () => {
  it('shows loading then renders fetched products', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: products })))

    const router = makeRouter()
    router.push('/')
    await router.isReady()

    const wrapper = mount(ProductListView, { global: { plugins: [router] } })

    // Sebelum promise selesai → state loading
    expect(wrapper.text()).toContain('Memuat')

    await flushPromises()
    expect(wrapper.text()).toContain('Kopi Robusta')
    expect(wrapper.text()).toContain('Teh Melati')
  })

  it('filters products via computed search', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: products })))

    const router = makeRouter()
    router.push('/')
    await router.isReady()

    const wrapper = mount(ProductListView, { global: { plugins: [router] } })
    await flushPromises()

    // computed `filtered` re-evaluate saat ref `search` berubah
    await wrapper.get('[data-testid="search"]').setValue('kopi')

    expect(wrapper.text()).toContain('Kopi Robusta')
    expect(wrapper.text()).not.toContain('Teh Melati')
  })

  it('shows friendly message when api returns 401', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      jsonResponse({ message: 'Unauthenticated.' }, false, 401),
    ))

    const router = makeRouter()
    router.push('/')
    await router.isReady()

    const wrapper = mount(ProductListView, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Silakan login terlebih dahulu.')
  })

  it('navigates to detail when card clicked (emit → parent → router)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: products })))

    const router = makeRouter()
    router.push('/')
    await router.isReady()

    const wrapper = mount(ProductListView, { global: { plugins: [router] } })
    await flushPromises()

    // Integrasi penuh: ProductCard emit 'open' → handler list → router.push
    await wrapper.find('[data-testid="product-card"]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/products/kopi-robusta')
  })

  it('sort select mengirim ?sort=&dir= dan kembali ke halaman 1', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: products }))
    vi.stubGlobal('fetch', fetchMock)

    const router = makeRouter()
    router.push('/')
    await router.isReady()

    const wrapper = mount(ProductListView, { global: { plugins: [router] } })
    await flushPromises()

    await wrapper.get('[data-testid="sort-select"]').setValue('price:asc')
    await flushPromises()

    expect(fetchMock.mock.calls.at(-1)[0]).toContain('sort=price&dir=asc')
    expect(fetchMock.mock.calls.at(-1)[0]).toContain('page=1')
  })

  it('pagination: next/prev memuat halaman sesuai tombol', async () => {
    const halaman = (n) => ({
      data: products,
      meta: { current_page: n, last_page: 3, total: 30, per_page: 12 },
    })
    const fetchMock = vi.fn().mockImplementation((url) =>
      Promise.resolve(jsonResponse(halaman(url.includes('page=2') ? 2 : 1))),
    )
    vi.stubGlobal('fetch', fetchMock)

    const router = makeRouter()
    router.push('/')
    await router.isReady()

    const wrapper = mount(ProductListView, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Halaman 1 dari 3')

    await wrapper.get('[data-testid="next-page"]').trigger('click')
    await flushPromises()
    expect(fetchMock.mock.calls.at(-1)[0]).toContain('page=2')
    expect(wrapper.text()).toContain('Halaman 2 dari 3')

    await wrapper.get('[data-testid="prev-page"]').trigger('click')
    await flushPromises()
    expect(fetchMock.mock.calls.at(-1)[0]).toContain('page=1')
  })
})
