<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import ProductCard from '../components/ProductCard.vue'

const router = useRouter()

// --- State reaktivitas Composition API ---
const products = ref([])      // ref: array state yang di-watch Vue
const loading = ref(true)     // primitive
const error = ref('')
const search = ref('')        // input pencarian

// --- Computed: nilai TURUNAN dari state lain, di-cache & re-evaluate
// otomatis saat dependensinya berubah (banding method: hitung ulang tiap render)
const filtered = computed(() => {
  const q = search.value.toLowerCase()
  return products.value.filter((p) => p.name.toLowerCase().includes(q))
})

// --- Lifecycle: fetch sekali setelah komponen mount ke DOM
onMounted(async () => {
  try {
    const data = await api.get('/products?per_page=50')
    products.value = data.data // shape: {data: [...], meta: {...}} (API Resource)
  } catch (e) {
    // 401 = belum login → arahkan ke halaman login
    error.value = e.status === 401 ? 'Silakan login terlebih dahulu.' : e.message
  } finally {
    loading.value = false
  }
})

function open(product) {
  // Emit dari card ditangkap di sini → parent yang navigasi
  router.push({ name: 'product-detail', params: { slug: product.slug } })
}
</script>

<template>
  <h1>Produk</h1>

  <input
    v-model="search"
    data-testid="search"
    placeholder="Cari produk…"
  >

  <p v-if="loading">Memuat…</p>
  <p v-else-if="error" class="err">{{ error }}</p>

  <p v-else-if="filtered.length === 0">Tidak ada produk cocok.</p>

  <ProductCard
    v-for="product in filtered"
    :key="product.id"
    :product="product"
    @open="open"
  />
</template>
