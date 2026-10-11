import { ref } from 'vue'
import { api } from '../api'
import type { Product } from '../types'

/**
 * Composable: ambil daftar produk (per halaman + urutan).
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
  const page = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  // Nilai select sorting: "kolom:arah" — dikirim sebagai ?sort=&dir=
  const sort = ref('created_at:desc')

  async function load(targetPage = 1): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const [column, dir] = sort.value.split(':')
      const res = await api.get<Product>(
        `/products?per_page=12&page=${targetPage}&sort=${column}&dir=${dir}`,
      )
      products.value = res.data
      page.value = res.meta?.current_page ?? 1
      lastPage.value = res.meta?.last_page ?? 1
      total.value = res.meta?.total ?? res.data.length
    } catch (e) {
      const status = (e as { status?: number }).status
      error.value = status === 401 ? 'Silakan login terlebih dahulu.' : (e as Error).message
    } finally {
      loading.value = false
    }
  }

  return { products, loading, error, page, lastPage, total, sort, load }
}
