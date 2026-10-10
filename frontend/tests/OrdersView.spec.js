import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import OrdersView from '../src/views/OrdersView.vue'

const jsonResponse = (body, ok = true, status = 200) => ({
  ok,
  status,
  json: async () => body,
})

describe('OrdersView', () => {
  it('renders orders from api (cursor pagination shape)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({
      data: [
        { id: 7, status: 'paid', total: 40000, transaction_id: 'txn_1', items: [{}, {}] },
        { id: 8, status: 'pending', total: 25000, transaction_id: null, items: [{}] },
      ],
      meta: { next_cursor: null },
    })))

    const wrapper = mount(OrdersView)
    await flushPromises()

    expect(wrapper.text()).toContain('paid')
    expect(wrapper.text()).toContain('txn_1')
    expect(wrapper.text()).toContain('pending')
    expect(wrapper.text()).toContain('—') // tanpa transaction_id
  })

  it('shows empty message when no orders', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({
      data: [],
      meta: { next_cursor: null },
    })))

    const wrapper = mount(OrdersView)
    await flushPromises()

    expect(wrapper.text()).toContain('Belum ada pesanan.')
  })

  it('shows error message on api failure', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      jsonResponse({ message: 'Unauthenticated.' }, false, 401),
    ))

    const wrapper = mount(OrdersView)
    await flushPromises()

    expect(wrapper.text()).toContain('Unauthenticated.')
  })
})
