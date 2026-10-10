import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig, loadEnv } from 'vite'

// GET /login = halaman SPA (deep-link & reload) — jangan kena proxy ke Laravel
// blade. POST /login (form/JSON) tetap diteruskan oleh proxy di bawah.
function spaLoginRoute() {
  return {
    name: 'spa-login-route',
    configureServer(server) {
      server.middlewares.use((req, _res, next) => {
        if (req.method === 'GET' && req.url?.startsWith('/login')) {
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
    plugins: [vue(), tailwindcss(), spaLoginRoute()],
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
      },
    },
    test: {
      environment: 'jsdom',
    },
  }
})
