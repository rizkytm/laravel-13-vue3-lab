import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { useCartStore } from '../src/stores/cart'
import { connectCartStockSync, subscribeStockUpdates } from '../src/realtime'

const kopi = { product_id: 1, name: 'Kopi', price: 25000, quantity: 5, stock: 10 }

/** Pasang broadcaster tiruan yang merekam callback listen('StockUpdated'). */
function fakeBroadcast() {
  const sink = { captured: null }
  globalThis.__BROADCAST__ = {
    channel: (name) => {
      sink.channelName = name
      return {
        listen: (event, cb) => {
          sink.eventName = event
          sink.captured = cb
        },
      }
    },
  }
  return sink
}

beforeEach(() => {
  setActivePinia(createPinia())
  delete globalThis.__BROADCAST__
})

afterEach(() => {
  delete globalThis.__BROADCAST__
})

describe('realtime stock subscription', () => {
  it('is a safe no-op when no broadcaster is configured (log driver)', () => {
    const seen = []

    // Tanpa __BROADCAST__ → tak ada yang subscribe, unsubscribe tetap aman
    const unsub = subscribeStockUpdates((u) => seen.push(u))

    expect(() => unsub()).not.toThrow()
    expect(seen).toHaveLength(0)
  })

  it('listens to StockUpdated on the products channel when a broadcaster exists', () => {
    const sink = fakeBroadcast()

    const seen = []
    subscribeStockUpdates((u) => seen.push(u))

    expect(sink.channelName).toBe('products')
    expect(sink.eventName).toBe('StockUpdated')

    sink.captured({ product_id: 7, stock: 3 })

    expect(seen).toEqual([{ product_id: 7, stock: 3 }])
  })

  it('ignores malformed payloads (defense against bad data)', () => {
    const sink = fakeBroadcast()

    const seen = []
    subscribeStockUpdates((u) => seen.push(u))

    sink.captured(null)
    sink.captured({ product_id: 'not-a-number' })
    sink.captured({})

    expect(seen).toHaveLength(0)
  })
})

describe('connectCartStockSync', () => {
  it('clamps cart quantity to the broadcast stock', () => {
    const sink = fakeBroadcast()

    const cart = useCartStore()
    cart.items = [kopi] // qty 5, stock 10

    connectCartStockSync()
    sink.captured({ product_id: 1, stock: 2 }) // stok turun ke 2

    expect(cart.items[0].stock).toBe(2)
    expect(cart.items[0].quantity).toBe(2) // qty 5 di-clamp ke 2
  })

  it('keeps quantity but updates stock when new stock is higher', () => {
    const sink = fakeBroadcast()

    const cart = useCartStore()
    cart.items = [{ ...kopi, quantity: 3, stock: 3 }]

    connectCartStockSync()
    sink.captured({ product_id: 1, stock: 50 }) // restock besar

    expect(cart.items[0].stock).toBe(50)
    expect(cart.items[0].quantity).toBe(3) // qty tidak ikut naik
  })

  it('leaves other cart items untouched', () => {
    const sink = fakeBroadcast()

    const cart = useCartStore()
    const teh = { product_id: 2, name: 'Teh', price: 15000, quantity: 1, stock: 4 }
    cart.items = [kopi, teh]

    connectCartStockSync()
    sink.captured({ product_id: 1, stock: 1 })

    expect(cart.items[0].quantity).toBe(1)
    expect(cart.items[1]).toEqual(teh) // teh tidak tersentuh
  })
})
