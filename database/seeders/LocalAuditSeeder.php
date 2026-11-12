<?php

namespace Database\Seeders;

use App\Models\{Order, Topup, User, Variant};
use App\Services\{Checkout, Store, Wallet};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

class LocalAuditSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Data audit hanya boleh dibuat di lingkungan lokal atau testing.');
        }

        $this->call(DatabaseSeeder::class);
        $this->call(LocalAuditStockSeeder::class);
        $this->call(LocalAuditWarrantySeeder::class);
        DB::transaction(function () {
            $accounts = [
                'natashasheiyla@gmail.com' => ['Natasha', 'reseller'],
                'wira123widodo@gmail.com' => ['Wira', 'owner'],
                'auraiftitahh@gmail.com' => ['Aura', 'admin'],
            ];
            foreach ($accounts as $email => [$name, $role]) {
                User::unguarded(fn () => User::firstOrCreate(['email' => $email], [
                    'name' => $name, 'password' => Hash::make(Str::random(64)), 'role' => $role, 'approval' => 'approved',
                ]));
            }
            $buyer = User::where('email', 'natashasheiyla@gmail.com')->firstOrFail();
            if ($buyer->role !== 'reseller' || $buyer->approval !== 'approved' || $buyer->isReadOnly()) {
                $this->command?->warn('Data transaksi demo dilewati karena akun contoh tidak memiliki akses reseller aktif.');
                return;
            }
            if (! $buyer->phone) {
                $buyer->forceFill(['phone'=>'080000000001'])->save();
            }
            $topup = Topup::firstOrCreate(['reference'=>'DEMO-TOPUP-PAID'], [
                'user_id'=>$buyer->id, 'token'=>(string) Str::uuid(), 'method'=>'manual',
                'amount'=>200000, 'fee'=>0, 'total'=>200000, 'status'=>'paid',
            ]);
            if (! DB::table('wallet_entries')->where('key', 'topup:'.$topup->id)->exists()) {
                Store::entry($buyer->id, 'topup', 200000, 'topup:'.$topup->id, 'DEMO: saldo simulasi audit, bukan transfer nyata');
            }
            foreach (['cancellation'=>30000, 'refund'=>15000] as $source=>$amount) {
                if (! DB::table('wallet_entries')->where('key', 'demo:'.$source)->exists()) {
                    Store::entry($buyer->id, $source, $amount, 'demo:'.$source, 'DEMO: contoh saldo refund untuk audit');
                }
            }
            Topup::firstOrCreate(['reference'=>'DEMO-TOPUP-PENDING'], [
                'user_id'=>$buyer->id, 'token'=>(string) Str::uuid(), 'method'=>'manual',
                'amount'=>25000, 'fee'=>0, 'total'=>25000, 'status'=>'pending',
            ]);
            $withdrawToken = '00000000-0000-4000-8000-000000000151';
            if (! DB::table('withdrawals')->where('token', $withdrawToken)->exists()) {
                app(Wallet::class)->withdraw($buyer, ['topup'=>5000, 'cancellation'=>0, 'refund'=>5000],
                    'DEMO — tujuan penarikan simulasi, jangan ditransfer', $withdrawToken, (int) Store::setting('withdraw_fee', '1000'));
            }
            foreach (['stock'=>'DEMO-ORDER-READY', 'invite'=>'DEMO-ORDER-INVITE', 'manual'=>'DEMO-ORDER-WAITING'] as $delivery=>$reference) {
                if (! Order::where('reference', $reference)->exists()) {
                    $variant = Variant::where('delivery', $delivery)->firstOrFail();
                    $order = app(Checkout::class)->create($buyer, [[
                        'variant'=>$variant->id, 'quantity'=>1, 'email'=>'demo-invite@example.test',
                    ]], 'wallet', (string) Str::uuid());
                    $order->update(['reference'=>$reference, 'paid_at'=>now()->subMinutes(35)]);
                }
            }
            // Keep examples of every balance source visible after sample purchases.
            foreach (['cancellation'=>20000, 'refund'=>30000] as $source=>$amount) {
                if (! DB::table('wallet_entries')->where('key', 'demo:remaining:'.$source)->exists()) {
                    Store::entry($buyer->id, $source, $amount, 'demo:remaining:'.$source, 'DEMO: sisa saldo contoh untuk audit penarikan');
                }
            }
            $candidate = User::firstOrCreate(['email'=>'calon-reseller@example.test'], [
                'name'=>'DEMO Calon Reseller', 'password'=>Hash::make(Str::random(64)),
            ]);
            if ($candidate->approval === 'draft') {
                $candidate->forceFill(['approval'=>'pending', 'phone'=>'080000000002'])->save();
            }
            DB::table('store_settings')->updateOrInsert(['key'=>'local_audit_data'], ['value'=>'1']);
        });
        $this->command?->info('Tiga akun siap. Login tetap OTP/Google. Data DEMO adalah simulasi tanpa transaksi DOKU.');
    }
}
