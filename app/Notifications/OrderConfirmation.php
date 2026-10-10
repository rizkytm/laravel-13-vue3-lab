<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Multi-channel: mail (log driver di dev) + database (tabel notifications).
 * ShouldQueue → kirimnya lewat queue: response request gak nunggu email.
 */
class OrderConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    /**
     * Daftar channel. Tambah channel baru (slack, sms) = tambah method
     * toSlack() dsb., class ini saja yang berubah — caller gak diubah.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order #{$this->order->id} diterima")
            ->greeting("Halo {$notifiable->name}!")
            ->line("Total: Rp ".number_format($this->order->total, 0, ',', '.'))
            ->line('Status: menunggu pembayaran')
            ->action('Lihat order', url("/orders/{$this->order->id}"))
            ->line('Email ini dikirim otomatis oleh Laravel 13 Lab.');
    }

    /**
     * Payload untuk channel database → ada di kolom notifications.data (JSON).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'total' => $this->order->total,
            'message' => "Order #{$this->order->id} berhasil dibuat.",
        ];
    }
}
