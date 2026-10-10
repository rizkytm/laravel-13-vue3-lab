import { createRouter, createWebHistory } from 'vue-router'
import ProductListView from '../views/ProductListView.vue'
import ProductDetailView from '../views/ProductDetailView.vue'
import LoginView from '../views/LoginView.vue'
import { getToken } from '../api'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'products', component: ProductListView, meta: { requiresAuth: true } },
    {
      path: '/products/:slug',
      name: 'product-detail',
      component: ProductDetailView,
      // Dynamic route: slug dibaca via useRoute().params.slug
      meta: { requiresAuth: true },
    },
    { path: '/login', name: 'login', component: LoginView },
  ],
})

// Route guard sederhana: halaman yang butuh token → login dulu.
// Versi lengkap (cookie session Sanctum) menyusul di F3.
router.beforeEach((to) => {
  if (to.meta.requiresAuth && !getToken()) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
})

export default router
