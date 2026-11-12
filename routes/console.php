<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockSlot;
use App\Models\User;
use App\Services\Store;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('biuble:staff {email} {role=owner}', function () {
    $email = Str::lower($this->argument('email'));
    $role = $this->argument('role');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! in_array($role, ['owner', 'admin'], true)) {
        $this->error('Gunakan email valid dan role owner atau admin.');
        return 1;
    }
    $user = User::unguarded(fn () => User::firstOrCreate(['email' => $email], [
        'name' => Str::before($email, '@'), 'password' => Hash::make(Str::random(64)),
        'role' => $role, 'approval' => 'approved',
    ]));
    if ($role === 'admin' && $user->role !== 'admin') {
        $this->error('Email sudah terdaftar. Gunakan menu pengelolaan akun untuk mengubah role.');
        return 1;
    }
    if ($role === 'owner' && $user->role !== 'owner') {
        $user->forceFill(['role' => 'owner', 'approval' => 'approved'])->save();
    }
    $this->info('Akun staf disiapkan. Gunakan OTP email atau Google untuk masuk.');
})->purpose('Siapkan owner atau admin dari CLI yang dipercaya.');

Artisan::command('biuble:expire', function () {
    Order::where('status', 'pending')->where('expires_at', '<=', now())->eachById(function ($target) {
        DB::transaction(function () use ($target) {
            Store::lockUser($target->user_id);
            $order = Order::whereKey($target->id)->lockForUpdate()->first();
            if ($order->status !== 'pending' || $order->expires_at->isFuture()) {
                return;
            }
            $order->update(['status' => 'expired']);
            $order->items()->where('status', 'reserved')->update(['status' => 'expired']);
            StockSlot::where('reservation_token', $order->token)->where('state', 'reserved')
                ->update(['state' => 'available', 'reservation_token' => null]);
        }, 3);
    });
    OrderItem::where('status', 'delivered')->where('ends_at', '<=', now())
        ->whereNotNull('stock_slot_id')->eachById(function ($item) {
            StockSlot::whereKey($item->stock_slot_id)->where('state', 'used')
                ->where('reservation_token', $item->order->token)->update(['state' => 'inspection']);
        });
    $this->info('Reservasi kedaluwarsa dan slot yang perlu diperiksa sudah ditandai.');
})->purpose('Lepas reservasi belum dibayar dan tandai slot untuk pemeriksaan.');

Schedule::command('biuble:expire')->everyMinute()->withoutOverlapping();

Artisan::command('biuble:reconcile', function () {
    $doku = app(\App\Services\Doku::class);
    if (! $doku->available()) {
        $this->info('DOKU belum diaktifkan.');
        return;
    }
    \App\Models\Payment::where('status', 'pending')->whereNotNull('url')
        ->where('updated_at', '<', now()->subMinutes(2))->orderBy('updated_at')->limit(30)->get()
        ->each(function ($payment) use ($doku) {
            try {
                $doku->reconcile($payment);
                $payment->touch();
            } catch (\Throwable $exception) {
                report($exception);
                $payment->touch();
            }
        });
})->purpose('Rekonsiliasi transaksi DOKU yang belum menerima notifikasi.');

Schedule::command('biuble:reconcile')->everyFiveMinutes()->withoutOverlapping();

