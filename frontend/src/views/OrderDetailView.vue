<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api } from '../api'
import type { Order } from '../types'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    // OrderResource membungkus payload di { data: ... } — harus di-unwrap
    const res = await api.raw<{ data: Order }>(`/orders/${route.params.id}`)
    order.value = res.data
  } catch (e) {
    const status = (e as { status?: number }).status
    error.value = status === 404
      ? t('orderDetail.notFound')
      : status === 403
        ? t('orderDetail.notOwner')
        : status === 401
          ? t('common.unauth')
          : (e as Error).message
  } finally {
    loading.value = false
  }
})

function back(): void {
  router.push({ name: 'orders' })
}

// Tahap 8 — customer batalkan order pending. Backend me-restock stok
// (jejak movement) dan menolak (403) bila bukan pending / bukan pemilik.
const cancelling = ref(false)

async function cancelOrder(): Promise<void> {
  if (!order.value) return
  cancelling.value = true
  error.value = ''
  try {
    const res = await api.raw<{ data: Order }>(`/orders/${order.value.id}/cancel`, {
      method: 'POST',
    })
    order.value = res.data // status jadi 'cancelled'
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    cancelling.value = false
  }
}

function statusClass(status: string): string {
  if (status === 'paid') return 'text-success'
  if (status === 'pending') return 'text-warn'
  return 'text-danger'
}

function formatDateTime(iso: string | null): string {
  return iso ? new Date(iso).toLocaleString('id-ID') : '—'
}
</script>

<template>
  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">
      {{ t('orderDetail.title', { id: $route.params.id }) }}
    </h1>
    <button
      data-testid="back-to-orders"
      class="cursor-pointer rounded-md border border-line bg-raised px-3 py-1.5 text-sm text-ink hover:border-ink-muted"
      @click="back"
    >
      {{ t('detail.back') }}
    </button>
  </div>

  <p
    v-if="error"
    data-testid="order-error"
    class="text-danger"
  >
    {{ error }}
  </p>
  <p
    v-else-if="loading"
    class="text-ink-muted"
  >
    {{ t('common.loading') }}
  </p>

  <template v-else-if="order">
    <dl class="mb-6 grid grid-cols-1 gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
      <div class="flex justify-between border-b border-line py-1.5">
        <dt class="text-ink-muted">
          {{ t('orders.status') }}
        </dt>
        <dd
          data-testid="order-status"
          class="font-medium"
          :class="statusClass(order.status)"
        >
          {{ order.status }}
        </dd>
      </div>
      <div class="flex justify-between border-b border-line py-1.5">
        <dt class="text-ink-muted">
          {{ t('common.total') }}
        </dt>
        <dd class="tabular-nums text-ink">
          Rp {{ order.total.toLocaleString('id-ID') }}
        </dd>
      </div>
      <div class="flex justify-between border-b border-line py-1.5">
        <dt class="text-ink-muted">
          {{ t('orderDetail.transactionId') }}
        </dt>
        <dd class="font-mono text-xs text-ink">
          {{ order.transaction_id || '—' }}
        </dd>
      </div>
      <div class="flex justify-between border-b border-line py-1.5">
        <dt class="text-ink-muted">
          {{ t('orderDetail.paidAt') }}
        </dt>
        <dd class="tabular-nums text-ink">
          {{ formatDateTime(order.paid_at) }}
        </dd>
      </div>
    </dl>

    <h2 class="mb-2 text-lg font-semibold text-ink">
      {{ t('orderDetail.items') }}
    </h2>
    <table
      data-testid="order-items"
      class="mb-6 w-full border-collapse text-sm"
    >
      <thead>
        <tr class="border-b border-ink-muted text-left text-ink-muted">
          <th class="py-2 pr-3 font-medium">
            {{ t('common.product') }}
          </th>
          <th class="py-2 pr-3 font-medium">
            {{ t('common.price') }}
          </th>
          <th class="py-2 pr-3 font-medium">
            {{ t('common.qty') }}
          </th>
          <th class="py-2 font-medium">
            {{ t('common.subtotal') }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="item in order.items ?? []"
          :key="item.id"
          class="border-b border-line"
        >
          <td class="py-2.5 pr-3 text-ink">
            {{ item.product?.name ?? `#${item.product_id}` }}
          </td>
          <td class="py-2.5 pr-3 tabular-nums text-ink">
            Rp {{ item.price.toLocaleString('id-ID') }}
          </td>
          <td class="py-2.5 pr-3 tabular-nums text-ink-muted">
            {{ item.quantity }}
          </td>
          <td class="py-2.5 tabular-nums text-ink">
            Rp {{ (item.price * item.quantity).toLocaleString('id-ID') }}
          </td>
        </tr>
      </tbody>
    </table>

    <a
      v-if="order.invoice_url"
      data-testid="invoice-link"
      :href="order.invoice_url"
      target="_blank"
      rel="noopener"
      class="inline-block rounded-md border border-line bg-raised px-3 py-1.5 text-sm text-accent no-underline hover:border-accent"
    >
      {{ t('orderDetail.invoice') }}
    </a>

    <button
      v-if="order.status === 'pending'"
      data-testid="cancel-order"
      :disabled="cancelling"
      class="ml-3 inline-block cursor-pointer rounded-md border border-danger px-3 py-1.5 text-sm text-danger hover:bg-danger hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
      @click="cancelOrder"
    >
      {{ cancelling ? t('orderDetail.cancelling') : t('orderDetail.cancel') }}
    </button>
  </template>
</template>
