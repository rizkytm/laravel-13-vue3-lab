import { ref } from 'vue'
import type { ApiError } from '../types'

/**
 * Composable generik: bungkus async function dengan state
 * loading + error → tiap pemakai gak menulis try/catch boilerplate lagi.
 *
 * Pakai bersama api.ts:
 *   const { data, loading, error, execute } = useApi<Order>()
 *   execute(() => api.raw<Order>('/orders'))
 */
export function useApi<T = unknown>() {
  const data = ref<T | null>(null)
  const loading = ref(false)
  const error = ref<ApiError | null>(null) // Error object asli — akses .status/.errors

  async function execute(fn: () => Promise<T>): Promise<T | null> {
    loading.value = true
    error.value = null
    data.value = null
    try {
      data.value = await fn()
      return data.value
    } catch (e) {
      error.value = e as ApiError // api.ts melempar Error bertipe ApiError
      return null
    } finally {
      loading.value = false
    }
  }

  return { data, loading, error, execute }
}
