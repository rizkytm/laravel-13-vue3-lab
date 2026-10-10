<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Otorisasi per-resource. Laravel auto-discover: App\Models\Order
 * → App\Policies\OrderPolicy (konvensi nama — tanpa registrasi manual).
 */
class OrderPolicy
{
    /**
     * Boleh lihat order ini? Hanya pemiliknya.
     * Dipakai: Gate::authorize('view', $order) → 403 kalau false.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }

    /**
     * Update — ditulis eksplisit meski belum ada endpoint-nya,
     * biar aturan "siapa boleh ubah" terdokumentasi di satu tempat.
     * Route update nanti otomatis butuh gate ini.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->id === $order->user_id && $order->status === \App\Enums\OrderStatus::Pending;
    }
}
