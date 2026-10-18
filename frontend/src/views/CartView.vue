<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api } from '../api'
import { useApi } from '../composables/useApi'
import { useCartStore } from '../stores/cart'
import type { Order } from '../types'

// Store = state global tunggal — semua komponen melihat angka yang sama
const cart = useCartStore()
const router = useRouter()
const { t } = useI18n()
const { loading, error, execute } = useApi<{ data: Order; message: string }>()

/**
 * Checkout: POST /api/v1/checkout TANPA payload items — backend membaca
 * cart server-side (tabel cart_items). Sukses → refresh cart (sudah
 * dikosongkan backend) dan pindah ke halaman pesanan (status 'pending',
 * jadi 'paid' oleh worker).
 */
async function checkout(): Promise<void> {
  const result = await execute(() =>
    api.post<{ data: Order; message: string }>('/checkout'),
  )

  if (result) {
    await cart.fetch() // cart sudah dikosongkan backend dalam transaksi
    router.push({ name: 'orders' })
  }
}

// Handler eksplisit (bukan $event.target di template) — cast aman di satu tempat
function onQtyChange(productId: number, event: Event): void {
  const value = Number((event.target as HTMLInputElement).value)
  void cart.setQuantity(productId, value)
}
</script>

<template>
  <h1 class="mb-4 text-2xl font-semibold tracking-tight text-ink">
    {{ t('cart.title') }}
  </h1>

  <p
    v-if="cart.items.length === 0"
    class="py-6 text-center text-ink-muted"
  >
    {{ t('cart.empty') }}
  </p>

  <!-- Artifact: nota/receipt — baris + garis bawah, bukan grid penuh -->
  <table
    v-else
    data-testid="cart-table"
    class="w-full border-collapse text-sm"
  >
    <thead>
      <tr class="border-b border-ink-muted text-left text-ink-muted">
        <th class="py-2 pr-3 font-medium">
          {{ t('common.product') }}
        </th>
        <th class="py-2 pr-3 font-medium">
          {{ t('common.price') }}
        </th>
        <th class="py-2 pr-3 font-medium">
          {{ t('common.qty') }}
        </th>
        <th class="py-2 pr-3 font-medium">
          {{ t('common.subtotal') }}
        </th>
        <th class="py-2 font-medium">
          <span class="sr-only">{{ t('cart.action') }}</span>
        </th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="item in cart.items"
        :key="item.product_id"
        class="border-b border-line"
      >
        <td class="py-2.5 pr-3 text-ink">
          {{ item.name }}
        </td>
        <td class="py-2.5 pr-3 tabular-nums text-ink-muted">
          Rp {{ item.price.toLocaleString('id-ID') }}
        </td>
        <td class="py-2.5 pr-3">
          <input
            type="number"
            min="1"
            :max="item.stock"
            :value="item.quantity"
            :data-testid="`qty-${item.product_id}`"
            class="w-16 rounded border border-line bg-raised px-2 py-1 tabular-nums text-ink focus:border-accent"
            @change="onQtyChange(item.product_id, $event)"
          >
        </td>
        <td class="py-2.5 pr-3 tabular-nums text-ink">
          Rp {{ (item.price * item.quantity).toLocaleString('id-ID') }}
        </td>
        <td class="py-2.5 text-right">
          <button
            :data-testid="`remove-${item.product_id}`"
            class="cursor-pointer border-0 bg-transparent p-0 text-sm text-ink-muted hover:text-danger"
            @click="void cart.remove(item.product_id)"
          >
            {{ t('cart.remove') }}
          </button>
        </td>
      </tr>
      <tr class="border-b-2 border-ink-muted">
        <th
          colspan="3"
          class="py-3 pr-3 text-left font-semibold text-ink"
        >
          {{ t('common.total') }}
        </th>
        <th
          colspan="2"
          class="py-3 text-right font-semibold tabular-nums text-ink"
        >
          Rp {{ cart.total.toLocaleString('id-ID') }}
        </th>
      </tr>
    </tbody>
  </table>

  <div
    v-if="cart.items.length"
    class="mt-5 flex gap-3"
  >
    <button
      data-testid="clear-cart"
      class="cursor-pointer rounded-md border border-line bg-raised px-4 py-2 text-ink hover:border-ink-muted"
      @click="void cart.clear()"
    >
      {{ t('cart.clear') }}
    </button>
    <button
      data-testid="checkout"
      :disabled="loading"
      class="cursor-pointer rounded-md bg-accent px-4 py-2 font-medium text-white transition-colors duration-150 hover:bg-accent-strong disabled:cursor-not-allowed disabled:opacity-40"
      @click="checkout"
    >
      {{ loading ? t('cart.processing') : t('cart.checkout') }}
    </button>
  </div>

  <!-- Error dari Laravel (validasi stok, 401, dll) -->
  <p
    v-if="error"
    class="mt-4 text-danger"
    data-testid="checkout-error"
  >
    {{ error.message }}
  </p>
</template>
