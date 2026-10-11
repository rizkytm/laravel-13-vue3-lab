<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->name }} — Laravel 13 Lab M2</title>
    <style>
        body { font-family: sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
        .ok { color: green; } .err { color: red; }
        nav a { margin-right: 1rem; }
    </style>
</head>
<body>
<h1>{{ $product->name }}</h1>
<p><em>Demo fitur framework: beli (lockForUpdate) + upload gambar (disk privat).</em></p>

@if (session('success')) <p class="ok">{{ session('success') }}</p> @endif
@if (session('error')) <p class="err">{{ session('error') }}</p> @endif

<p>
    Harga: <strong>{{ $product->formatted_price }}</strong><br>
    Stok: <strong>{{ $product->stock }}</strong><br>
    Opsi: {{ json_encode($product->options) }}<br>
    Masuk di {{ $product->order_items_count }} order item
</p>

<form method="POST" action="{{ route('products.buy', $product) }}">
    @csrf
    <fieldset>
        <legend>Beli (demo lockForUpdate)</legend>
        <label>Jumlah:
            <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock }}">
        </label>
        <button type="submit">Beli</button>
    </fieldset>
    @error('quantity')
        <p class="err">{{ $message }}</p>
    @enderror
</form>

{{-- M6 — gambar dari disk privat, diserve oleh controller --}}
@if ($product->image_path)
    <p><img src="{{ route('products.image', $product) }}" alt="{{ $product->name }}" width="200"></p>
@endif

<form method="POST" action="{{ route('products.image.store', $product) }}" enctype="multipart/form-data">
    @csrf
    <fieldset>
        <legend>{{ __('Upload product image') }}</legend>
        <input type="file" name="image" accept="image/*" required>
        <button type="submit">Upload</button>
    </fieldset>
    @error('image')
        <p class="err">{{ $message }}</p>
    @enderror
</form>
</body>
</html>
