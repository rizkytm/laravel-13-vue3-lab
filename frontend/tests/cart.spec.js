import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import { beforeEach, describe, expect, it } from 'vitest'
import { useCartStore } from '../src/stores/cart'

const productA = { id: 1, name: 'Kopi', price: 25000 }
const productB = { id: 2, name: 'Teh', price: 15000 }

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
})

describe('cart store', () => {
  it('starts empty', () => {
    const cart = useCartStore()
    expect(cart.items).toEqual([])
    expect(cart.count).toBe(0)
    expect(cart.total).toBe(0)
  })

  it('add: item baru masuk, add lagi quantity-nya digabung', () => {
    const cart = useCartStore()

    cart.add(productA, 2)
    cart.add(productA, 3)
    cart.add(productB)

    expect(cart.items).toHaveLength(2)
    expect(cart.items[0].quantity).toBe(5) // digabung, bukan duplikat baris
    expect(cart.count).toBe(6)
  })

  it('getters total mengikuti perubahan quantity', () => {
    const cart = useCartStore()

    cart.add(productA, 2) // 50.000
    expect(cart.total).toBe(50000)

    cart.setQuantity(1, 1)
    expect(cart.total).toBe(25000)
  })

  it('setQuantity < 1 menghapus item (keputusan UX eksplisit)', () => {
    const cart = useCartStore()
    cart.add(productA)

    cart.setQuantity(1, 0)

    expect(cart.items).toHaveLength(0)
  })

  it('remove & clear', () => {
    const cart = useCartStore()
    cart.add(productA)
    cart.add(productB)

    cart.remove(1)
    expect(cart.items.map((i) => i.product_id)).toEqual([2])

    cart.clear()
    expect(cart.items).toHaveLength(0)
  })

  it('persists to localStorage via watch (deep)', async () => {
    const cart = useCartStore()

    cart.add(productA, 2)
    await nextTick() // watch default flush: 'post' — tunggu render cycle

    expect(JSON.parse(localStorage.getItem('lab_cart'))).toEqual([
      { product_id: 1, name: 'Kopi', price: 25000, quantity: 2 },
    ])
  })

  it('restores state from corrupted localStorage safely', () => {
    localStorage.setItem('lab_cart', '{rusak')

    // Store merehydrate saat pertama dipakai — gak boleh crash
    const cart = useCartStore()
    expect(cart.items).toEqual([])
  })
})
