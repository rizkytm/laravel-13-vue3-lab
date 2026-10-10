<script setup lang="ts">
import { inject, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { useCartStore } from './stores/cart'
import type { AppConfig } from './types'

const router = useRouter()
// inject: nilai dari app.provide('appConfig') — tanpa prop drilling
const config = inject<AppConfig>('appConfig')!

const auth = useAuthStore() // state global: siapa yang login
const cart = useCartStore() // state global: isi keranjang

// Cek session sekali saat boot — nav langsung tampil benar
// (guard route tetap jadi penjaga utama)
onMounted(() => auth.fetchUser())

async function logout(): Promise<void> {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <nav>
    <strong>{{ config.appName }}</strong>
    <template v-if="auth.user">
      <router-link to="/">Produk</router-link>
      <!-- Badge langsung reaktif: cart.count berubah → render ulang otomatis -->
      <router-link :to="{ name: 'cart' }">
        Keranjang ({{ cart.count }}) — Rp {{ cart.total.toLocaleString('id-ID') }}
      </router-link>
      <router-link :to="{ name: 'orders' }">Pesanan</router-link>
      <span>{{ auth.user.name }}</span>
      <button @click="logout">Logout</button>
    </template>
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
