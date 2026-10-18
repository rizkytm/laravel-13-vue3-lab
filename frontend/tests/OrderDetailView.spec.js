import { mount, flushPromises } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import OrderDetailView from '../src/views/OrderDetailView.vue'

const jsonResponse = (body, ok = true, status = 200) => ({
  ok,
  status,
  json: async () => body,
})

// Bentuk response OrderResource: payload dibungkus { data: ... }
const orderPayload = {
  data: {
    id: 7,
    status: 'paid',
    total: 75000,
    transaction_id: 'txn_abc123',
    paid_at: '2026-10-11T10:00:00+07:00',
    invoice_url: 'http://127.0.0.1:8000/orders/7/invoice?expires=1&signature=xyz',
    items_count: 1,
    items: [
      { id: 1, product_id: 2, quantity: 3, price: 25000, product: { id: 2, name: 'Kopi' } },
    ],
  },
}

async function mountDetail(fetchMock) {
  vi.stubGlobal('fetch', fetchMock)
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/orders', name: 'orders', component: { template: '<div>orders</div>' } },
      { path: '/orders/:id', name: 'order-detail', component: OrderDetailView },
      { path: '/login', name: 'login', component: { template: '<div>login</div>' } },
    ],
  })
  router.push('/orders/7')
  await router.isReady()
  return { router, wrapper: mount(OrderDetailView, { global: { plugins: [router] } }) }
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('OrderDetailView', () => {
  it('renders detail: status, total, item, link invoice signed', async () => {
    const { wrapper } = await mountDetail(
      vi.fn().mockResolvedValue(jsonResponse(orderPayload)),
    )
    await flushPromises()

    expect(wrapper.text()).toContain('Pesanan #7')
    expect(wrapper.get('[data-testid="order-status"]').text()).toBe('paid')
    expect(wrapper.text()).toContain('75.000')
    expect(wrapper.text()).toContain('Kopi')
    expect(wrapper.text()).toContain('txn_abc123')
    expect(wrapper.text()).toContain('25.000')

    // Link invoice = signed URL dari server, target tab baru
    const link = wrapper.get('[data-testid="invoice-link"]')
    expect(link.attributes('href')).toContain('/orders/7/invoice?expires=')
    expect(link.attributes('target')).toBe('_blank')
  })

  it('shows ownership error on 403', async () => {
    const { wrapper } = await mountDetail(
      vi.fn().mockResolvedValue(jsonResponse({ message: 'This action is unauthorized.' }, false, 403)),
    )
    await flushPromises()

    expect(wrapper.get('[data-testid="order-error"]').text()).toContain('bukan milik Anda')
  })

  it('hides invoice link when payload has no invoice_url', async () => {
    const tanpaInvoice = { data: { ...orderPayload.data, invoice_url: undefined } }
    const { wrapper } = await mountDetail(
      vi.fn().mockResolvedValue(jsonResponse(tanpaInvoice)),
    )
    await flushPromises()

    expect(wrapper.find('[data-testid="invoice-link"]').exists()).toBe(false)
  })

  it('hides cancel button for a paid order', async () => {
    const { wrapper } = await mountDetail(
      vi.fn().mockResolvedValue(jsonResponse(orderPayload)), // status paid
    )
    await flushPromises()

    expect(wrapper.find('[data-testid="cancel-order"]').exists()).toBe(false)
  })

  it('cancels a pending order and updates status to cancelled', async () => {
    const pendingPayload = { data: { ...orderPayload.data, status: 'pending', paid_at: null, transaction_id: null } }
    const cancelledPayload = { data: { ...orderPayload.data, status: 'cancelled' } }

    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse(pendingPayload)) // GET detail
      .mockResolvedValueOnce(jsonResponse(cancelledPayload)) // POST cancel
    vi.stubGlobal('fetch', fetchMock)

    const { wrapper } = await mountDetail(fetchMock)
    await flushPromises()

    // Tombol cancel hanya tampil untuk order pending
    const btn = wrapper.get('[data-testid="cancel-order"]')
    await btn.trigger('click')
    await flushPromises()

    const [url, init] = fetchMock.mock.calls[1]
    expect(url).toBe('/api/v1/orders/7/cancel')
    expect(init.method).toBe('POST')
    expect(wrapper.get('[data-testid="order-status"]').text()).toBe('cancelled')
    expect(wrapper.find('[data-testid="cancel-order"]').exists()).toBe(false) // hilang setelah batal
  })
})
