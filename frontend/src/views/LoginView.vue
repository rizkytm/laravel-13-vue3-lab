<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { login } from '../api'

const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const error = ref('')

async function submit() {
  error.value = ''
  try {
    // POST /api/token → simpan Bearer token → semua request berikut otomatis
    await login(email.value, password.value)
    router.push(route.query.redirect || { name: 'products' })
  } catch (e) {
    error.value = e.status === 401 ? 'Email atau password salah.' : e.message
  }
}
</script>

<template>
  <h1>Login</h1>
  <p class="err">{{ error }}</p>

  <form @submit.prevent="submit">
    <label>Email <input v-model="email" type="email" required></label>
    <label>Password <input v-model="password" type="password" required></label>
    <button type="submit">Masuk</button>
  </form>

  <p><small>Demo: test@example.com / password</small></p>
</template>
