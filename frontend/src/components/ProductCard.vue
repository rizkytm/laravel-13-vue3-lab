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
  <div class="card" data-testid="product-card" @click="emit('open', props.product)">
    <strong>{{ product.name }}</strong>
    — Rp {{ product.price.toLocaleString('id-ID') }}
    <small>(stok: {{ product.stock }})</small>
  </div>
</template>
