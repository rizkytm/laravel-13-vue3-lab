<script setup>
import { useCartStore } from '../stores/cart'

// Store = state global tunggal — semua komponen melihat angka yang sama
const cart = useCartStore()
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
    <button @click="cart.clear()">Kosongkan</button>
  </p>
</template>
