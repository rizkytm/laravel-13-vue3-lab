<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import type { ApiError } from '../types'

const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

const name = ref('')
const email = ref('')
const password = ref('')
const error = ref('')

async function submit(): Promise<void> {
  error.value = ''
  try {
    // Register = langsung login di backend (session) + kirim email verifikasi
    await auth.register(name.value, email.value, password.value)
    // User baru pasti belum verifikasi → ke halaman notice verifikasi
    router.push({ name: 'verify-email' })
  } catch (e) {
    const apiError = e as ApiError
    // 422 = validasi (email duplikat dst) — tampilkan pesan pertama dari field
    const first = apiError.errors && Object.values(apiError.errors)[0]?.[0]
    error.value = first ?? apiError.message
  }
}
</script>

<template>
  <div class="mx-auto max-w-sm">
    <h1 class="mb-5 text-2xl font-semibold tracking-tight text-ink">
      {{ t('register.title') }}
    </h1>
    <p
      v-if="error"
      class="mb-4 text-danger"
      role="alert"
    >
      {{ error }}
    </p>

    <form
      class="flex flex-col gap-4"
      @submit.prevent="submit"
    >
      <label class="block text-sm">
        <span class="mb-1 block text-ink-muted">{{ t('register.name') }}</span>
        <input
          v-model="name"
          type="text"
          required
          autocomplete="name"
          class="w-full rounded-md border border-line bg-raised px-3 py-2 text-base text-ink focus:border-accent"
        >
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-ink-muted">{{ t('login.email') }}</span>
        <input
          v-model="email"
          type="email"
          required
          autocomplete="email"
          class="w-full rounded-md border border-line bg-raised px-3 py-2 text-base text-ink focus:border-accent"
        >
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-ink-muted">{{ t('register.password') }}</span>
        <input
          v-model="password"
          type="password"
          required
          minlength="8"
          autocomplete="new-password"
          class="w-full rounded-md border border-line bg-raised px-3 py-2 text-base text-ink focus:border-accent"
        >
      </label>
      <button
        type="submit"
        class="cursor-pointer rounded-md bg-accent px-4 py-2 font-medium text-white transition-colors duration-150 hover:bg-accent-strong"
      >
        {{ t('register.submit') }}
      </button>
    </form>

    <p class="mt-4 text-sm text-ink-muted">
      {{ t('register.haveAccount') }}
      <RouterLink
        :to="{ name: 'login' }"
        class="text-accent hover:underline"
      >
        {{ t('nav.login') }}
      </RouterLink>
    </p>
  </div>
</template>
