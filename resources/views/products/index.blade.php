<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produk — Laravel 13 Lab M2</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: .5rem; text-align: left; }
        .ok { color: green; } .err { color: red; }
        nav a { margin-right: 1rem; }
    </style>
</head>
<body>
<h1>M2 — Eloquent: Produk</h1>
<nav>
    <a href="/">Checkout (M1)</a><a href="/orders">Orders</a>
    @auth
        <form method="POST" action="{{ route('logout') }}" style="display:inline">
            @csrf
            <button type="submit">Logout ({{ auth()->user()->name }})</button>
        </form>
    @else
        <a href="{{ route('login') }}">Login</a>
    @endauth
</nav>

@if (session('success')) <p class="ok">{{ session('success') }}</p> @endif
@if (session('error')) <p class="err">{{ session('error') }}</p> @endif

<table>
    <tr>
        <th>Nama</th><th>Harga</th><th>Stok</th><th>Opsi (JSON)</th><th>Terjual (withCount)</th>
    </tr>
    @foreach ($products as $product)
        <tr>
            <td><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></td>
            {{-- Accessor: kolom turunan, bukan data DB --}}
            <td>{{ $product->formatted_price }}</td>
            <td>{{ $product->stock }}</td>
            <td>{{ json_encode($product->options) }}</td>
            <td>{{ $product->order_items_count }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
