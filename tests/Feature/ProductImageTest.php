<?php

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// File storage: disk privat + upload tervalidasi + diserve controller.

it('uploads product image to private disk and serves it', function () {
    Storage::fake('local');
    $product = Product::factory()->create();

    $this->post("/products/{$product->slug}/image", [
        'image' => UploadedFile::fake()->image('kopi.jpg', 100, 100),
    ])->assertRedirect()->assertSessionHas('success');

    $product->refresh();
    expect($product->image_path)->not->toBeNull()
        ->and(str_starts_with($product->image_path, 'products/'))->toBeTrue();

    Storage::disk('local')->assertExists($product->image_path);

    $this->get("/products/{$product->slug}/image")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('rejects non-image upload', function () {
    Storage::fake('local');
    $product = Product::factory()->create();

    $this->from("/products/{$product->slug}")
        ->post("/products/{$product->slug}/image", [
            'image' => UploadedFile::fake()->create('dokumen.txt', 100, 'text/plain'),
        ])
        ->assertSessionHasErrors('image');

    expect($product->refresh()->image_path)->toBeNull();
});

it('returns 404 when product has no image', function () {
    $product = Product::factory()->create();

    $this->get("/products/{$product->slug}/image")->assertNotFound();
});
