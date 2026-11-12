<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\StockAccount;
use App\Models\StockSlot;
use App\Models\Topup;
use App\Models\User;
use App\Models\Variant;
use App\Services\Checkout;
use App\Services\Doku;
use App\Services\Store;
use App\Services\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function reseller(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['approval' => 'approved', 'phone' => '08'.random_int(1000000000, 9999999999)])->save();
        return $user;
    }

    public function test_approval_and_staff_access_are_enforced(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/keranjang')->assertForbidden();
        $this->get('/toko')->assertForbidden();
        $this->post('/akun/pengajuan', ['name' => 'Reseller', 'phone' => '081234567890'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'approval' => 'pending']);
        $owner = User::factory()->create();
        $owner->forceFill(['role' => 'owner', 'approval' => 'approved'])->save();
        $this->actingAs($owner)->post('/toko/reseller/'.$user->id, ['decision' => 'approved'])->assertRedirect();
        $this->actingAs($user->fresh())->get('/keranjang')->assertOk();
    }

    public function test_wallet_purchase_fulfills_stock_and_prevents_double_spend(): void
    {
        $user = $this->reseller();
        $variant = Variant::where('delivery', 'stock')->first();
        $stock = StockAccount::create(['variant_id' => $variant->id, 'credentials' => 'secret-access']);
        $stock->slots()->create(['label' => 'Profil 1']);
        Store::entry($user->id, 'topup', 10000, 'test-credit', 'Test');
        $token = (string) Str::uuid();
        $checkout = app(Checkout::class);
        $cart = [['variant' => $variant->id, 'quantity' => 1]];
        $order = $checkout->create($user, $cart, 'wallet', $token);
        $again = $checkout->create($user, $cart, 'wallet', $token);
        $this->assertSame($order->id, $again->id);
        $this->assertSame('delivered', $order->items()->first()->status);
        $this->assertStringContainsString('secret-access', $order->items()->first()->access);
        $this->assertSame(10000 - $variant->price, array_sum(Store::balances($user->id)));
        $this->assertSame('used', StockSlot::first()->state);
        $this->actingAs($this->reseller())->get('/pesanan/'.$order->id)->assertForbidden();
    }

    public function test_checkout_total_mismatch_rolls_back_every_change(): void
    {
        $user = $this->reseller();
        Store::entry($user->id, 'topup', 100000, 'credit', 'Test');
        $variant = Variant::where('delivery', 'manual')->first();
        $this->actingAs($user)->withSession(['cart' => [
            ['variant' => $variant->id, 'quantity' => 1],
        ]])->post('/checkout', [
            'token' => (string) Str::uuid(), 'method' => 'wallet', 'expected_total' => 1,
        ])->assertSessionHasErrors();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(100000, array_sum(Store::balances($user->id)));
    }

    public function test_partial_cancellation_refunds_only_pending_manual_item_once(): void
    {
        $user = $this->reseller();
        Store::entry($user->id, 'topup', 100000, 'credit', 'Test');
        $variant = Variant::where('delivery', 'manual')->first();
        $order = app(Checkout::class)->create($user, [['variant' => $variant->id, 'quantity' => 1]], 'wallet', (string) Str::uuid());
        $order->update(['paid_at' => now()->subMinutes(31)]);
        $item = $order->items()->first();
        $this->actingAs($user)->post('/item/'.$item->id.'/batal')->assertRedirect();
        $this->assertSame($item->amount, Store::balances($user->id)['cancellation']);
        $this->post('/item/'.$item->id.'/batal')->assertSessionHasErrors();
        $this->assertDatabaseCount('wallet_entries', 3);
    }

    public function test_mixed_withdrawal_fee_and_rejection_restore_original_sources(): void
    {
        $user = $this->reseller();
        Store::entry($user->id, 'cancellation', 30000, 'cancel-credit', 'Test');
        Store::entry($user->id, 'refund', 12000, 'refund-credit', 'Test');
        $wallet = app(Wallet::class);
        $allocation = ['topup' => 0, 'cancellation' => 30000, 'refund' => 5000];
        $withdrawal = $wallet->withdraw($user, $allocation, 'BCA 123 Reseller', (string) Str::uuid(), 1000);
        $this->assertSame(34000, $withdrawal->net);
        $this->assertSame(7000, array_sum(Store::balances($user->id)));
        $wallet->decideWithdrawal($withdrawal->id, 'reject', null);
        $this->assertSame(['topup' => 0, 'cancellation' => 30000, 'refund' => 12000], Store::balances($user->id));
    }

    public function test_manual_topup_is_free_and_approved_once(): void
    {
        $user = $this->reseller();
        $wallet = app(Wallet::class);
        $topup = $wallet->topup($user, ['amount' => 10000, 'method' => 'manual'], (string) Str::uuid(), 'proof.pdf');
        $this->assertSame(0, $topup->fee);
        $wallet->approveTopup($topup->id, 'approve');
        $this->assertSame(10000, Store::balances($user->id)['topup']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $wallet->approveTopup($topup->id, 'approve');
    }

    public function test_owner_and_reseller_pages_render_and_admin_cannot_transfer(): void
    {
        $user = $this->reseller();
        foreach (['/akun', '/saldo', '/keranjang', '/pesanan'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
        $owner = User::factory()->create();
        $owner->forceFill(['role' => 'owner'])->save();
        $this->actingAs($owner)->get('/toko')->assertOk();
        $owner->forceFill(['role' => 'admin'])->save();
        $withdrawal = app(Wallet::class);
        Store::entry($user->id, 'topup', 100, 'tiny', 'Test');
        $record = $withdrawal->withdraw($user, ['topup'=>100, 'refund'=>0, 'cancellation'=>0],
            'Test bank', (string) Str::uuid(), 0);
        $this->actingAs($owner)->post('/toko/penarikan/'.$record->id,
            ['decision'=>'approve', 'reference'=>'bank-ref'])->assertForbidden();
    }

    public function test_sandbox_hides_qris_and_explains_why_existing_qris_payments_cannot_continue(): void
    {
        config(['doku.enabled' => true, 'doku.client_id' => 'merchant-test', 'doku.secret' => 'test-secret', 'doku.production' => false]);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $user = $this->reseller();
        $topup = app(Wallet::class)->topup($user, ['amount' => 5000, 'method' => 'QRIS'], (string) Str::uuid(), null);
        $this->actingAs($user)->get(route('wallet'))->assertOk()
            ->assertDontSee('value="QRIS"', false)->assertSee('Virtual Account BCA via DOKU');
        $this->from(route('wallet'))->post(route('payment', $topup->reference))
            ->assertSessionHasErrors(['store' => 'QRIS tidak tersedia di DOKU Sandbox. Gunakan kanal sandbox lain yang aktif untuk transaksi baru.']);
        config(['doku.production' => true]);
        $this->get(route('wallet'))->assertOk()->assertSee('value="QRIS"', false);
    }

    public function test_signed_doku_topup_is_credited_exactly_once_and_tampering_is_rejected(): void
    {
        config(['doku.enabled'=>true, 'doku.client_id'=>'merchant-test', 'doku.secret'=>'test-secret']);
        $user = $this->reseller();
        $topup = app(Wallet::class)->topup($user, ['amount'=>5000, 'method'=>'QRIS'], (string) Str::uuid(), null);
        $body = json_encode(['order'=>['invoice_number'=>$topup->reference, 'amount'=>5000], 'transaction'=>['status'=>'SUCCESS']]);
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $headers = [
            'Content-Type'=>'application/json', 'Client-Id'=>'merchant-test', 'Request-Id'=>'test-id',
            'Request-Timestamp'=>$timestamp,
            'Signature'=>app(Doku::class)->signature('test-id', $timestamp, '/doku/notification', $body),
        ];
        $this->call('POST', '/doku/notification', [], [], [], $this->transformHeadersToServerVars($headers), $body)->assertOk();
        $this->call('POST', '/doku/notification', [], [], [], $this->transformHeadersToServerVars($headers), $body)->assertOk();
        $this->assertSame(5000, Store::balances($user->id)['topup']);
        $this->call('POST', '/doku/notification', [], [], [], $this->transformHeadersToServerVars($headers),
            str_replace('5000', '9000', $body))->assertUnauthorized();
    }

    public function test_customer_can_reconcile_a_sandbox_payment_without_webhook(): void
    {
        config(['doku.enabled' => true, 'doku.client_id' => 'merchant-test', 'doku.secret' => 'test-secret', 'doku.production' => false]);
        $user = $this->reseller();
        $topup = app(Wallet::class)->topup($user, ['amount' => 5000, 'method' => 'EMONEY_DANA'], (string) Str::uuid(), null);
        $topup->payment()->update(['url' => 'https://sandbox.doku.com/checkout-link-v2/example']);
        \Illuminate\Support\Facades\Http::fake([
            'https://api-sandbox.doku.com/orders/v1/status/'.$topup->reference => \Illuminate\Support\Facades\Http::response([
                'order' => ['invoice_number' => $topup->reference, 'amount' => 5000],
                'transaction' => ['status' => 'SUCCESS'],
            ]),
        ]);
        $url = route('payment.check', $topup->reference);
        $this->actingAs($this->reseller())->post($url)->assertForbidden();
        $this->actingAs($user)->get(route('wallet'))->assertOk()->assertSee('Cek status DOKU');
        $this->post($url)->assertRedirect(route('wallet'));
        $this->post($url)->assertRedirect(route('wallet'));
        $this->assertSame('paid', $topup->fresh()->status);
        $this->assertSame(5000, Store::balances($user->id)['topup']);
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_qris_fee_is_zero_even_if_owner_config_contains_qris(): void
    {
        DB::table('store_settings')->insert([
            ['key'=>'fee_channels','value'=>'["QRIS","EMONEY_DANA"]'],
            ['key'=>'topup_fee_type','value'=>'percent'], ['key'=>'topup_fee_value','value'=>'2'],
        ]);
        $this->assertSame(0, Store::fee(100000, 'topup', 'QRIS'));
        $this->assertSame(2000, Store::fee(100000, 'topup', 'EMONEY_DANA'));
    }

    public function test_late_payment_does_not_take_slot_reserved_by_another_order(): void
    {
        $user = $this->reseller();
        $variant = Variant::where('delivery', 'stock')->first();
        $stock = StockAccount::create(['variant_id'=>$variant->id, 'credentials'=>'access']);
        $slot = $stock->slots()->create(['label'=>'Profil 1']);
        $order = app(Checkout::class)->create($user, [['variant'=>$variant->id, 'quantity'=>1]], 'QRIS', (string) Str::uuid());
        $order->update(['expires_at'=>now()->subMinute()]);
        $this->artisan('biuble:expire')->assertSuccessful();
        $slot->refresh()->update(['state'=>'reserved', 'reservation_token'=>(string) Str::uuid()]);
        DB::transaction(function () use ($user, $order) {
            Store::lockUser($user->id);
            app(Checkout::class)->complete($order->fresh());
        });
        $this->assertSame('review', $order->items()->first()->status);
        $this->assertSame('reserved', $slot->fresh()->state);
    }

    public function test_owner_needs_recent_verification_before_marking_transfer_complete(): void
    {
        $reseller = $this->reseller();
        Store::entry($reseller->id, 'topup', 5000, 'withdraw-credit', 'Test');
        $withdrawal = app(Wallet::class)->withdraw($reseller,
            ['topup'=>5000, 'cancellation'=>0, 'refund'=>0], 'BCA 123', (string) Str::uuid(), 0);
        $owner = User::factory()->create();
        $owner->forceFill(['role'=>'owner'])->save();
        $this->actingAs($owner)->post('/toko/penarikan/'.$withdrawal->id,
            ['decision'=>'approve','reference'=>'bank-123'])->assertRedirect('/keamanan');
        $this->assertSame('pending', $withdrawal->fresh()->status);
        $this->withSession(['owner_verified_at'=>now()->timestamp])
            ->post('/toko/penarikan/'.$withdrawal->id, ['decision'=>'approve','reference'=>'bank-123'])->assertRedirect();
        $this->assertSame('transferred', $withdrawal->fresh()->status);
        $this->assertSame(0, array_sum(Store::balances($reseller->id)));
    }

    public function test_cart_checkout_order_invoice_and_manual_delivery_work_through_routes(): void
    {
        $reseller = $this->reseller();
        $variant = Variant::where('delivery','manual')->first();
        Store::entry($reseller->id, 'topup', 100000, 'route-credit', 'Test');
        $this->actingAs($reseller)->post('/keranjang', ['variant'=>$variant->id,'quantity'=>1])->assertRedirect('/keranjang');
        $this->get('/keranjang')->assertOk()->assertSee('Konfirmasi pesanan');
        $this->post('/checkout', ['method'=>'wallet','expected_total'=>$variant->price,'token'=>(string) Str::uuid()])->assertRedirect();
        $order = Order::firstOrFail();
        $this->get('/pesanan/'.$order->id)->assertOk()->assertSee('Menunggu akses');
        $this->get('/pesanan/'.$order->id.'?invoice=1')->assertOk()->assertSee($reseller->phone);
        $owner = User::factory()->create();
        $owner->forceFill(['role'=>'owner'])->save();
        $this->actingAs($owner)->post('/toko/item/'.$order->items()->first()->id.'/akses', ['access'=>'email dan petunjuk akses'])->assertRedirect();
        $this->actingAs($reseller)->get('/pesanan/'.$order->id)->assertOk()->assertSee('email dan petunjuk akses');
        $this->get('/akun')->assertOk()->assertSee('Akses siap');
    }
}

