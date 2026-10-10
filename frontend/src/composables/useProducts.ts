import { ref } from 'vue'
import { api } from '../api'
import type { Product } from '../types'

/**
 * Composable: ambil daftar produk.
 * Logic fetch yang TADI inline di ProductListView (F1) sekarang di-extract —
 * komponen lain bisa pakai tanpa menyalin (DRY di level komponen).
 *
 * Composable = fungsi biasa yang mengembalikan ref/computed —
 * state hidup PER KOMponen yang memanggilnya (bukan global seperti store).
 */
export function useProducts() {
  const products = ref<Product[]>([])
  const loading = ref(true)
  const error = ref('')

  async function load(query = ''): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const res = await api.get<Product>(`/products?per_page=50${query}`)
      products.value = res.data
    } catch (e) {
      const status = (e as { status?: number }).status
      error.value = status === 401 ? 'Silakan login terlebih dahulu.' : (e as Error).message
    } finally {
      loading.value = false
    }
  }

  return { products, loading, error, load }
}
