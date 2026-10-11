<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useProducts } from '../composables/useProducts'
import ProductCard from '../components/ProductCard.vue'
import type { Product } from '../types'

const router = useRouter()
const { t } = useI18n()

// Fetch logic di-extract ke composable (F2) — komponen tinggal pakai
const { products, loading, error, page, lastPage, total, sort, load } = useProducts()

// Input pencarian
const search = ref('')

// --- Computed: nilai TURUNAN dari state lain, di-cache & re-evaluate
// otomatis saat dependensinya berubah (banding method: hitung ulang tiap render)
const filtered = computed<Product[]>(() => {
  const q = search.value.toLowerCase()
  return products.value.filter((p) => p.name.toLowerCase().includes(q))
})

onMounted(() => load())

// Ganti urutan → kembali ke halaman 1 dengan urutan baru
watch(sort, () => load(1))

function open(product: Product): void {
  // Emit dari card ditangkap di sini → parent yang navigasi
  router.push({ name: 'product-detail', params: { slug: product.slug } })
}
</script>

<template>
  <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">
      {{ t('list.title') }}
    </h1>
    <label class="flex items-center gap-2 text-sm text-ink-muted">
      {{ t('list.sort') }}
      <select
        v-model="sort"
        data-testid="sort-select"
        class="rounded-md border border-line bg-raised px-2 py-1.5 text-ink focus:border-accent"
      >
        <option value="created_at:desc">{{ t('list.sortNewest') }}</option>
        <option value="name:asc">{{ t('list.sortName') }}</option>
        <option value="price:asc">{{ t('list.sortPriceAsc') }}</option>
        <option value="price:desc">{{ t('list.sortPriceDesc') }}</option>
      </select>
    </label>
  </div>

  <label class="mb-5 block">
    <span class="sr-only">{{ t('list.search') }}</span>
    <input
      v-model="search"
      data-testid="search"
      type="search"
      :placeholder="t('list.searchPlaceholder')"
      class="w-full rounded-md border border-line bg-raised px-3 py-2 text-ink placeholder:text-ink-muted focus:border-accent"
    >
  </label>

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

  <p
    v-else-if="filtered.length === 0"
    class="py-6 text-center text-ink-muted"
  >
    {{ t('list.empty') }}
  </p>

  <template v-else>
    <div class="border-t border-line">
      <ProductCard
        v-for="product in filtered"
        :key="product.id"
        :product="product"
        @open="open"
      />
    </div>

    <!-- Pagination offset: nomor halaman bisa di-share, query tetap sederhana -->
    <div
      v-if="lastPage > 1"
      class="mt-5 flex items-center justify-between text-sm"
    >
      <button
        data-testid="prev-page"
        :disabled="loading || page <= 1"
        class="cursor-pointer rounded-md border border-line bg-raised px-3 py-1.5 text-ink hover:border-ink-muted disabled:cursor-not-allowed disabled:opacity-40"
        @click="load(page - 1)"
      >
        {{ t('list.prev') }}
      </button>
      <span
        data-testid="page-info"
        class="tabular-nums text-ink-muted"
      >
        {{ t('list.pageInfo', { page, last: lastPage, total }) }}
      </span>
      <button
        data-testid="next-page"
        :disabled="loading || page >= lastPage"
        class="cursor-pointer rounded-md border border-line bg-raised px-3 py-1.5 text-ink hover:border-ink-muted disabled:cursor-not-allowed disabled:opacity-40"
        @click="load(page + 1)"
      >
        {{ t('list.next') }}
      </button>
    </div>
  </template>
</template>
