<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — Laravel 13 Lab M1</title>
    <style>
        body { font-family: sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
        fieldset { margin-bottom: 1.5rem; }
        .flash { background: #e8f5e9; padding: .75rem; border-radius: 4px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: .5rem; text-align: left; }
    </style>
</head>
<body>
<h1>M1 — Container Binding + Collections</h1>

@if (session('transaction_id'))
    <p class="flash">Pembayaran sukses — transaction id: <strong>{{ session('transaction_id') }}</strong></p>
@endif

<form method="POST" action="{{ route('checkout.store') }}">
    @csrf
    <fieldset>
        <legend>Checkout (diproses FakePaymentGateway via container binding)</legend>
        <label>Nominal (IDR):
            <input type="number" name="amount" value="150000" min="1" required>
        </label>
        <button type="submit">Bayar</button>
    </fieldset>
    @error('amount')
        <p style="color:red">{{ $message }}</p>
    @enderror
</form>

<h2>Ringkasan order contoh (Collection transform)</h2>
<table>
    <tr><th>Total order</th><td>{{ $summary['total_orders'] }}</td></tr>
    <tr><th>Revenue (paid saja)</th><td>Rp {{ number_format($summary['total_revenue'], 0, ',', '.') }}</td></tr>
    <tr><th>Per status</th><td>{{ json_encode($summary['by_status']) }}</td></tr>
    <tr><th>Rata-rata item</th><td>{{ $summary['avg_items'] }}</td></tr>
</table>
</body>
</html>
