<script setup>
import { useRouter } from 'vue-router'
import { api } from '../api'
import { useApi } from '../composables/useApi'
import { useCartStore } from '../stores/cart'

// Store = state global tunggal — semua komponen melihat angka yang sama
const cart = useCartStore()
const router = useRouter()
const { loading, error, execute } = useApi()

/**
 * Checkout penuh: cart (localStorage) → POST /api/v1/checkout →
 * backend transaksi + queue payment. Sukses → keranjang dikosongkan
 * dan pindah ke halaman pesanan (status 'pending', jadi 'paid' oleh worker).
 */
async function checkout() {
  const result = await execute(() =>
    api.post('/checkout', {
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
          @change="cart.setQuantity(item.product_id, Number($event.target.value))"
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
