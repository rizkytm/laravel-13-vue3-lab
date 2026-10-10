<script setup>
// Props vs emit — kontrak satu arah per komponen:
// data TURUN lewat props, event NAIK lewat emit.
// Komponen tidak pernah memutasi props (Vue warning + review checklist).
const props = defineProps({
  // Array object, wajib
  product: { type: Object, required: true },
})

// Event 'open' dipanggil saat kartu diklik — parent yang memutuskan navigasi
const emit = defineEmits(['open'])
</script>

<template>
  <div class="card" data-testid="product-card" @click="emit('open', props.product)">
    <strong>{{ product.name }}</strong>
    — Rp {{ Number(product.price).toLocaleString('id-ID') }}
    <small>(stok: {{ product.stock }})</small>
  </div>
</template>
