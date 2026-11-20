<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\StockSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderPayment
{
    public function cancel(User $buyer, Order $target): void
    {
        DB::transaction(function () use ($buyer, $target) {
            $buyer = Store::lockUser($buyer->id);
            Store::reseller($buyer);
            Store::writable($buyer);
            $order = Order::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->user_id === $buyer->id, 403);
            if ($order->status === 'cancelled') return;
            Store::check(! $order->paid_at && $order->status === 'pending', 'Pesanan sudah diproses dan tidak dapat dibatalkan melalui tombol ini.');
            $order->update(['status' => 'cancelled']);
            $order->items()->where('status', 'reserved')->update(['status' => 'cancelled']);
            StockSlot::where('reservation_token', $order->token)->where('state', 'reserved')
                ->update(['state' => 'available', 'reservation_token' => null]);
            Payment::where('kind', 'order')->where('target_id', $order->id)->where('status', 'pending')->update(['status' => 'cancelled']);
            Store::audit('order_cancelled', $order->reference);
        }, 3);
    }

    public function change(User $buyer, Order $target, string $method, int $expectedTotal, int $expectedPayment): void
    {
        DB::transaction(function () use ($buyer, $target, $method, $expectedTotal, $expectedPayment) {
            $buyer = Store::lockUser($buyer->id);
            Store::reseller($buyer);
            Store::writable($buyer);
            $order = Order::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->user_id === $buyer->id, 403);
            Store::check(! $order->paid_at && $order->status === 'pending' && $order->expires_at->isFuture(), 'Pesanan sudah dibayar, dibatalkan, atau melewati batas pembayaran.');
            $previous = $order->payment;
            Store::check(($previous?->id ?? 0) === $expectedPayment, 'Metode sudah berubah. Muat ulang detail pesanan sebelum mencoba lagi.');
            $fee = Store::fee($order->subtotal, 'checkout', $method);
            Store::check($expectedTotal === $order->subtotal + $fee, 'Biaya pembayaran berubah. Periksa total lalu konfirmasi ulang.');
            if ($order->method === $method) return;
            Payment::where('kind', 'order')->where('target_id', $order->id)->where('status', 'pending')->update(['status' => 'superseded']);
            $order->update(['method' => $method, 'fee' => $fee, 'total' => $expectedTotal]);
            if ($method === 'wallet') {
                Store::debit($buyer->id, $order->subtotal, 'order:'.$order->id);
                app(Checkout::class)->complete($order);
            } else {
                Payment::create([
                    'reference' => 'P'.Str::upper(Str::random(20)), 'kind' => 'order', 'target_id' => $order->id,
                    'method' => $method, 'amount' => $order->total, 'request_id' => (string) Str::uuid(),
                ]);
            }
            Store::audit('order_payment_changed', $order->reference);
        }, 3);
    }
}
