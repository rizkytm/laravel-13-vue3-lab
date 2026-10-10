<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Notifications\OrderConfirmation;

/**
 * Listener untuk OrderPlaced — auto-discovery, tanpa registrasi.
 * Decoupling: CheckoutController cukup fire event, gak tahu ada
 * notifikasi/email/broadcast yang mengikuti (SRP — lihat Observer
 * pattern di roadmap poin 1).
 */
class SendOrderConfirmation
{
    public function handle(OrderPlaced $event): void
    {
        // Notification-nya sendiri ShouldQueue → call ini hanya MENYERAHKAN
        // ke queue, bukan kirim email inline di request (respons tetap cepat)
        $event->order->user->notify(new OrderConfirmation($event->order));
    }
}
