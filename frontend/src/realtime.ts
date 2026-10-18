import { useCartStore } from './stores/cart'

/**
 * Realtime stok (tahap 7) — subscribe channel broadcast `products`,
 * event `StockUpdated` (payload: { product_id, stock }).
 *
 * Transport: stack lab memakai BROADCAST_DRIVER=log (tanpa Redis/Reverb),
 * jadi TIDAK ada websocket sungguhan di dev. Modul ini mendegradasi dengan
 * anggun — kalau tidak ada broadcaster yang terpasang, semua jadi no-op dan
 * aplikasi tetap jalan (stok segar lewat refresh/optimistic).
 *
 * Titik sambung Reverb/Pusher (produksi): pasang Echo di bootstrap lalu
 * expose-nya sebagai broadcaster bertipe minimal:
 *   { channel(name: string): { listen(event: string, cb: (p: unknown) => void): void } }
 * Cukup set window.__BROADCAST__ sebelum app mount — tanpa mengubah file ini.
 */

export interface Broadcaster {
  channel(name: string): {
    listen(event: string, callback: (payload: unknown) => void): void
  }
}

export interface StockUpdate {
  product_id: number
  stock: number
}

function resolveBroadcaster(): Broadcaster | null {
  const injected = (globalThis as Record<string, unknown>).__BROADCAST__
  return (injected as Broadcaster | undefined) ?? null
}

/**
 * Subscribe update stok. Return fungsi unsubscribe (selalu aman dipanggil).
 * Handler dipanggil untuk SETIAP event StockUpdated — biarkan store yang
 * memutuskan cara menerapkannya (clamp qty, tampilkan banner, dsb).
 */
export function subscribeStockUpdates(
  handler: (update: StockUpdate) => void,
): () => void {
  const broadcaster = resolveBroadcaster()

  // Graceful no-op: tanpa broadcaster (log driver / dev), tak ada yang dibuka
  if (!broadcaster) {
    return () => {}
  }

  const channel = broadcaster.channel('products')
  channel.listen('StockUpdated', (payload: unknown) => {
    const data = payload as Partial<StockUpdate> | null
    if (data && typeof data.product_id === 'number' && typeof data.stock === 'number') {
      handler({ product_id: data.product_id, stock: data.stock })
    }
  })

  return () => {}
}

/**
 * Contoh pemakaian: saat stok berubah, sinkronkan keranjang lokal
 * (clamp quantity ke stok baru) tanpa refresh halaman.
 */
export function connectCartStockSync(): () => void {
  return subscribeStockUpdates((update) => {
    useCartStore().applyStockUpdate(update.product_id, update.stock)
  })
}
