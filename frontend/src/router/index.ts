import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import ProductListView from '../views/ProductListView.vue'
import ProductDetailView from '../views/ProductDetailView.vue'
import LoginView from '../views/LoginView.vue'
import CartView from '../views/CartView.vue'
import OrdersView from '../views/OrdersView.vue'
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
  { path: '/cart', name: 'cart', component: CartView, meta: { requiresAuth: true } },
  { path: '/orders', name: 'orders', component: OrdersView, meta: { requiresAuth: true } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Guard ASYNC: session cookie tidak bisa dicek sinkron dari JS —
// tanya backend (GET /api/user) SEBELUM masuk halaman yang butuh login.
router.beforeEach(async (to) => {
  if (!to.meta.requiresAuth) {
    return true
  }

  const auth = useAuthStore()
  if (!auth.user) {
    await auth.fetchUser()
  }

  if (!auth.user) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  return true
})

export default router
