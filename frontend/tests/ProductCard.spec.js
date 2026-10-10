import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import ProductCard from '../src/components/ProductCard.vue'

const product = { id: 1, name: 'Kopi Robusta', slug: 'kopi-robusta', price: 25000, stock: 5 }

describe('ProductCard', () => {
  it('renders props (data turun ke child)', () => {
    const wrapper = mount(ProductCard, { props: { product } })

    expect(wrapper.text()).toContain('Kopi Robusta')
    expect(wrapper.text()).toContain('25.000')
    expect(wrapper.text()).toContain('stok: 5')
  })

  it('emits open with the product when clicked (event naik ke parent)', async () => {
    // Listener dipasang sebagai props onOpen — Vue memanggil handler ini
    // saat emit('open', ...) terjadi. (wrapper.emitted() tidak dipakai:
    // rekamannya via devtools hook tidak andal di kombinasi VTU/Vue ini)
    const onOpen = vi.fn()
    const wrapper = mount(ProductCard, { props: { product, onOpen } })

    await wrapper.find('[data-testid="product-card"]').trigger('click')

    expect(onOpen).toHaveBeenCalledTimes(1)
    expect(onOpen).toHaveBeenCalledWith(product)
  })

  it('does not mutate props', async () => {
    const wrapper = mount(ProductCard, { props: { product } })

    await wrapper.find('[data-testid="product-card"]').trigger('click')
    // toEqual (bukan toBe): props dibungkus reactive proxy oleh Vue —
    // referensi beda, isi identik
    expect(wrapper.props('product')).toEqual(product)
  })
})
