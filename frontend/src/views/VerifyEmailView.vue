<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { spaResendVerification } from '../api'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const auth = useAuthStore()
const { t } = useI18n()

// ?status=verified → kembali dari link verifikasi di email
const justVerified = route.query.status === 'verified'

const message = ref('')
const error = ref('')

async function resend(): Promise<void> {
  message.value = ''
  error.value = ''
  try {
    const res = await spaResendVerification()
    message.value = res.message
  } catch (e) {
    error.value = (e as Error).message
  }
}
</script>

<template>
  <div class="mx-auto max-w-sm">
    <h1 class="mb-5 text-2xl font-semibold tracking-tight text-ink">
      {{ t('verify.title') }}
    </h1>

    <!-- Kasus 1: baru saja klik link verifikasi di email -->
    <template v-if="justVerified || auth.user?.email_verified_at">
      <p
        class="mb-4 text-ink"
        data-testid="verify-done"
      >
        {{ t('verify.done') }}
      </p>
      <RouterLink
        :to="{ name: 'products' }"
        class="text-sm text-accent hover:underline"
      >
        {{ t('verify.goShopping') }}
      </RouterLink>
    </template>

    <!-- Kasus 2: belum verifikasi — tampilkan tombol kirim ulang -->
    <template v-else>
      <p
        v-if="error"
        class="mb-4 text-danger"
        role="alert"
      >
        {{ error }}
      </p>
      <p
        v-if="message"
        class="mb-4 text-ink"
        data-testid="verify-sent"
      >
        {{ message }}
      </p>
      <p class="mb-4 text-ink">
        {{ t('verify.notice', { email: auth.user?.email ?? '' }) }}
      </p>
      <button
        type="button"
        data-testid="resend-verification"
        class="cursor-pointer rounded-md bg-accent px-4 py-2 font-medium text-white transition-colors duration-150 hover:bg-accent-strong"
        @click="resend"
      >
        {{ t('verify.resend') }}
      </button>
    </template>
  </div>
</template>
