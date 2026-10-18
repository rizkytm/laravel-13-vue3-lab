<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { spaForgotPassword } from '../api'

const { t } = useI18n()

const email = ref('')
const error = ref('')
const sent = ref(false)

async function submit(): Promise<void> {
  error.value = ''
  try {
    await spaForgotPassword(email.value)
    sent.value = true // respons selalu generik — backend tidak membocorkan email terdaftar
  } catch (e) {
    error.value = (e as Error).message
  }
}
</script>

<template>
  <div class="mx-auto max-w-sm">
    <h1 class="mb-5 text-2xl font-semibold tracking-tight text-ink">
      {{ t('forgot.title') }}
    </h1>
    <p
      v-if="error"
      class="mb-4 text-danger"
      role="alert"
    >
      {{ error }}
    </p>

    <template v-if="sent">
      <p
        class="mb-4 text-ink"
        data-testid="forgot-sent"
      >
        {{ t('forgot.sent') }}
      </p>
      <RouterLink
        :to="{ name: 'login' }"
        class="text-sm text-accent hover:underline"
      >
        {{ t('forgot.backToLogin') }}
      </RouterLink>
    </template>

    <form
      v-else
      class="flex flex-col gap-4"
      @submit.prevent="submit"
    >
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
      <button
        type="submit"
        class="cursor-pointer rounded-md bg-accent px-4 py-2 font-medium text-white transition-colors duration-150 hover:bg-accent-strong"
      >
        {{ t('forgot.submit') }}
      </button>
    </form>

    <p class="mt-4 text-sm text-ink-muted">
      {{ t('forgot.haveAccount') }}
      <RouterLink
        :to="{ name: 'login' }"
        class="text-accent hover:underline"
      >
        {{ t('nav.login') }}
      </RouterLink>
    </p>
  </div>
</template>
