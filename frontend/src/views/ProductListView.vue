<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useProducts } from '../composables/useProducts'
import ProductCard from '../components/ProductCard.vue'
import type { Product } from '../types'

const router = useRouter()

// Fetch logic di-extract ke composable (F2) — komponen tinggal pakai
const { products, loading, error, load } = useProducts()

// Input pencarian
const search = ref('')

// --- Computed: nilai TURUNAN dari state lain, di-cache & re-evaluate
// otomatis saat dependensinya berubah (banding method: hitung ulang tiap render)
const filtered = computed<Product[]>(() => {
  const q = search.value.toLowerCase()
  return products.value.filter((p) => p.name.toLowerCase().includes(q))
})

onMounted(load)

function open(product: Product): void {
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

  <p v-if="loading">
    Memuat…
  </p>
  <p
    v-else-if="error"
    class="err"
  >
    {{ error }}
  </p>

  <p v-else-if="filtered.length === 0">
    Tidak ada produk cocok.
  </p>

  <ProductCard
    v-for="product in filtered"
    :key="product.id"
    :product="product"
    @open="open"
  />
</template>
