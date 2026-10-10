<?php

use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

// Event discovery → listener → notification (mail + database).

it('OrderPlaced triggers confirmation notification via auto-discovered listener', function () {
    Notification::fake();

    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    event(new OrderPlaced($order)); // tanpa registrasi — discovery by type-hint

    Notification::assertSentTo(
        $user,
        OrderConfirmation::class,
        function (OrderConfirmation $notification, array $channels) use ($order) {
            return $notification->order->is($order)
                && in_array('mail', $channels)
                && in_array('database', $channels);
        }
    );
});

it('writes notification row to database channel (full sync path)', function () {
    // Tanpa fake: event → listener → notification (queue sync di test)
    // → channel database menulis baris, channel mail ditulis ke array driver.
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    event(new OrderPlaced($order));

    expect($user->notifications()->count())->toBe(1)
        ->and($user->notifications()->first()->type)->toBe(OrderConfirmation::class);
});

it('cancels stale pending orders via orders:cleanup command', function () {
    $stale = Order::factory()->create(['status' => OrderStatus::Pending]);
    $fresh = Order::factory()->create(['status' => OrderStatus::Pending]);
    $paid = Order::factory()->create(['status' => OrderStatus::Paid]);

    // Backdate manual — hindari event model saat testing
    Order::query()->whereKey($stale->id)->update(['created_at' => now()->subHours(48)]);

    Artisan::call('orders:cleanup', ['--hours' => 24]);

    expect($stale->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($fresh->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($paid->refresh()->status)->toBe(OrderStatus::Paid);
});
