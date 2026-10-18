<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { spaResetPassword } from '../api'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

// Token & email datang dari query string tautan email reset:
// /reset-password?token=xxx&email=a%40b.com
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const email = computed(() => (typeof route.query.email === 'string' ? route.query.email : ''))

const password = ref('')
const error = ref('')

async function submit(): Promise<void> {
  error.value = ''
  try {
    // Backend: broker validasi token → ganti password → auto-login (session)
    await spaResetPassword(token.value, email.value, password.value)
    await auth.fetchUser()
    // Reset = kepemilikan akun terbukti; email mungkin belum verifikasi
    router.push(auth.user?.email_verified_at ? { name: 'products' } : { name: 'verify-email' })
  } catch (e) {
    const apiError = e as { status?: number; message: string }
    // 422 = token invalid/kedaluwarsa atau validasi field
    error.value = apiError.message
  }
}
</script>

<template>
  <div class="mx-auto max-w-sm">
    <h1 class="mb-5 text-2xl font-semibold tracking-tight text-ink">
      {{ t('reset.title') }}
    </h1>
    <p
      v-if="error"
      class="mb-4 text-danger"
      role="alert"
    >
      {{ error }}
    </p>

    <!-- Token hilang = tautan dibuka tanpa parameter (bukan dari email) -->
    <template v-if="!token || !email">
      <p class="mb-4 text-ink">
        {{ t('reset.missingToken') }}
      </p>
      <RouterLink
        :to="{ name: 'forgot-password' }"
        class="text-sm text-accent hover:underline"
      >
        {{ t('reset.requestNew') }}
      </RouterLink>
    </template>

    <form
      v-else
      class="flex flex-col gap-4"
      @submit.prevent="submit"
    >
      <p class="text-sm text-ink-muted">
        {{ t('reset.forEmail', { email }) }}
      </p>
      <label class="block text-sm">
        <span class="mb-1 block text-ink-muted">{{ t('reset.password') }}</span>
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
        {{ t('reset.submit') }}
      </button>
    </form>
  </div>
</template>
