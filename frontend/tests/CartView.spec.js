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

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('CartView', () => {
  it('shows empty message when cart is empty', async () => {
    const { wrapper } = await mountCart()

    expect(wrapper.text()).toContain('Keranjang kosong.')
  })

  it('renders items from store and updates quantity', async () => {
    const cart = useCartStore()
    cart.add({ id: 1, name: 'Kopi', price: 25000, stock: 10 }, 2)

    const { wrapper } = await mountCart()

    expect(wrapper.text()).toContain('Kopi')
    expect(wrapper.text()).toContain('50.000')

    await wrapper.get('[data-testid="qty-1"]').setValue('3')
    expect(cart.items[0].quantity).toBe(3)
    expect(wrapper.text()).toContain('75.000')
  })

  it('removes item via store action', async () => {
    const cart = useCartStore()
    cart.add({ id: 1, name: 'Kopi', price: 25000, stock: 10 })

    const { wrapper } = await mountCart()
    await wrapper.get('[data-testid="remove-1"]').trigger('click')

    expect(cart.items).toHaveLength(0)
    expect(wrapper.text()).toContain('Keranjang kosong.')
  })

  it('checkout posts cart items to api, clears cart, navigates to orders', async () => {
    const cart = useCartStore()
    cart.add({ id: 1, name: 'Kopi', price: 25000, stock: 10 }, 2)
    cart.add({ id: 2, name: 'Teh', price: 15000, stock: 10 })

    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse({ data: { id: 9, status: 'pending' }, message: 'ok' }, true, 201),
    )
    vi.stubGlobal('fetch', fetchMock)

    const { router, wrapper } = await mountCart()
    await wrapper.get('[data-testid="checkout"]').trigger('click')
    await flushPromises()

    // Payload = bentuk yang diminta backend CheckoutRequest
    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('/api/v1/checkout')
    expect(JSON.parse(init.body)).toEqual({
      items: [
        { product_id: 1, quantity: 2 },
        { product_id: 2, quantity: 1 },
      ],
    })

    expect(cart.items).toHaveLength(0)
    expect(router.currentRoute.value.name).toBe('orders')
  })

  it('shows backend validation error and keeps cart on failure', async () => {
    const cart = useCartStore()
    cart.add({ id: 1, name: 'Kopi', price: 25000, stock: 10 }, 5)

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      jsonResponse({ message: 'Stok tidak mencukupi — tersisa 2.' }, false, 422),
    ))

    const { router, wrapper } = await mountCart()
    await wrapper.get('[data-testid="checkout"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="checkout-error"]').text())
      .toContain('Stok tidak mencukupi')
    expect(cart.items).toHaveLength(1) // gagal → keranjang TIDAK dikosongkan
    expect(router.currentRoute.value.name).toBe('cart')
  })
})
