<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
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
  <div class="mx-auto max-w-sm">
    <h1 class="mb-5 text-2xl font-semibold tracking-tight text-ink">
      Login
    </h1>
    <p
      v-if="error"
      class="mb-4 text-danger"
      role="alert"
    >
      {{ error }}
    </p>

    <!-- Label selalu terlihat — placeholder bukan label -->
    <form
      class="flex flex-col gap-4"
      @submit.prevent="submit"
    >
      <label class="block text-sm">
        <span class="mb-1 block text-ink-muted">Email</span>
        <input
          v-model="email"
          type="email"
          required
          autocomplete="email"
          class="w-full rounded-md border border-line bg-raised px-3 py-2 text-base text-ink focus:border-accent"
        >
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-ink-muted">Password</span>
        <span class="relative block">
          <input
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            required
            autocomplete="current-password"
            class="w-full rounded-md border border-line bg-raised px-3 py-2 pr-16 text-base text-ink focus:border-accent"
          >
          <button
            type="button"
            data-testid="toggle-password"
            :aria-label="showPassword ? 'Sembunyikan password' : 'Lihat password'"
            class="absolute right-1 top-1/2 -translate-y-1/2 cursor-pointer border-0 bg-transparent px-2 py-1 text-xs text-ink-muted hover:text-accent"
            @click="showPassword = !showPassword"
          >
            {{ showPassword ? 'Sembunyikan' : 'Lihat' }}
          </button>
        </span>
      </label>
      <button
        type="submit"
        class="cursor-pointer rounded-md bg-accent px-4 py-2 font-medium text-white transition-colors duration-150 hover:bg-accent-strong"
      >
        Masuk
      </button>
    </form>

    <p class="mt-4 text-sm text-ink-muted">
      Demo: <span class="font-mono">test@example.com</span> / <span class="font-mono">password</span>
    </p>
  </div>
</template>
