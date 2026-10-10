import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'

const app = createApp(App)

// Pinia SEBELUM router — komponen yang di-mount router boleh pakai store
app.use(createPinia())
app.use(router)

// Provide/inject level aplikasi: config/bound shared TANPA prop drilling.
// Inject di komponen mana pun: inject('appConfig')
app.provide('appConfig', {
  appName: 'Laravel 13 Lab',
  apiBase: '/api/v1',
})

app.mount('#app')
