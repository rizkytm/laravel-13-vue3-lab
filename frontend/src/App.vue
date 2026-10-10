<script setup>
import { inject, ref } from 'vue'
import { useRouter } from 'vue-router'
import { clearToken, getToken } from './api'
import { useCartStore } from './stores/cart'

const router = useRouter()
// inject: nilai dari app.provide('appConfig') — tanpa prop drilling
const config = inject('appConfig')

const isLoggedIn = ref(!!getToken())
const cart = useCartStore() // store global — state sama di semua komponen

function logout() {
  clearToken()
  isLoggedIn.value = false
  router.push({ name: 'login' })
}
</script>

<template>
  <nav>
    <strong>{{ config.appName }}</strong>
    <router-link to="/">Produk</router-link>
    <!-- Badge langsung reaktif: cart.count berubah → render ulang otomatis -->
    <router-link :to="{ name: 'cart' }">
      Keranjang ({{ cart.count }}) — Rp {{ cart.total.toLocaleString('id-ID') }}
    </router-link>
    <button v-if="isLoggedIn" @click="logout">Logout</button>
    <router-link v-else to="/login">Login</router-link>
  </nav>

  <router-view />
</template>

<style>
body {
  font-family: sans-serif;
  max-width: 720px;
  margin: 1.5rem auto;
  padding: 0 1rem;
}
nav { margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
.card { border: 1px solid #ccc; border-radius: 6px; padding: .75rem; margin-bottom: .5rem; cursor: pointer; }
.card:hover { background: #f5f5f5; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ccc; padding: .5rem; text-align: left; }
.err { color: red; }
</style>
