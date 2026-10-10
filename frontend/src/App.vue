<script setup lang="ts">
import { inject, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { useCartStore } from './stores/cart'
import type { AppConfig } from './types'

const router = useRouter()
// inject: nilai dari app.provide('appConfig') — tanpa prop drilling
const config = inject<AppConfig>('appConfig')!

const auth = useAuthStore() // state global: siapa yang login
const cart = useCartStore() // state global: isi keranjang

// Cek session sekali saat boot — nav langsung tampil benar
// (guard route tetap jadi penjaga utama)
onMounted(() => auth.fetchUser())

async function logout(): Promise<void> {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen">
    <header class="sticky top-0 z-10 border-b border-line bg-raised">
      <nav class="mx-auto flex max-w-3xl flex-wrap items-center gap-x-5 gap-y-1 px-4 py-3 text-sm">
        <router-link
          to="/"
          class="font-semibold text-ink no-underline"
        >
          {{ config.appName }}
        </router-link>
        <template v-if="auth.user">
          <router-link
            to="/"
            class="text-ink-muted no-underline hover:text-ink"
          >
            Produk
          </router-link>
          <router-link
            :to="{ name: 'cart' }"
            class="text-ink-muted no-underline hover:text-ink"
          >
            Keranjang
            <span class="font-mono tabular-nums text-ink">({{ cart.count }})</span>
            <span class="tabular-nums">Rp {{ cart.total.toLocaleString('id-ID') }}</span>
          </router-link>
          <router-link
            :to="{ name: 'orders' }"
            class="text-ink-muted no-underline hover:text-ink"
          >
            Pesanan
          </router-link>
          <span class="ml-auto text-ink-muted">{{ auth.user.name }}</span>
          <button
            class="cursor-pointer rounded border border-line bg-raised px-2.5 py-1 text-ink hover:border-ink-muted"
            @click="logout"
          >
            Logout
          </button>
        </template>
        <router-link
          v-else
          to="/login"
          class="ml-auto text-accent no-underline hover:text-accent-strong"
        >
          Login
        </router-link>
      </nav>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-6">
      <router-view />
    </main>
  </div>
</template>
