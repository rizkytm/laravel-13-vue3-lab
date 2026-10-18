import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../api'
import type { CartItem } from '../types'

/**
 * Cart store — keranjang SERVER-SIDE (tahap 3).
 * State = cache dari tabel cart_items; setiap action memanggil API lalu
 * ME-REPLACE items dengan cart penuh dari respons — satu sumber kebenaran
 * (server), tidak ada logika merge/clamp di client lagi.
 *
 * Gaya "setup store" (Composition API di dalam store): state = ref,
 * getter = computed, action = fungsi biasa.
 */
export const useCartStore = defineStore('cart', () => {
  // --- State ---
  const items = ref<CartItem[]>([])

  // --- Getters (computed: cache by dependency) ---
  const count = computed(() => items.value.reduce((n, i) => n + i.quantity, 0))
  const total = computed(() => items.value.reduce((n, i) => n + i.price * i.quantity, 0))

  // --- Actions (semua async — server yang menghitung clamp stok) ---
  async function fetch(): Promise<void> {
    const res = await api.raw<{ data: CartItem[] }>('/cart')
    items.value = res.data
  }

  async function add(productId: number, quantity = 1): Promise<void> {
    const res = await api.raw<{ data: CartItem[] }>('/cart/items', {
      method: 'POST',
      body: { product_id: productId, quantity },
    })
    items.value = res.data
  }

  async function setQuantity(productId: number, quantity: number): Promise<void> {
    // quantity < 1 = keputusan UX "hapus item" (konsisten dengan versi localStorage)
    if (quantity < 1) {
      await remove(productId)
      return
    }
    const res = await api.raw<{ data: CartItem[] }>(`/cart/items/${productId}`, {
      method: 'PATCH',
      body: { quantity },
    })
    items.value = res.data
  }

  async function remove(productId: number): Promise<void> {
    const res = await api.raw<{ data: CartItem[] }>(`/cart/items/${productId}`, {
      method: 'DELETE',
    })
    items.value = res.data
  }

  async function clear(): Promise<void> {
    const res = await api.raw<{ data: CartItem[] }>('/cart', { method: 'DELETE' })
    items.value = res.data
  }

  /**
   * Terima update stok realtime (broadcast StockUpdated) — clamp quantity
   * lokal ke stok baru TANPA panggil API (ini optimis lokal; angka final
   * tetap server saat checkout). Item yang quantity-nya jadi 0 tidak
   * dihapus otomatis — biar user melihat "stok habis", bukan hilang diam-diam.
   */
  function applyStockUpdate(productId: number, stock: number): void {
    const item = items.value.find((i) => i.product_id === productId)
    if (item) {
      item.stock = stock
      item.quantity = Math.min(item.quantity, stock)
    }
  }

  return { items, count, total, fetch, add, setQuantity, remove, clear, applyStockUpdate }
})
