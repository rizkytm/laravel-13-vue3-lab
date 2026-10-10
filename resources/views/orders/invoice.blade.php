<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $order->id }}</title>
    <style>
        body { font-family: sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: .5rem; text-align: left; }
    </style>
</head>
<body>
<h1>Invoice Order #{{ $order->id }}</h1>
{{-- Halaman ini tanpa login — dilindungi signature URL (middleware 'signed') --}}
<p>
    Pelanggan: {{ $order->user->name }}<br>
    Tanggal: {{ $order->created_at->isoFormat('D MMMM YYYY') }}<br>
    Status: {{ $order->status->value }}
</p>

<table>
    <tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr>
    @foreach ($order->items as $item)
        <tr>
            <td>{{ $item->product->name }}</td>
            <td>{{ $item->quantity }}</td>
            <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
            <td>Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}</td>
        </tr>
    @endforeach
    <tr>
        <th colspan="3">Total</th>
        <th>Rp {{ number_format($order->total, 0, ',', '.') }}</th>
    </tr>
</table>
</body>
</html>
