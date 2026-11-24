<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Store
{
    public static function setting(string $key, string $default = ''): string
    {
        return (string) (DB::table('store_settings')->where('key', $key)->value('value') ?? $default);
    }

    public static function balances(int $userId): array
    {
        $balances = array_fill_keys(['topup', 'cancellation', 'refund'], 0);
        foreach (DB::table('wallet_entries')->where('user_id', $userId)
            ->selectRaw('source, SUM(amount) AS balance')->groupBy('source')->get() as $row) {
            $balances[$row->source] = (int) $row->balance;
        }

        return $balances;
    }

    public static function lockUser(int $id): User
    {
        return User::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public static function writable(User $user): void
    {
        abort_if($user->isReadOnly(), 403, 'Akun ini hanya dapat melihat data. Hubungi owner untuk mengaktifkan kembali akses perubahan.');
    }

    public static function reseller(User $user): void
    {
        abort_unless($user->role === 'reseller' && $user->approval === 'approved', 403,
            'Akun reseller harus disetujui sebelum bertransaksi.');
    }

    public static function staff(): void
    {
        abort_unless(in_array(Auth::user()->role, ['owner', 'admin'], true), 403);
    }

    public static function owner(): void
    {
        abort_unless(Auth::user()->role === 'owner', 403);
    }

    public static function entry(int $user, string $source, int $amount, string $key, string $note): void
    {
        DB::table('wallet_entries')->insert([
            'user_id' => $user, 'source' => $source, 'amount' => $amount, 'key' => $key,
            'note' => $note, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function debit(int $user, int $amount, string $key): void
    {
        $balances = self::balances($user);
        self::check(array_sum($balances) >= $amount, 'Saldo belum cukup. Top-up dulu atau pilih DOKU.');
        foreach (['refund', 'cancellation', 'topup'] as $source) {
            $take = min($balances[$source], $amount);
            if ($take > 0) {
                self::entry($user, $source, -$take, $key.':'.$source, 'Pembayaran pesanan');
                $amount -= $take;
            }
        }
    }

    public static function fee(int $amount, string $flow, string $method): int
    {
        if (in_array($method, ['wallet', 'manual', 'QRIS', 'CREDIT_CARD'], true)) {
            return 0;
        }
        $allowed = json_decode(self::setting('fee_channels', '[]'), true);
        if (! in_array($method, $allowed, true)) {
            return 0;
        }

        $value = (int) self::setting($flow.'_fee_value', '0');

        return self::setting($flow.'_fee_type', 'fixed') === 'percent'
            ? intdiv($amount * $value + 50, 100) : $value;
    }

    public static function check(bool $valid, string $message): void
    {
        if (! $valid) {
            throw ValidationException::withMessages(['store' => $message]);
        }
    }

    public static function audit(string $action, string $subject): void
    {
        DB::table('store_audits')->insert([
            'actor_id' => Auth::id(), 'action' => $action, 'subject' => $subject, 'created_at' => now(),
        ]);
    }

    public static function notify(int $user, string $message, ?int $order = null, ?int $case = null): void
    {
        DB::table('store_notifications')->insert([
            'user_id' => $user, 'message' => $message, 'order_id' => $order, 'support_case_id' => $case, 'created_at' => now(),
        ]);
    }

    public static function updates(int $user): string
    {
        return hash('sha256', json_encode([
            DB::table('users')->where('id',$user)->first(['account_status','account_status_reason']),
            DB::table('orders')->where('user_id', $user)->orderByDesc('id')->limit(30)->get(['id', 'status']),
            DB::table('order_items')->whereIn('order_id', DB::table('orders')->where('user_id', $user)->select('id'))
                ->orderByDesc('id')->limit(100)->get(['id', 'status']),
            DB::table('topups')->where('user_id', $user)->orderByDesc('id')->limit(30)->get(['id', 'status']),
            DB::table('withdrawals')->where('user_id', $user)->orderByDesc('id')->limit(30)->get(['id', 'status']),
            DB::table('wallet_entries')->where('user_id', $user)->max('id'),
            DB::table('store_notifications')->where('user_id', $user)->max('id'),
        ], JSON_THROW_ON_ERROR));
    }
}

