<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders — Laravel 13 Lab M2</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: .5rem; text-align: left; vertical-align: top; }
        nav a { margin-right: 1rem; }
    </style>
</head>
<body>
<h1>M2 — Eloquent: Orders (eager loading)</h1>
<nav><a href="/">Checkout (M1)</a><a href="/products">Produk</a></nav>

<table>
    <tr>
        <th>#</th><th>User</th><th>Status (enum)</th><th>Total</th><th>Item (withCount)</th><th>Isi items → product</th>
    </tr>
    {{-- with(['user', 'items.product']) → semua relasi ter-load di 3 query,
         bukan 1+N. Akses $order->user->name tidak memicu query tambahan. --}}
    @foreach ($orders as $order)
        <tr>
            <td>{{ $order->id }}</td>
            <td>{{ $order->user->name }}</td>
            <td>{{ $order->status->value }}</td>
            <td>Rp {{ number_format($order->total, 0, ',', '.') }}</td>
            <td>{{ $order->items_count }}</td>
            <td>
                @foreach ($order->items as $item)
                    {{ $item->quantity }} × {{ $item->product->name }}<br>
                @endforeach
            </td>
        </tr>
    @endforeach
</table>
</body>
</html>
