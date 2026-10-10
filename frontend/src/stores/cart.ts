import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import type { CartItem, Product } from '../types'

/**
 * Cart store — gaya "setup store" (Composition API di dalam store):
 * state = ref, getter = computed, action = fungsi biasa.
 * Kontras Vuex: gak ada mutation, action langsung mutasi state,
 * gak ada module boilerplate.
 */
export const useCartStore = defineStore('cart', () => {
  // --- State ---
  // localStorage = persistence eksternal → treat sebagai input tak terpercaya
  const items = ref<CartItem[]>(loadSaved())

  // --- Getters (computed: cache by dependency) ---
  const count = computed(() => items.value.reduce((n, i) => n + i.quantity, 0))
  const total = computed(() => items.value.reduce((n, i) => n + i.price * i.quantity, 0))

  // --- Actions ---
  function add(product: Product, quantity = 1): void {
    const existing = items.value.find((i) => i.product_id === product.id)
    if (existing) {
      existing.quantity += quantity
    } else {
      items.value.push({
        product_id: product.id,
        name: product.name,
        price: product.price,
        quantity,
      })
    }
  }

  function setQuantity(productId: number, quantity: number): void {
    if (quantity < 1) {
      remove(productId)
      return
    }
    const item = items.value.find((i) => i.product_id === productId)
    if (item) item.quantity = quantity
  }

  function remove(productId: number): void {
    items.value = items.value.filter((i) => i.product_id !== productId)
  }

  function clear(): void {
    items.value = []
  }

  // --- Side effect via watch: persist setiap state berubah ---
  // watch (bukan watchEffect): dependensi eksplisit = items saja,
  // deep karena mutasi terjadi di dalam array (push, properti quantity)
  watch(items, (value) => localStorage.setItem('lab_cart', JSON.stringify(value)), {
    deep: true,
  })

  return { items, count, total, add, setQuantity, remove, clear }
})

function loadSaved(): CartItem[] {
  try {
    const parsed: unknown = JSON.parse(localStorage.getItem('lab_cart') || '[]')
    return Array.isArray(parsed) ? (parsed as CartItem[]) : []
  } catch {
    return [] // JSON korup → reset, jangan crash
  }
}
