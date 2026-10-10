import { ref } from 'vue'
import { api } from '../api'

/**
 * Composable: ambil daftar produk.
 * Logic fetch yang TADI inline di ProductListView (F1) sekarang di-extract —
 * komponen lain bisa pakai tanpa menyalin (DRY di level komponen).
 *
 * Composable = fungsi biasa yang mengembalikan ref/computed —
 * state hidup PER KOMponen yang memanggilnya (bukan global seperti store).
 */
export function useProducts() {
  const products = ref([])
  const loading = ref(true)
  const error = ref('')

  async function load(query = '') {
    loading.value = true
    error.value = ''
    try {
      const data = await api.get(`/products?per_page=50${query}`)
      products.value = data.data
    } catch (e) {
      error.value = e.status === 401 ? 'Silakan login terlebih dahulu.' : e.message
    } finally {
      loading.value = false
    }
  }

  return { products, loading, error, load }
}
