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
      cancel: 'Batalkan order',
      cancelling: 'Membatalkan…',
    },
    login: {
      email: 'Email',
      password: 'Password',
      submit: 'Masuk',
      showPassword: 'Lihat password',
      hidePassword: 'Sembunyikan password',
      wrong: 'Email atau password salah.',
      noAccount: 'Belum punya akun?',
      registerLink: 'Daftar',
      forgotLink: 'Lupa password?',
    },
    register: {
      title: 'Daftar Akun',
      name: 'Nama',
      password: 'Password (min. 8 karakter)',
      submit: 'Daftar',
      haveAccount: 'Sudah punya akun?',
    },
    forgot: {
      title: 'Lupa Password',
      submit: 'Kirim tautan reset',
      sent: 'Jika email terdaftar, tautan reset password sudah dikirim. Periksa kotak masuk Anda.',
      haveAccount: 'Ingat password?',
      backToLogin: 'Kembali ke login',
    },
    reset: {
      title: 'Reset Password',
      password: 'Password baru (min. 8 karakter)',
      submit: 'Ubah password',
      missingToken: 'Tautan reset tidak lengkap. Minta tautan baru.',
      requestNew: 'Minta tautan reset',
      forEmail: 'Reset password untuk {email}',
    },
    verify: {
      title: 'Verifikasi Email',
      notice: 'Kami sudah mengirim tautan verifikasi ke {email}. Klik tautan di email untuk mengaktifkan akun.',
      resend: 'Kirim ulang tautan verifikasi',
      done: 'Email berhasil diverifikasi.',
      goShopping: 'Mulai belanja →',
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
      cancel: 'Cancel order',
      cancelling: 'Cancelling…',
    },
    login: {
      email: 'Email',
      password: 'Password',
      submit: 'Sign in',
      showPassword: 'Show password',
      hidePassword: 'Hide password',
      wrong: 'Incorrect email or password.',
      noAccount: "Don't have an account?",
      registerLink: 'Register',
      forgotLink: 'Forgot password?',
    },
    register: {
      title: 'Create Account',
      name: 'Name',
      password: 'Password (min. 8 characters)',
      submit: 'Register',
      haveAccount: 'Already have an account?',
    },
    forgot: {
      title: 'Forgot Password',
      submit: 'Send reset link',
      sent: 'If the email is registered, a reset link has been sent. Check your inbox.',
      haveAccount: 'Remember your password?',
      backToLogin: 'Back to login',
    },
    reset: {
      title: 'Reset Password',
      password: 'New password (min. 8 characters)',
      submit: 'Change password',
      missingToken: 'The reset link is incomplete. Request a new one.',
      requestNew: 'Request a reset link',
      forEmail: 'Reset password for {email}',
    },
    verify: {
      title: 'Verify Email',
      notice: 'We sent a verification link to {email}. Click it to activate your account.',
      resend: 'Resend verification link',
      done: 'Email verified successfully.',
      goShopping: 'Start shopping →',
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
