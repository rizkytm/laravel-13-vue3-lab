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
  <h1 class="mb-4 text-2xl font-semibold tracking-tight text-ink">
    Produk
  </h1>

  <label class="mb-5 block">
    <span class="sr-only">Cari produk</span>
    <input
      v-model="search"
      data-testid="search"
      type="search"
      placeholder="Cari produk…"
      class="w-full rounded-md border border-line bg-raised px-3 py-2 text-ink placeholder:text-ink-muted focus:border-accent"
    >
  </label>

  <p
    v-if="loading"
    class="text-ink-muted"
  >
    Memuat…
  </p>
  <p
    v-else-if="error"
    class="text-danger"
  >
    {{ error }}
  </p>

  <p
    v-else-if="filtered.length === 0"
    class="py-6 text-center text-ink-muted"
  >
    Tidak ada produk cocok.
  </p>

  <div
    v-else
    class="border-t border-line"
  >
    <ProductCard
      v-for="product in filtered"
      :key="product.id"
      :product="product"
      @open="open"
    />
  </div>
</template>
