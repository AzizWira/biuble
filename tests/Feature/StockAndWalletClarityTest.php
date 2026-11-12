<?php

namespace Tests\Feature;

use App\Models\{Product, StockAccount, User, Variant};
use App\Services\{Checkout, Store};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockAndWalletClarityTest extends TestCase
{
    use RefreshDatabase;
    protected $seed = true;

    private function buyer(): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>'reseller','approval'=>'approved','phone'=>'081234567891'])->save();
        return $user;
    }

    public function test_only_available_slots_can_be_ordered_and_empty_packages_are_disabled(): void
    {
        $buyer=$this->buyer();
        $product=Product::create(['category_id'=>1,'name'=>'Stock test','slug'=>'stock-test','description'=>'Test']);
        $variant=Variant::create(['product_id'=>$product->id,'name'=>'One profile','price'=>2500,'type'=>'sharing','delivery'=>'stock']);
        $account=StockAccount::create(['variant_id'=>$variant->id,'credentials'=>'private-test-credential']);
        $available=$account->slots()->create(['label'=>'Available']);
        foreach (['reserved','used','inspection'] as $state) $account->slots()->create(['label'=>$state,'state'=>$state]);
        $this->assertSame(1,Variant::withAvailableStock()->find($variant->id)->availableUnits());
        $this->capture('stock-available', $this->actingAs($buyer)->get('/produk/stock-test')->assertOk()->assertSee('Stok tersedia: 1')->assertDontSee('private-test-credential')->getContent());
        $this->post('/keranjang',['variant'=>$variant->id,'quantity'=>2])->assertSessionHasErrors('store');
        $this->post('/keranjang',['variant'=>$variant->id,'quantity'=>1])->assertRedirect('/keranjang');
        $this->post('/keranjang',['variant'=>$variant->id,'quantity'=>1])->assertSessionHasErrors('store');
        $available->update(['state'=>'used']);
        $this->capture('stock-empty', $this->get('/produk/stock-test')->assertOk()->assertSee('Stok habis')->assertSee('aria-disabled="true"',false)->assertDontSee('variant-disclosure')->getContent());
        $this->get('/')->assertOk()->assertDontSee('href="'.route('product','stock-test').'"',false);
        $this->get('/keranjang')->assertOk()->assertSee('Ketersediaan')->assertSee('disabled',false);
        $this->post('/keranjang',['variant'=>$variant->id,'quantity'=>1])->assertSessionHasErrors('store');
        $manual=Variant::where('delivery','manual')->firstOrFail();
        $this->post('/keranjang',['variant'=>$manual->id,'quantity'=>1])->assertRedirect('/keranjang');
    }

    public function test_purchase_from_refund_balance_stays_a_purchase_and_dates_use_month_names(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-05 16:35:00', config('app.timezone')));
        $buyer=$this->buyer();
        Store::entry($buyer->id,'refund',10000,'demo:refund','DEMO refund balance');
        $variant=Variant::where('delivery','stock')->where('duration_days',1)->firstOrFail();
        $account=StockAccount::create(['variant_id'=>$variant->id,'credentials'=>'Delivered account']);
        $account->slots()->create(['label'=>'Profile 1']);
        $order=app(Checkout::class)->create($buyer,[['variant'=>$variant->id,'quantity'=>1]],'wallet',(string)Str::uuid());
        $this->assertSame('delivered',$order->items()->first()->status);
        $this->assertSame(7500,Store::balances($buyer->id)['refund']);
        $before=DB::table('wallet_entries')->where('user_id',$buyer->id)->count();
        $this->actingAs($buyer)->get('/saldo')->assertOk()->assertSee('Pembelian produk')
            ->assertSee('Diambil dari saldo: Refund pesanan')->assertSee($order->reference)
            ->assertSee('5 November 2026')->assertDontSee('Refund lain')->assertDontSee('2026-11-05 16:35:00');
        $this->capture('ledger-purchase', $this->get('/saldo')->assertOk()->getContent());
        $this->get('/pesanan/'.$order->id)->assertOk()->assertSee('Mulai 5 November 2026')->assertSee('Berakhir 6 November 2026')->assertDontSee('05/11/2026');
        $this->assertSame($before,DB::table('wallet_entries')->where('user_id',$buyer->id)->count());
        $this->assertSame(7500,Store::balances($buyer->id)['refund']);
    }

    public function test_staff_stock_drills_into_one_variant_and_only_releases_eligible_profiles(): void
    {
        $buyer = $this->buyer();
        Store::entry($buyer->id, 'topup', 50000, 'demo:stock-purchase', 'Test balance');
        $staff = User::factory()->create(['role' => 'admin']);
        $variant = Variant::where('delivery', 'stock')->where('duration_days', 1)->firstOrFail();
        $account = StockAccount::create([
            'variant_id' => $variant->id, 'label' => 'Akun Harian Biru', 'credentials' => 'secret-test-credential',
        ]);
        $used = $account->slots()->create(['label' => 'Profil dibeli']);
        $account->slots()->create(['label' => 'Profil tersedia']);
        $order = app(Checkout::class)->create($buyer, [['variant' => $variant->id, 'quantity' => 1]], 'wallet', (string) Str::uuid());
        $base = ['bagian' => 'stok'];
        $app = $base + ['app' => $variant->product->slug];
        $detail = route('staff', $app + ['varian' => $variant->id]);
        $accountDetail = route('staff', $app + ['varian' => $variant->id, 'akun' => $account->id]);
        $access = route('staff.stock-access', $account);

        $this->actingAs($buyer)->getJson($access)->assertForbidden()->assertDontSee('secret-test-credential');
        $this->actingAs($staff)->get(route('staff', $base))->assertOk()
            ->assertSee('Pilih aplikasi')->assertSee('Netflix')->assertSee('ChatGPT')->assertSee(route('staff', $app).'#stok-pilihan')
            ->assertDontSee('Stok masuk')->assertDontSee('Stok keluar')->assertDontSee($order->reference)
            ->assertDontSee('Cari varian')->assertDontSee('Akun Harian Biru')->assertDontSee('secret-test-credential');
        $this->get(route('staff', $app))->assertOk()->assertSee('Cari varian')
            ->assertSee('Sharing · Harian')->assertSee('1 slot siap')->assertSee($detail.'#stok-akun')
            ->assertSee('Stok masuk')->assertSee('Stok keluar')->assertSee($order->reference)
            ->assertDontSee($accountDetail.'#stok-akun');
        $this->get(route('staff', $base + ['app' => 'chatgpt']))->assertOk()
            ->assertDontSee('Akun Harian Biru')->assertDontSee($order->reference);
        $this->get($detail)->assertOk()->assertSee('Akun Harian Biru')->assertSee($accountDetail.'#stok-akun')
            ->assertDontSee('Profil tersedia')->assertDontSee('secret-test-credential');
        $this->get($accountDetail)->assertOk()->assertSee('Segera habis')->assertSee('Pernah terjual')
            ->assertSee('Profil tersedia')->assertSee('Belum terjual')->assertSee('Lihat detail akses')->assertSee($access)
            ->assertDontSee('secret-test-credential')->assertDontSee(route('staff.recycle', $used));
        $this->getJson($access)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('access', 'secret-test-credential');
        $this->from($detail)->post(route('staff.stock'), ['variant_id' => $variant->id, 'credentials' => 'another-secret', 'slots' => 0])
            ->assertRedirect($detail.'#stok-tambah')->assertSessionHasErrors('slots')->assertSessionMissing('_old_input.credentials');
        $returnToSlot = $accountDetail.'#stok-profil-'.$used->id;
        $this->from($accountDetail)->post(route('staff.recycle', $used))->assertRedirect($returnToSlot)->assertSessionHasErrors('store');
        $this->assertSame('used', $used->refresh()->state);

        $this->travel(2)->days();
        $this->get($accountDetail)->assertOk()->assertSee('Perlu diperiksa')->assertSee(route('staff.recycle', $used));
        $this->from($accountDetail)->post(route('staff.recycle', $used))->assertRedirect($returnToSlot);
        $this->assertSame('available', $used->refresh()->state);
    }

    private function capture(string $name, string $html): void
    {
        if ($directory=getenv('BIUBLE_AUDIT_DIR')) {
            if (! is_dir($directory)) mkdir($directory,0777,true);
            $html=preg_replace('/(href|src)="http:\/\/localhost\//', '$1="http://127.0.0.1:8000/', $html);
            file_put_contents($directory.'/'.$name.'.html',$html);
        }
    }
}
