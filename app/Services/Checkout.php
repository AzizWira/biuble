<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\StockSlot;
use App\Models\Variant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Checkout
{
    public function create(User $buyer, array $cart, string $method, string $token): Order
    {
        return DB::transaction(function () use ($buyer, $cart, $method, $token) {
            $buyer = Store::lockUser($buyer->id);
            Store::reseller($buyer);
            Store::writable($buyer);
            if ($existing = Order::where('token', $token)->first()) {
                abort_unless($existing->user_id === $buyer->id, 403);
                return $existing;
            }
            Store::check(count($cart) > 0, 'Keranjang masih kosong.');
            $lines = [];
            $total = 0;
            foreach ($cart as $row) {
                $variant = Variant::with('product')->whereKey($row['variant'])->lockForUpdate()->firstOrFail();
                Store::check($variant->active && $variant->product->active, 'Paket sudah tidak tersedia.');
                $designer = $variant->designer && ! empty($row['designer']);
                $price = $variant->unitPrice($designer);
                for ($i = 0; $i < $row['quantity']; $i++) {
                    $slot = null;
                    if ($variant->delivery === 'stock') {
                        $slot = StockSlot::whereHas('account', fn ($q) => $q->where('variant_id', $variant->id))
                            ->where('state', 'available')->orderBy('id')->lockForUpdate()->first();
                        Store::check((bool) $slot, 'Stok '.$variant->name.' belum cukup.');
                        $slot->update(['state' => 'reserved', 'reservation_token' => $token]);
                    }
                    Store::check($variant->delivery !== 'invite' || filter_var($row['email'] ?? '', FILTER_VALIDATE_EMAIL),
                        'Isi email tujuan undangan.');
                    $lines[] = [
                        'variant_id' => $variant->id, 'stock_slot_id' => $slot?->id,
                        'name' => $variant->product->name.' · '.$variant->name,
                        'delivery' => $variant->delivery, 'amount' => $price,
                        'designer' => $designer, 'email' => $variant->delivery === 'invite' ? $row['email'] : null,
                        'duration_days' => $variant->duration_days,
                        'warranty_days' => $variant->warranty_days,
                        'login_proof_required' => (bool) $variant->login_proof_required,
                    ];
                    $total += $price;
                }
            }
            $fee = Store::fee($total, 'checkout', $method);
            $order = Order::create([
                'user_id' => $buyer->id, 'token' => $token, 'reference' => 'B'.Str::upper(Str::random(20)),
                'method' => $method, 'subtotal' => $total, 'fee' => $fee, 'total' => $total + $fee,
                'expires_at' => now()->addMinutes(60), 'phone' => $buyer->phone,
            ]);
            $order->items()->createMany($lines);
            if ($method === 'wallet') {
                Store::debit($buyer->id, $total, 'order:'.$order->id);
                $this->complete($order);
            } else {
                Payment::create([
                    'reference' => $order->reference, 'kind' => 'order', 'target_id' => $order->id,
                    'amount' => $order->total, 'request_id' => (string) Str::uuid(),
                ]);
            }

            return $order;
        }, 3);
    }

    public function complete(Order $order): void
    {
        // Caller holds the buyer and order locks.
        if ($order->paid_at) {
            return;
        }
        $order->update(['status' => 'paid', 'paid_at' => now()]);
        foreach ($order->items()->lockForUpdate()->get() as $item) {
            if ($item->delivery === 'stock') {
                $slot = StockSlot::with('account')->whereKey($item->stock_slot_id)->lockForUpdate()->first();
                if (! $slot || $slot->state !== 'reserved' || $slot->reservation_token !== $order->token) {
                    $item->update(['status' => 'review']);
                    continue;
                }
                $slot->update(['state' => 'used']);
                $this->deliver($item, $slot->account->credentials."\nProfil: ".$slot->label);
            } else {
                $item->update(['status' => 'waiting']);
            }
        }
    }

    public function deliver(OrderItem $item, string $access): void
    {
        $item->update([
            'status' => 'delivered', 'access' => $access, 'delivered_at' => now(),
            'ends_at' => $item->duration_days ? now()->addDays($item->duration_days) : null,
        ]);
        Store::notify($item->order->user_id, 'Akses siap untuk '.$item->name, $item->order_id);
    }

    public function cancel(int $itemId, User $buyer): void
    {
        DB::transaction(function () use ($itemId, $buyer) {
            $buyer = Store::lockUser($buyer->id);
            Store::writable($buyer);
            $item = OrderItem::whereKey($itemId)->firstOrFail();
            $order = Order::whereKey($item->order_id)->lockForUpdate()->firstOrFail();
            abort_unless($order->user_id === $buyer->id, 403);
            $item = OrderItem::whereKey($itemId)->lockForUpdate()->firstOrFail();
            Store::check($order->paid_at && $order->paid_at->lte(now()->subMinutes(30))
                && $item->delivery === 'manual' && $item->status === 'waiting',
                'Item ini belum bisa dibatalkan.');
            if ($order->method !== 'wallet') {
                // A return through the gateway must be confirmed independently.
                $item->update(['status' => 'refund_requested']);
                Store::notify($buyer->id, 'Pengembalian pembayaran DOKU sedang diperiksa.', $order->id);
            } else {
                Store::entry($buyer->id, 'cancellation', $item->amount, 'cancel:'.$item->id, 'Pembatalan '.$item->name);
                $item->update(['status' => 'cancelled']);
            }
            Store::audit('cancel_item', (string) $item->id);
        }, 3);
    }
}



