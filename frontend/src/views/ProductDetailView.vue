<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api } from '../api'
import { useCartStore } from '../stores/cart'
import type { Product } from '../types'

// Dynamic route: /products/:slug → params.slug
const route = useRoute()
const router = useRouter()
const cart = useCartStore()
const { t } = useI18n()

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
      ? t('detail.notFound')
      : status === 401
        ? t('common.unauth')
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
  color: 'detail.color',
  weight_gram: 'detail.weight',
}

const optionEntries = computed<{ label: string; value: string }[]>(() =>
  Object.entries(product.value?.options ?? {}).map(([key, raw]) => {
    const value = typeof raw === 'number'
      ? raw.toLocaleString('id-ID')
      : String(raw).replace(/_/g, ' ')
    return {
      label: optionLabels[key] ? t(optionLabels[key]) : key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
      value: key.endsWith('_gram') ? `${value} gram` : value,
    }
  }),
)

// Aksi ke store global — badge di App.vue ikut berubah otomatis
const inCartQty = computed(
  () => cart.items.find((i) => i.product_id === product.value?.id)?.quantity ?? 0,
)

function addToCart(): void {
  // Server otoritatif: clamp stok + persist ke cart_items — client cukup
  // kirim id produk; badge nav ikut berubah setelah respons mengganti items
  if (product.value) void cart.add(product.value.id)
}
</script>

<template>
  <button
    class="mb-5 cursor-pointer border-0 bg-transparent p-0 text-sm text-accent hover:text-accent-strong"
    @click="back"
  >
    {{ t('detail.back') }}
  </button>

  <p
    v-if="loading"
    class="text-ink-muted"
  >
    {{ t('common.loading') }}
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
        {{ t('common.price') }}
      </dt>
      <dd class="tabular-nums text-ink">
        Rp {{ product.price.toLocaleString('id-ID') }}
      </dd>
      <dt class="text-ink-muted">
        {{ t('detail.stock') }}
      </dt>
      <dd :class="product.stock < 1 ? 'text-danger' : 'text-ink'">
        {{ product.stock }}
      </dd>
      <dt class="text-ink-muted">
        {{ t('detail.options') }}
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
          ? t('detail.outOfStock')
          : inCartQty >= product.stock
            ? t('detail.inCart', { n: inCartQty })
            : t('detail.addToCart')
      }}
    </button>
  </template>
</template>
