<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { spaChangePassword } from '../api'
import type { ApiError } from '../types'

const { t } = useI18n()

const currentPassword = ref('')
const password = ref('')
const confirm = ref('')
const error = ref('')
const success = ref('')

async function submit(): Promise<void> {
  error.value = ''
  success.value = ''
  try {
    await spaChangePassword(currentPassword.value, password.value)
    // Pesan dari key lokal (konsisten i18n — bukan teks server)
    success.value = t('changePassword.success')
    // Bersihkan field — password tak boleh tertinggal di DOM
    currentPassword.value = ''
    password.value = ''
    confirm.value = ''
  } catch (e) {
    const apiError = e as ApiError
    // 422 = validasi (password lama salah, policy baru) — field pertama dulu
    const first = apiError.errors && Object.values(apiError.errors)[0]?.[0]
    error.value = first ?? apiError.message
  }
}
</script>

<template>
  <div class="mx-auto max-w-sm">
    <h1 class="mb-5 text-2xl font-semibold tracking-tight text-ink">
      {{ t('changePassword.title') }}
    </h1>
    <p
      v-if="error"
      data-testid="change-error"
      class="mb-4 text-danger"
      role="alert"
    >
      {{ error }}
    </p>
    <p
      v-if="success"
      data-testid="change-success"
      class="mb-4 text-success"
    >
      {{ success }}
    </p>

    <form
      class="flex flex-col gap-4"
      @submit.prevent="submit"
    >
      <label class="block text-sm">
        <span class="mb-1 block text-ink-muted">{{ t('changePassword.current') }}</span>
        <input
          v-model="currentPassword"
          type="password"
          required
          autocomplete="current-password"
          class="w-full rounded-md border border-line bg-raised px-3 py-2 text-base text-ink focus:border-accent"
        >
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-ink-muted">{{ t('changePassword.new') }}</span>
        <input
          v-model="password"
          type="password"
          required
          minlength="8"
          autocomplete="new-password"
          class="w-full rounded-md border border-line bg-raised px-3 py-2 text-base text-ink focus:border-accent"
        >
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-ink-muted">{{ t('changePassword.confirm') }}</span>
        <input
          v-model="confirm"
          type="password"
          required
          minlength="8"
          autocomplete="new-password"
          class="w-full rounded-md border border-line bg-raised px-3 py-2 text-base text-ink focus:border-accent"
        >
      </label>
      <button
        type="submit"
        data-testid="change-submit"
        class="cursor-pointer rounded-md bg-accent px-4 py-2 font-medium text-white transition-colors duration-150 hover:bg-accent-strong"
      >
        {{ t('changePassword.submit') }}
      </button>
    </form>
  </div>
</template>
