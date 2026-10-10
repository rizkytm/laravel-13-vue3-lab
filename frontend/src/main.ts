import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './style.css'
import type { AppConfig } from './types'

const app = createApp(App)

// Pinia SEBELUM router — komponen yang di-mount router boleh pakai store
app.use(createPinia())
app.use(router)

// Provide/inject level aplikasi: config/bound shared TANPA prop drilling.
// Inject di komponen mana pun: inject<AppConfig>('appConfig')
app.provide('appConfig', {
  appName: 'Laravel 13 Lab',
  apiBase: import.meta.env.VITE_API_BASE || '/api/v1',
} satisfies AppConfig)

app.mount('#app')
