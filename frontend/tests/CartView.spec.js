import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import CartView from '../src/views/CartView.vue'
import { useCartStore } from '../src/stores/cart'

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

describe('CartView', () => {
  it('shows empty message when cart is empty', () => {
    const wrapper = mount(CartView)

    expect(wrapper.text()).toContain('Keranjang kosong.')
  })

  it('renders items from store and updates quantity', async () => {
    const cart = useCartStore()
    cart.add({ id: 1, name: 'Kopi', price: 25000 }, 2)

    const wrapper = mount(CartView)

    expect(wrapper.text()).toContain('Kopi')
    expect(wrapper.text()).toContain('50.000')

    // Ubah qty via input → action store → total reaktif berubah
    await wrapper.get('[data-testid="qty-1"]').setValue('3')
    expect(cart.items[0].quantity).toBe(3)
    expect(wrapper.text()).toContain('75.000')
  })

  it('removes item via store action', async () => {
    const cart = useCartStore()
    cart.add({ id: 1, name: 'Kopi', price: 25000 })

    const wrapper = mount(CartView)
    await wrapper.get('[data-testid="remove-1"]').trigger('click')

    expect(cart.items).toHaveLength(0)
    expect(wrapper.text()).toContain('Keranjang kosong.')
  })
})
