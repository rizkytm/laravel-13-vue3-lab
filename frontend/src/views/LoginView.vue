<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const error = ref('')

async function submit(): Promise<void> {
  error.value = ''
  try {
    // Sanctum SPA: csrf-cookie → POST /login (session) → fetch /api/user
    await auth.login(email.value, password.value)
    const redirect = route.query.redirect
    router.push(typeof redirect === 'string' ? redirect : { name: 'products' })
  } catch (e) {
    error.value = (e as { status?: number }).status === 401
      ? 'Email atau password salah.'
      : (e as Error).message
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
