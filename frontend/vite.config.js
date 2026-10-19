import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig, loadEnv } from 'vite'

// GET halaman auth = halaman SPA (deep-link & reload) — jangan kena proxy ke
// Laravel. POST-nya (/login, /register, dst) tetap diteruskan oleh proxy di bawah.
// Catatan: req.url di-reset ke index.html tapi URL browser TETAP asli
// (termasuk query ?token=..&email=.. untuk /reset-password) — SPA baca dari
// window.location, jadi parameter tautan email tetap sampai ke Vue Router.
function spaAuthRoutes() {
  const spaPaths = ['/login', '/register', '/forgot-password', '/reset-password']
  return {
    name: 'spa-auth-routes',
    configureServer(server) {
      server.middlewares.use((req, _res, next) => {
        if (req.method === 'GET' && spaPaths.some((p) => req.url?.startsWith(p))) {
          req.url = '/index.html'
        }
        next()
      })
    },
  }
}

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const backend = env.VITE_BACKEND_URL || 'http://127.0.0.1:8000'

  return {
    plugins: [vue(), tailwindcss(), spaAuthRoutes()],
    server: {
      proxy: {
        // Dev: rute Laravel diteruskan ke backend dari VITE_BACKEND_URL.
        // changeOrigin:false → Host asli dipertahankan → redirect Laravel
        // (route()) menghasilkan URL origin SPA (:5173), bukan backend —
        // kalau tidak, fetch() browser ngikut redirect lintas origin → Failed to fetch.
        // Cookie localhost gak peduli port — session dipakai silang 5173 <-> backend.
        '/api': { target: backend, changeOrigin: false },
        '/sanctum': { target: backend, changeOrigin: false },
        '/login': { target: backend, changeOrigin: false },
        '/logout': { target: backend, changeOrigin: false },
        // Tahap 1 — auth SPA: POST register & password reset, link verifikasi
        // (/email/verify/.. SIGNED) dibuka browser langsung dari email.
        '/register': { target: backend, changeOrigin: false },
        '/forgot-password': { target: backend, changeOrigin: false },
        '/reset-password': { target: backend, changeOrigin: false },
        // ASVS 6.2.2 — ubah password saat login (POST; GET-nya murni SPA)
        '/change-password': { target: backend, changeOrigin: false },
        '/email': { target: backend, changeOrigin: false },
        // Invoice signed URL dibuka dari SPA (host :5173, ikut di-sign untuk
        // host itu) — harus diteruskan ke backend, bukan jatuh ke SPA fallback.
        // Regex: HANYA /orders/*/invoice — /orders dan /orders/:id tetap SPA.
        '^/orders/[^/]+/invoice': { target: backend, changeOrigin: false },
      },
    },
    test: {
      environment: 'jsdom',
      setupFiles: ['tests/setup.ts'],
      // e2e/ milik Playwright — jangan ikut dijalan vitest
      exclude: ['e2e/**', 'node_modules/**'],
    },
  }
})
