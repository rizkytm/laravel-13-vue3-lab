import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import CartView from '../src/views/CartView.vue'
import { useCartStore } from '../src/stores/cart'

const jsonResponse = (body, ok = true, status = 200) => ({
  ok,
  status,
  json: async () => body,
})

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'products', component: { template: '<div>products</div>' } },
      { path: '/cart', name: 'cart', component: CartView },
      { path: '/orders', name: 'orders', component: { template: '<div>orders</div>' } },
      { path: '/login', name: 'login', component: { template: '<div>login</div>' } },
    ],
  })
}

async function mountCart() {
  const router = makeRouter()
  router.push('/cart')
  await router.isReady()
  return { router, wrapper: mount(CartView, { global: { plugins: [router] } }) }
}

/** Seed state store langsung — add() kini async (server-side). */
function seedCart(...items) {
  useCartStore().items = items
}

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('CartView', () => {
  it('shows empty message when cart is empty', async () => {
    const { wrapper } = await mountCart()

    expect(wrapper.text()).toContain('Keranjang kosong.')
  })

  it('renders items from store and updates quantity via PATCH', async () => {
    seedCart({ product_id: 1, name: 'Kopi', price: 25000, quantity: 2, stock: 10 })

    const updated = { product_id: 1, name: 'Kopi', price: 25000, quantity: 3, stock: 10 }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [updated] }))
    vi.stubGlobal('fetch', fetchMock)

    const { wrapper } = await mountCart()

    expect(wrapper.text()).toContain('Kopi')
    expect(wrapper.text()).toContain('50.000')

    await wrapper.get('[data-testid="qty-1"]').setValue('3')
    await flushPromises()

    expect(fetchMock.mock.calls[0][0]).toBe('/api/v1/cart/items/1')
    expect(fetchMock.mock.calls[0][1].method).toBe('PATCH')
    expect(wrapper.text()).toContain('75.000')
  })

  it('removes item via DELETE', async () => {
    seedCart({ product_id: 1, name: 'Kopi', price: 25000, quantity: 1, stock: 10 })

    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [] }))
    vi.stubGlobal('fetch', fetchMock)

    const { wrapper } = await mountCart()
    await wrapper.get('[data-testid="remove-1"]').trigger('click')
    await flushPromises()

    expect(fetchMock.mock.calls[0][1].method).toBe('DELETE')
    expect(wrapper.text()).toContain('Keranjang kosong.')
  })

  it('checkout posts WITHOUT items payload (server cart wins), refreshes, navigates', async () => {
    seedCart(
      { product_id: 1, name: 'Kopi', price: 25000, quantity: 2, stock: 10 },
      { product_id: 2, name: 'Teh', price: 15000, quantity: 1, stock: 10 },
    )

    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse({ data: { id: 9, status: 'pending' }, message: 'ok' }, true, 201)) // POST checkout
      .mockResolvedValueOnce(jsonResponse({ data: [] })) // GET cart (refresh setelah sukses)
    vi.stubGlobal('fetch', fetchMock)

    const { router, wrapper } = await mountCart()
    await wrapper.get('[data-testid="checkout"]').trigger('click')
    await flushPromises()

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('/api/v1/checkout')
    expect(init.body).toBeUndefined() // TIDAK ada payload items — cart di server

    expect(fetchMock.mock.calls[1][0]).toBe('/api/v1/cart') // refresh
    expect(router.currentRoute.value.name).toBe('orders')
  })

  it('shows backend validation error and keeps cart on failure', async () => {
    seedCart({ product_id: 1, name: 'Kopi', price: 25000, quantity: 5, stock: 10 })

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      jsonResponse({ message: 'Stok tidak mencukupi — tersisa 2.' }, false, 422),
    ))

    const { router, wrapper } = await mountCart()
    await wrapper.get('[data-testid="checkout"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="checkout-error"]').text())
      .toContain('Stok tidak mencukupi')
    expect(useCartStore().items).toHaveLength(1) // gagal → keranjang TIDAK dikosongkan
    expect(router.currentRoute.value.name).toBe('cart')
  })
})
