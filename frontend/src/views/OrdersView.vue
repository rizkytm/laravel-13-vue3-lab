<script setup lang="ts">
import { onMounted } from 'vue'
import { api } from '../api'
import { useApi } from '../composables/useApi'
import type { Order, Paginated } from '../types'

// Order history dari API (auth via session cookie Sanctum SPA)
const { data, loading, error, execute } = useApi<Paginated<Order>>()

function load(): Promise<Paginated<Order> | null> {
  return execute(() => api.get<Order>('/orders?per_page=20'))
}

onMounted(load)
</script>

<template>
  <h1>Pesanan Saya</h1>

  <button :disabled="loading" @click="load">
    {{ loading ? 'Memuat…' : 'Muat ulang' }}
  </button>

  <p v-if="error" class="err">{{ error.message }}</p>
  <p v-else-if="loading && !data">Memuat…</p>

  <p v-else-if="data && data.data.length === 0">Belum ada pesanan.</p>

  <table v-else-if="data" data-testid="orders-table">
    <tr>
      <th>#</th>
      <th>Status</th>
      <th>Total</th>
      <th>Item</th>
      <th>Transaction</th>
    </tr>
    <tr v-for="order in data.data" :key="order.id">
      <td>{{ order.id }}</td>
      <!-- 'pending' → 'paid' setelah queue worker memproses (M5) -->
      <td data-testid="order-status">{{ order.status }}</td>
      <td>Rp {{ order.total.toLocaleString('id-ID') }}</td>
      <td>{{ order.items_count ?? order.items?.length ?? 0 }}</td>
      <td>{{ order.transaction_id || '—' }}</td>
    </tr>
  </table>
</template>
