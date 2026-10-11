import { createI18n } from 'vue-i18n'

/**
 * i18n vue-i18n (composition mode). Default 'id' — teks Indonesia = teks
 * asli UI, sehingga test yang ada tidak berubah. Pilihan bahasa disimpan
 * di localStorage (persist antar reload).
 */
const messages = {
  id: {
    nav: {
      products: 'Produk',
      cart: 'Keranjang',
      orders: 'Pesanan',
      logout: 'Logout',
      login: 'Login',
    },
    common: {
      loading: 'Memuat…',
      unauth: 'Silakan login terlebih dahulu.',
      product: 'Produk',
      price: 'Harga',
      qty: 'Qty',
      subtotal: 'Subtotal',
      total: 'Total',
    },
    list: {
      title: 'Produk',
      sort: 'Urutkan',
      sortNewest: 'Terbaru',
      sortName: 'Nama A–Z',
      sortPriceAsc: 'Harga termurah',
      sortPriceDesc: 'Harga termahal',
      search: 'Cari produk',
      searchPlaceholder: 'Cari produk…',
      empty: 'Tidak ada produk cocok.',
      pageInfo: 'Halaman {page} dari {last} · {total} produk',
      prev: '← Sebelumnya',
      next: 'Berikutnya →',
    },
    card: {
      out: 'Habis',
      stockValue: 'stok {n}',
    },
    detail: {
      back: '← Kembali',
      stock: 'Stok',
      options: 'Opsi',
      color: 'Warna',
      weight: 'Berat',
      addToCart: 'Tambah ke keranjang',
      outOfStock: 'Stok habis',
      inCart: 'Sudah di keranjang ({n})',
      notFound: 'Produk tidak ditemukan.',
    },
    cart: {
      title: 'Keranjang',
      empty: 'Keranjang kosong.',
      action: 'Aksi',
      remove: 'Hapus',
      clear: 'Kosongkan',
      checkout: 'Checkout',
      processing: 'Memproses…',
    },
    orders: {
      title: 'Pesanan Saya',
      reload: 'Muat ulang',
      empty: 'Belum ada pesanan.',
      status: 'Status',
      item: 'Item',
      transaction: 'Transaction',
    },
    orderDetail: {
      title: 'Pesanan #{id}',
      transactionId: 'Transaction ID',
      paidAt: 'Dibayar',
      items: 'Item',
      invoice: 'Lihat invoice ↗',
      notFound: 'Pesanan tidak ditemukan.',
      notOwner: 'Pesanan ini bukan milik Anda.',
    },
    login: {
      email: 'Email',
      password: 'Password',
      submit: 'Masuk',
      showPassword: 'Lihat password',
      hidePassword: 'Sembunyikan password',
      wrong: 'Email atau password salah.',
    },
  },
  en: {
    nav: {
      products: 'Products',
      cart: 'Cart',
      orders: 'Orders',
      logout: 'Logout',
      login: 'Login',
    },
    common: {
      loading: 'Loading…',
      unauth: 'Please log in first.',
      product: 'Product',
      price: 'Price',
      qty: 'Qty',
      subtotal: 'Subtotal',
      total: 'Total',
    },
    list: {
      title: 'Products',
      sort: 'Sort',
      sortNewest: 'Newest',
      sortName: 'Name A–Z',
      sortPriceAsc: 'Price: low to high',
      sortPriceDesc: 'Price: high to low',
      search: 'Search products',
      searchPlaceholder: 'Search products…',
      empty: 'No matching products.',
      pageInfo: 'Page {page} of {last} · {total} products',
      prev: '← Previous',
      next: 'Next →',
    },
    card: {
      out: 'Out',
      stockValue: 'stock {n}',
    },
    detail: {
      back: '← Back',
      stock: 'Stock',
      options: 'Options',
      color: 'Color',
      weight: 'Weight',
      addToCart: 'Add to cart',
      outOfStock: 'Out of stock',
      inCart: 'In cart ({n})',
      notFound: 'Product not found.',
    },
    cart: {
      title: 'Cart',
      empty: 'Cart is empty.',
      action: 'Actions',
      remove: 'Remove',
      clear: 'Clear',
      checkout: 'Checkout',
      processing: 'Processing…',
    },
    orders: {
      title: 'My Orders',
      reload: 'Reload',
      empty: 'No orders yet.',
      status: 'Status',
      item: 'Items',
      transaction: 'Transaction',
    },
    orderDetail: {
      title: 'Order #{id}',
      transactionId: 'Transaction ID',
      paidAt: 'Paid at',
      items: 'Items',
      invoice: 'View invoice ↗',
      notFound: 'Order not found.',
      notOwner: 'This order is not yours.',
    },
    login: {
      email: 'Email',
      password: 'Password',
      submit: 'Sign in',
      showPassword: 'Show password',
      hidePassword: 'Hide password',
      wrong: 'Incorrect email or password.',
    },
  },
} as const

const LOCALE_KEY = 'lab_locale'

export function savedLocale(): string {
  return localStorage.getItem(LOCALE_KEY) ?? 'id'
}

export function saveLocale(locale: string): void {
  localStorage.setItem(LOCALE_KEY, locale)
}

export const i18n = createI18n({
  legacy: false, // composition API mode
  globalInjection: true, // $t tersedia di template
  locale: savedLocale(),
  fallbackLocale: 'id',
  messages,
})
