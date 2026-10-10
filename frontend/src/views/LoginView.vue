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
            class="absolute right-1 top-1/2 -translate-y-1/2 cursor-pointer border-0 bg-transparent p-1.5 text-ink-muted hover:text-accent"
            @click="showPassword = !showPassword"
          >
            <!-- eye: tampil saat password tersembunyi; eye-off saat terlihat -->
            <svg
              v-if="!showPassword"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              class="h-5 w-5"
              aria-hidden="true"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"
              />
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
              />
            </svg>
            <svg
              v-else
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              class="h-5 w-5"
              aria-hidden="true"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243"
              />
            </svg>
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
