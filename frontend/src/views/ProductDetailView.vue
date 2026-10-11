<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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
    // ProductResource membungkus payload di { data: ... } — harus di-unwrap
    const res = await api.raw<{ data: Product }>(`/products/${route.params.slug}`)
    product.value = res.data
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

// Opsi produk = JSON dinamis → baris label: nilai yang enak dibaca
const optionLabels: Record<string, string> = {
  color: 'Warna',
  weight_gram: 'Berat',
}

const optionEntries = computed<{ label: string; value: string }[]>(() =>
  Object.entries(product.value?.options ?? {}).map(([key, raw]) => {
    const value = typeof raw === 'number'
      ? raw.toLocaleString('id-ID')
      : String(raw).replace(/_/g, ' ')
    return {
      label: optionLabels[key] ?? key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
      value: key.endsWith('_gram') ? `${value} gram` : value,
    }
  }),
)

// Aksi ke store global — badge di App.vue ikut berubah otomatis
const inCartQty = computed(
  () => cart.items.find((i) => i.product_id === product.value?.id)?.quantity ?? 0,
)

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
      <dd class="text-ink">
        <span
          v-if="optionEntries.length === 0"
          class="text-ink-muted"
        >—</span>
        <ul
          v-else
          class="flex flex-wrap gap-x-4 gap-y-1"
        >
          <li
            v-for="opt in optionEntries"
            :key="opt.label"
          >
            <span class="text-ink-muted">{{ opt.label }}:</span>
            <span class="font-mono text-xs">{{ opt.value }}</span>
          </li>
        </ul>
      </dd>
    </dl>

    <button
      data-testid="add-to-cart"
      :disabled="product.stock < 1 || inCartQty >= product.stock"
      class="cursor-pointer rounded-md bg-accent px-4 py-2 font-medium text-white transition-colors duration-150 hover:bg-accent-strong disabled:cursor-not-allowed disabled:opacity-40"
      @click="addToCart"
    >
      {{
        product.stock < 1
          ? 'Stok habis'
          : inCartQty >= product.stock
            ? `Sudah di keranjang (${inCartQty})`
            : 'Tambah ke keranjang'
      }}
    </button>
  </template>
</template>
