import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    proxy: {
      // Dev: rute Laravel diteruskan ke php artisan serve (:8000).
      // Cookie localhost gak peduli port — session laravel_session/XSRF
      // yang di-set lewat proxy dipakai silang 5173 <-> 8000.
      // Production bebas: build statis diserve Laravel/CDN, CORS disesuaikan.
      '/api': 'http://127.0.0.1:8000',
      '/sanctum': 'http://127.0.0.1:8000',
      '/login': 'http://127.0.0.1:8000',
      '/logout': 'http://127.0.0.1:8000',
    },
  },
  test: {
    environment: 'jsdom',
  },
})
