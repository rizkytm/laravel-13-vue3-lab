// Kontrak data antara frontend ↔ Laravel API (API Resource shape M4).
// Satu tempat definisi tipe → compiler yang menjaga konsistensi field.

export interface Product {
  id: number
  name: string
  slug: string
  price: number
  stock: number
  options: Record<string, unknown> | null
  order_items_count?: number
  image_path?: string | null
}

export interface CartItem {
  product_id: number
  name: string
  price: number
  quantity: number
  /** Snapshot stok saat add — batas qty di cart; server tetap otoritatif saat checkout */
  stock: number
}

export interface User {
  id: number
  name: string
  email: string
  phone?: string | null
}

export interface OrderItem {
  id: number
  product_id: number
  quantity: number
  price: number
  product?: { id: number; name: string }
}

export interface Order {
  id: number
  status: string
  total: number
  transaction_id: string | null
  paid_at: string | null
  items_count?: number
  items?: OrderItem[]
  user?: { id: number; name: string }
}

/** Bentuk response paginator (offset & cursor) dari Laravel Resource */
export interface Paginated<T> {
  data: T[]
  meta?: {
    current_page?: number
    per_page?: number
    total?: number
    next_cursor?: string | null
  }
}

/** Error shape Laravel: {message, errors} — dilempar oleh api.request() */
export interface ApiError extends Error {
  status?: number
  errors?: Record<string, string[]>
}

export interface AppConfig {
  appName: string
  apiBase: string
}
