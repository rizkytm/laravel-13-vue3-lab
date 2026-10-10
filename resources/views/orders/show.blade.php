<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #{{ $order->id }} — Laravel 13 Lab M3</title>
    <style>
        body { font-family: sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: .5rem; text-align: left; }
        nav a { margin-right: 1rem; }
    </style>
</head>
<body>
<h1>Order #{{ $order->id }}</h1>
<nav><a href="/orders">← Semua order saya</a><a href="/products">Produk</a></nav>

<p>
    Pemilik: <strong>{{ $order->user->name }}</strong> ({{ $order->user->email }})<br>
    Status: <strong>{{ $order->status->value }}</strong><br>
    Total: Rp {{ number_format($order->total, 0, ',', '.') }}<br>
    {{-- Locale-aware: ?lang=id → "10 Oktober 2026" (Carbon translation) --}}
    {{ __('Created at') }}: {{ $order->created_at->isoFormat('D MMMM YYYY') }}
</p>

{{-- dilindungi OrderPolicy::view — halaman ini 403 untuk user lain --}}
<table>
    <tr><th>Produk</th><th>Qty</th><th>Harga satuan</th><th>Subtotal</th></tr>
    @foreach ($order->items as $item)
        <tr>
            <td>{{ $item->product->name }}</td>
            <td>{{ $item->quantity }}</td>
            <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
            <td>Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
