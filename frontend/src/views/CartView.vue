<script setup lang="ts">
import { useRouter } from 'vue-router'
import { api } from '../api'
import { useApi } from '../composables/useApi'
import { useCartStore } from '../stores/cart'
import type { Order } from '../types'

// Store = state global tunggal — semua komponen melihat angka yang sama
const cart = useCartStore()
const router = useRouter()
const { loading, error, execute } = useApi<{ data: Order; message: string }>()

/**
 * Checkout penuh: cart (localStorage) → POST /api/v1/checkout →
 * backend transaksi + queue payment. Sukses → keranjang dikosongkan
 * dan pindah ke halaman pesanan (status 'pending', jadi 'paid' oleh worker).
 */
async function checkout(): Promise<void> {
  const result = await execute(() =>
    api.post<{ data: Order; message: string }>('/checkout', {
      items: cart.items.map((i) => ({
        product_id: i.product_id,
        quantity: i.quantity,
      })),
    }),
  )

  if (result) {
    cart.clear()
    router.push({ name: 'orders' })
  }
}

// Handler eksplisit (bukan $event.target di template) — cast aman di satu tempat
function onQtyChange(productId: number, event: Event): void {
  const value = Number((event.target as HTMLInputElement).value)
  cart.setQuantity(productId, value)
}
</script>

<template>
  <h1>Keranjang</h1>

  <p v-if="cart.items.length === 0">Keranjang kosong.</p>

  <table v-else data-testid="cart-table">
    <tr>
      <th>Produk</th>
      <th>Harga</th>
      <th>Qty</th>
      <th>Subtotal</th>
      <th></th>
    </tr>
    <tr v-for="item in cart.items" :key="item.product_id">
      <td>{{ item.name }}</td>
      <td>Rp {{ item.price.toLocaleString('id-ID') }}</td>
      <td>
        <input
          type="number"
          min="1"
          :value="item.quantity"
          :data-testid="`qty-${item.product_id}`"
          @change="onQtyChange(item.product_id, $event)"
        >
      </td>
      <td>Rp {{ (item.price * item.quantity).toLocaleString('id-ID') }}</td>
      <td>
        <button :data-testid="`remove-${item.product_id}`" @click="cart.remove(item.product_id)">
          Hapus
        </button>
      </td>
    </tr>
    <tr>
      <th colspan="3">Total</th>
      <th colspan="2">Rp {{ cart.total.toLocaleString('id-ID') }}</th>
    </tr>
  </table>

  <p v-if="cart.items.length">
    <button data-testid="clear-cart" @click="cart.clear()">Kosongkan</button>
    <button
      data-testid="checkout"
      :disabled="loading"
      @click="checkout"
    >
      {{ loading ? 'Memproses…' : 'Checkout' }}
    </button>
  </p>

  <!-- Error dari Laravel (validasi stok, 401, dll) -->
  <p v-if="error" class="err" data-testid="checkout-error">{{ error.message }}</p>
</template>
