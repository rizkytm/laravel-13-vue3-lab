<?php

use App\Models\Order;
use App\Models\User;

// OrderPolicy::view — lapisan otorisasi objek. Pemakaian HTTP-nya ada di
// ApiOrderTest (show own order / 403); file ini menguji policy-nya langsung.

it('lets the owner view own order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    expect($user->can('view', $order))->toBeTrue();
});

it('forbids other users from viewing the order', function () {
    $order = Order::factory()->create(); // milik user lain
    $intruder = User::factory()->create();

    expect($intruder->can('view', $order))->toBeFalse();
});
