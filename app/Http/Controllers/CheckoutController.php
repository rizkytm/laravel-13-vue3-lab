<?php

namespace App\Http\Controllers;

use App\Services\Orders\OrderSummary;
use App\Services\Payment\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    /**
     * Halaman checkout: form + demo ringkasan order (collections).
     */
    public function create(): View
    {
        // Data contoh — di M2 ini datang dari database lewat Eloquent.
        $sampleOrders = [
            ['id' => 1, 'status' => 'paid', 'total' => 120000, 'item_count' => 2],
            ['id' => 2, 'status' => 'pending', 'total' => 75000, 'item_count' => 1],
            ['id' => 3, 'status' => 'paid', 'total' => 200000, 'item_count' => 3],
            ['id' => 4, 'status' => 'cancelled', 'total' => 50000, 'item_count' => 1],
        ];

        return view('checkout', [
            'summary' => app(OrderSummary::class)->make($sampleOrders),
        ]);
    }

    /**
     * Proses checkout.
     *
     * Method injection: container mengisi parameter bertipe PaymentGateway
     * otomatis sesuai binding di AppServiceProvider. Controller TIDAK tahu
     * implementasinya — hanya tahu kontraknya.
     */
    public function store(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $transactionId = $gateway->charge($validated['amount']);

        return redirect()->back()->with('transaction_id', $transactionId);
    }
}
