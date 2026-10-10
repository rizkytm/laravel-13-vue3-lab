<script setup lang="ts">
import type { Product } from '../types'

// Props vs emit — kontrak satu arah per komponen:
// data TURUN lewat props, event NAIK lewat emit.
// Type-based props: optional pakai `?` — tanpa `?` = wajib (required).
const props = defineProps<{
  product: Product
}>()

// Typed emits: tuple syntax — argumen event 'open' harus Product
const emit = defineEmits<{
  open: [product: Product]
}>()
</script>

<template>
  <!-- Baris scanning (Explore): flat, border-b, hover naik 1 tingkat — bukan grid kartu seragam -->
  <button
    data-testid="product-card"
    type="button"
    class="flex w-full cursor-pointer items-baseline gap-3 border-0 border-b border-line bg-transparent px-2 py-3 text-left transition-colors duration-150 hover:rounded-md hover:bg-raised"
    @click="emit('open', props.product)"
  >
    <span class="flex-1 font-medium text-ink">{{ product.name }}</span>
    <span class="tabular-nums text-ink">
      Rp {{ product.price.toLocaleString('id-ID') }}
    </span>
    <span
      class="w-20 text-right text-sm"
      :class="product.stock < 1 ? 'text-danger' : 'text-ink-muted'"
    >
      {{ product.stock < 1 ? 'Habis' : `stok ${product.stock}` }}
    </span>
  </button>
</template>
