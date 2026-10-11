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

// Warna semantic — teks status selalu ikut (jangan warna sendirian)
function statusClass(status: string): string {
  if (status === 'paid') return 'text-success'
  if (status === 'pending') return 'text-warn'
  return 'text-danger'
}

onMounted(load)
</script>

<template>
  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">
      Pesanan Saya
    </h1>
    <button
      :disabled="loading"
      class="cursor-pointer rounded-md border border-line bg-raised px-3 py-1.5 text-sm text-ink hover:border-ink-muted disabled:cursor-not-allowed disabled:opacity-40"
      @click="load"
    >
      {{ loading ? 'Memuat…' : 'Muat ulang' }}
    </button>
  </div>

  <p
    v-if="error"
    class="text-danger"
  >
    {{ error.message }}
  </p>
  <p
    v-else-if="loading && !data"
    class="text-ink-muted"
  >
    Memuat…
  </p>

  <p
    v-else-if="data && data.data.length === 0"
    class="py-6 text-center text-ink-muted"
  >
    Belum ada pesanan.
  </p>

  <table
    v-else-if="data"
    data-testid="orders-table"
    class="w-full border-collapse text-sm"
  >
    <thead>
      <tr class="border-b border-ink-muted text-left text-ink-muted">
        <th class="py-2 pr-3 font-medium">
          #
        </th>
        <th class="py-2 pr-3 font-medium">
          Status
        </th>
        <th class="py-2 pr-3 font-medium">
          Total
        </th>
        <th class="py-2 pr-3 font-medium">
          Item
        </th>
        <th class="py-2 font-medium">
          Transaction
        </th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="order in data.data"
        :key="order.id"
        class="border-b border-line"
      >
        <td class="py-2.5 pr-3 tabular-nums">
          <router-link
            :to="{ name: 'order-detail', params: { id: order.id } }"
            data-testid="order-link"
            class="text-accent no-underline hover:underline"
          >
            #{{ order.id }}
          </router-link>
        </td>
        <!-- 'pending' → 'paid' setelah queue worker memproses (M5) -->
        <td
          data-testid="order-status"
          class="py-2.5 pr-3 font-medium"
          :class="statusClass(order.status)"
        >
          {{ order.status }}
        </td>
        <td class="py-2.5 pr-3 tabular-nums text-ink">
          Rp {{ order.total.toLocaleString('id-ID') }}
        </td>
        <td class="py-2.5 pr-3 tabular-nums text-ink-muted">
          {{ order.items_count ?? order.items?.length ?? 0 }}
        </td>
        <!-- transaction_id = artifact domain: mono -->
        <td class="py-2.5 font-mono text-xs text-ink-muted">
          {{ order.transaction_id || '—' }}
        </td>
      </tr>
    </tbody>
  </table>
</template>
