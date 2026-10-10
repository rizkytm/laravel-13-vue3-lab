<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { clearToken, getToken } from './api'

const router = useRouter()
// ref = primitive state reaktif. Variabel di <script setup> yang dibungkus
// ref otomatis "unwrapped" di template — {{ isLoggedIn }} tanpa .value
const isLoggedIn = ref(!!getToken())

function logout() {
  clearToken()
  isLoggedIn.value = false
  router.push({ name: 'login' })
}
</script>

<template>
  <nav>
    <router-link to="/">Produk</router-link>
    <button v-if="isLoggedIn" @click="logout">Logout</button>
    <router-link v-else to="/login">Login</router-link>
  </nav>

  <!-- <router-view> = placeholder konten halaman aktif dari router -->
  <router-view />
</template>

<style>
body {
  font-family: sans-serif;
  max-width: 720px;
  margin: 1.5rem auto;
  padding: 0 1rem;
}
nav { margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center; }
.card { border: 1px solid #ccc; border-radius: 6px; padding: .75rem; margin-bottom: .5rem; cursor: pointer; }
.card:hover { background: #f5f5f5; }
.err { color: red; }
</style>
