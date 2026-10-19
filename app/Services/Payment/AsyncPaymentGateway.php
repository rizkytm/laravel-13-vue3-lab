<?php

namespace App\Services\Payment;

/**
 * Marker interface: gateway PAYMENTNYA ASYNC (redirect/sesi) — pembayaran
 * TIDAK dikonfirmasi oleh charge(), melainkan oleh WEBHOOK provider
 * (contoh: Midtrans Snap → PaymentWebhookController).
 *
 * ProcessPayment mengecek `instanceof AsyncPaymentGateway`: bila ya, job
 * TIDAK menandai order paid (itu kewajiban webhook yang signature-nya
 * diverifikasi) — mencegah paid prematur.
 */
interface AsyncPaymentGateway extends PaymentGateway {}
