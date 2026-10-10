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
  <button @click="back">
    ← Kembali
  </button>

  <p v-if="loading">
    Memuat…
  </p>
  <p
    v-else-if="error"
    class="err"
  >
    {{ error }}
  </p>

  <template v-else-if="product">
    <h1>{{ product.name }}</h1>
    <p>
      Harga: Rp {{ product.price.toLocaleString('id-ID') }}<br>
      Stok: {{ product.stock }}<br>
      Opsi: {{ JSON.stringify(product.options) }}
    </p>
    <button
      data-testid="add-to-cart"
      :disabled="product.stock < 1"
      @click="addToCart"
    >
      Tambah ke keranjang
    </button>
  </template>
</template>
