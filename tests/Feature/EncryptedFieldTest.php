<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Encrypted cast: plaintext di aplikasi, ciphertext di database.

it('stores phone as ciphertext but reads it back decrypted', function () {
    $user = User::factory()->create(['phone' => '081234567890']);

    $raw = DB::table('users')->where('id', $user->id)->value('phone');

    // Yang tersimpan di DB BUKAN nomor asli
    expect($raw)->not->toBe('081234567890')
        ->and(str_contains($raw, '081234567890'))->toBeFalse();

    // Aplikasi tetap membaca plaintext otomatis
    expect(User::find($user->id)->phone)->toBe('081234567890');
});

it('decrypts phone when shown on order page', function () {
    $user = User::factory()->create(['phone' => '0812999888777']);
    $order = Order::factory()->for($user)->create();

    $this->actingAs($user)
        ->get("/orders/{$order->id}")
        ->assertOk()
        ->assertSee('0812999888777');
});
