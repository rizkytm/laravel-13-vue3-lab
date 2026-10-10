<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'

// Dynamic route: /products/:slug → params.slug
const route = useRoute()
const router = useRouter()

const product = ref(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const data = await api.get(`/products/${route.params.slug}`)
    product.value = data.data
  } catch (e) {
    error.value = e.status === 404
      ? 'Produk tidak ditemukan.'
      : e.status === 401
        ? 'Silakan login terlebih dahulu.'
        : e.message
  } finally {
    loading.value = false
  }
})

function back() {
  router.push({ name: 'products' })
}
</script>

<template>
  <button @click="back">← Kembali</button>

  <p v-if="loading">Memuat…</p>
  <p v-else-if="error" class="err">{{ error }}</p>

  <template v-else-if="product">
    <h1>{{ product.name }}</h1>
    <p>
      Harga: Rp {{ Number(product.price).toLocaleString('id-ID') }}<br>
      Stok: {{ product.stock }}<br>
      Opsi: {{ JSON.stringify(product.options) }}
    </p>
  </template>
</template>
