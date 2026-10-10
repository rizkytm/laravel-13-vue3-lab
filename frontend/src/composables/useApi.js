import { ref } from 'vue'

/**
 * Composable generik: bungkus async function dengan state
 * loading + error → tiap pemakai gak menulis try/catch boilerplate lagi.
 *
 * Pakai bersama api.js:
 *   const { data, loading, error, execute } = useApi()
 *   execute(() => api.get('/orders'))
 */
export function useApi() {
  const data = ref(null)
  const loading = ref(false)
  const error = ref(null) // Error object asli — akses .status/.errors

  async function execute(fn) {
    loading.value = true
    error.value = null
    data.value = null
    try {
      data.value = await fn()
      return data.value
    } catch (e) {
      error.value = e
      return null
    } finally {
      loading.value = false
    }
  }

  return { data, loading, error, execute }
}
