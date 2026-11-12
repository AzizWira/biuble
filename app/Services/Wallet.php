<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Topup;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Wallet
{
    public function topup(User $user, array $data, string $token, ?string $proof): Topup
    {
        return DB::transaction(function () use ($user, $data, $token, $proof) {
            $user = Store::lockUser($user->id);
            Store::reseller($user);
            Store::writable($user);
            if ($existing = Topup::where('token', $token)->first()) {
                abort_unless($existing->user_id === $user->id, 403);
                return $existing;
            }
            $fee = Store::fee($data['amount'], 'topup', $data['method']);
            if (isset($data['expected_total'])) {
                Store::check($data['amount'] + $fee === (int) $data['expected_total'], 'Biaya berubah. Periksa rincian lagi.');
            }
            $topup = Topup::create([
                'user_id' => $user->id, 'token' => $token,
                'reference' => 'T'.Str::upper(Str::random(20)), 'method' => $data['method'],
                'amount' => $data['amount'], 'fee' => $fee, 'total' => $data['amount'] + $fee, 'proof' => $proof,
            ]);
            if ($data['method'] !== 'manual') {
                Payment::create([
                    'reference' => $topup->reference, 'kind' => 'topup', 'target_id' => $topup->id,
                    'amount' => $topup->total, 'request_id' => (string) Str::uuid(),
                ]);
            }

            return $topup;
        }, 3);
    }

    public function approveTopup(int $id, string $decision): void
    {
        DB::transaction(function () use ($id, $decision) {
            $target = Topup::findOrFail($id);
            Store::lockUser($target->user_id);
            $topup = Topup::whereKey($id)->lockForUpdate()->firstOrFail();
            Store::check($topup->method === 'manual' && $topup->status === 'pending', 'Top-up sudah diproses.');
            if ($decision === 'approve') {
                Store::entry($topup->user_id, 'topup', $topup->amount, 'topup:'.$id, 'Top-up transfer manual');
            }
            $topup->update(['status' => $decision === 'approve' ? 'paid' : 'rejected']);
            Store::audit('topup_'.$decision, (string) $id);
        }, 3);
    }

    public function withdraw(User $user, array $allocation, string $destination, string $token, int $expectedFee): Withdrawal
    {
        return DB::transaction(function () use ($user, $allocation, $destination, $token, $expectedFee) {
            $user = Store::lockUser($user->id);
            Store::reseller($user);
            Store::writable($user);
            if ($existing = Withdrawal::where('token', $token)->first()) {
                abort_unless($existing->user_id === $user->id, 403);
                return $existing;
            }
            $balances = Store::balances($user->id);
            $amount = array_sum($allocation);
            Store::check($amount > 0, 'Isi nominal penarikan.');
            foreach ($allocation as $source => $value) {
                Store::check($value <= $balances[$source], 'Saldo '.\App\Support\StoreLabels::source($source).' belum cukup.');
            }
            $fee = ($allocation['refund'] ?? 0) > 0 ? (int) Store::setting('withdraw_fee', '1000') : 0;
            Store::check($expectedFee === $fee, 'Tarif berubah. Periksa rincian dan konfirmasi ulang.');
            Store::check($amount > $fee, 'Nominal penarikan harus lebih besar dari biaya.');
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id, 'token' => $token, 'amount' => $amount,
                'fee' => $fee, 'net' => $amount - $fee, 'allocation' => $allocation,
                'destination' => $destination,
            ]);
            foreach ($allocation as $source => $value) {
                if ($value > 0) {
                    Store::entry($user->id, $source, -$value, 'hold:'.$withdrawal->id.':'.$source, 'Dana pengajuan penarikan ditahan');
                }
            }

            return $withdrawal;
        }, 3);
    }

    public function decideWithdrawal(int $id, string $decision, ?string $reference): void
    {
        DB::transaction(function () use ($id, $decision, $reference) {
            $target = Withdrawal::findOrFail($id);
            Store::lockUser($target->user_id);
            $withdrawal = Withdrawal::whereKey($id)->lockForUpdate()->firstOrFail();
            Store::check($withdrawal->status === 'pending', 'Pengajuan sudah diproses.');
            if ($decision === 'reject') {
                foreach ($withdrawal->allocation as $source => $amount) {
                    if ($amount > 0) {
                        Store::entry($withdrawal->user_id, $source, $amount,
                            'release:'.$id.':'.$source, 'Pengajuan ditolak, saldo dikembalikan');
                    }
                }
            }
            $withdrawal->update([
                'status' => $decision === 'approve' ? 'transferred' : 'rejected',
                'transfer_reference' => $reference,
            ]);
            Store::audit('withdraw_'.$decision, (string) $id);
        }, 3);
    }
}


