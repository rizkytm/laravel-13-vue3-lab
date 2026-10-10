import { createApp } from 'vue'
import App from './App.vue'
import router from './router'

// <script setup> + Composition API — semua state/logic lokal, tanpa options API
createApp(App).use(router).mount('#app')
