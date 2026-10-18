import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import ProductListView from '../views/ProductListView.vue'
import ProductDetailView from '../views/ProductDetailView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import ForgotPasswordView from '../views/ForgotPasswordView.vue'
import ResetPasswordView from '../views/ResetPasswordView.vue'
import VerifyEmailView from '../views/VerifyEmailView.vue'
import CartView from '../views/CartView.vue'
import OrdersView from '../views/OrdersView.vue'
import OrderDetailView from '../views/OrderDetailView.vue'
import { useAuthStore } from '../stores/auth'

const routes: RouteRecordRaw[] = [
  { path: '/', name: 'products', component: ProductListView, meta: { requiresAuth: true } },
  {
    path: '/products/:slug',
    name: 'product-detail',
    component: ProductDetailView,
    // Dynamic route: slug dibaca via useRoute().params.slug
    meta: { requiresAuth: true },
  },
  { path: '/login', name: 'login', component: LoginView },
  { path: '/register', name: 'register', component: RegisterView },
  { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView },
  // Token & email dibaca dari query string (?token=..&email=..) —
  // tautan di email reset menunjuk ke sini
  { path: '/reset-password', name: 'reset-password', component: ResetPasswordView },
  { path: '/verify-email', name: 'verify-email', component: VerifyEmailView, meta: { requiresAuth: true } },
  { path: '/cart', name: 'cart', component: CartView, meta: { requiresAuth: true } },
  { path: '/orders', name: 'orders', component: OrdersView, meta: { requiresAuth: true } },
  {
    path: '/orders/:id',
    name: 'order-detail',
    component: OrderDetailView,
    meta: { requiresAuth: true },
  },
  // Catch-all: route tak dikenal → balik ke daftar (jangan halaman kosong)
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Guard ASYNC: session cookie tidak bisa dicek sinkron dari JS —
// tanya backend (GET /api/user) SEBELUM masuk halaman yang butuh login.
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Halaman auth (login/register/lupa & reset password) hanya untuk TAMU —
  // yang sudah login dibalik ke beranda
  const guestOnly = ['login', 'register', 'forgot-password', 'reset-password']
  if (guestOnly.includes(String(to.name))) {
    if (!auth.user) {
      await auth.fetchUser()
    }
    return auth.user ? { name: 'products' } : true
  }

  if (!to.meta.requiresAuth) {
    return true
  }

  if (!auth.user) {
    await auth.fetchUser()
  }

  if (!auth.user) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  return true
})

export default router
