<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import { useCartStore } from '../stores/cart'
import type { Product } from '../types'

// Dynamic route: /products/:slug → params.slug
const route = useRoute()
const router = useRouter()
const cart = useCartStore()

const product = ref<Product | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const res = await api.raw<Product>(`/products/${route.params.slug}`)
    product.value = res
  } catch (e) {
    const status = (e as { status?: number }).status
    error.value = status === 404
      ? 'Produk tidak ditemukan.'
      : status === 401
        ? 'Silakan login terlebih dahulu.'
        : (e as Error).message
  } finally {
    loading.value = false
  }
})

function back(): void {
  router.push({ name: 'products' })
}

// Aksi ke store global — badge di App.vue ikut berubah otomatis
function addToCart(): void {
  if (product.value) cart.add(product.value)
}
</script>

<template>
  <button
    class="mb-5 cursor-pointer border-0 bg-transparent p-0 text-sm text-accent hover:text-accent-strong"
    @click="back"
  >
    ← Kembali
  </button>

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

  <template v-else-if="product">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">
      {{ product.name }}
    </h1>

    <!-- Artifact domain: harga = angka tabular, opsi = data mentah -->
    <dl class="my-5 grid max-w-sm grid-cols-[auto_1fr] gap-x-6 gap-y-1.5 text-sm">
      <dt class="text-ink-muted">
        Harga
      </dt>
      <dd class="tabular-nums text-ink">
        Rp {{ product.price.toLocaleString('id-ID') }}
      </dd>
      <dt class="text-ink-muted">
        Stok
      </dt>
      <dd :class="product.stock < 1 ? 'text-danger' : 'text-ink'">
        {{ product.stock }}
      </dd>
      <dt class="text-ink-muted">
        Opsi
      </dt>
      <dd class="font-mono text-xs text-ink">
        {{ JSON.stringify(product.options) }}
      </dd>
    </dl>

    <button
      data-testid="add-to-cart"
      :disabled="product.stock < 1"
      class="cursor-pointer rounded-md bg-accent px-4 py-2 font-medium text-white transition-colors duration-150 hover:bg-accent-strong disabled:cursor-not-allowed disabled:opacity-40"
      @click="addToCart"
    >
      {{ product.stock < 1 ? 'Stok habis' : 'Tambah ke keranjang' }}
    </button>
  </template>
</template>
