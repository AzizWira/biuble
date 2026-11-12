<?php

namespace Tests\Feature;

use App\Models\{Order, User};
use App\Services\Store;
use Database\Seeders\LocalAuditSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UiAuditTest extends TestCase
{
    use RefreshDatabase;
    protected $seed = true;

    public function test_audit_seed_is_repeatable_and_role_pages_render(): void
    {
        $this->seed(LocalAuditSeeder::class);
        $buyer = User::where('email','natashasheiyla@gmail.com')->firstOrFail();
        $balances = Store::balances($buyer->id);
        $counts = collect(['orders','order_items','topups','wallet_entries','stock_slots','withdrawals'])
            ->mapWithKeys(fn($table)=>[$table=>DB::table($table)->count()]);
        $this->seed(LocalAuditSeeder::class);
        $this->assertSame($balances, Store::balances($buyer->id));
        foreach ($counts as $table=>$count) $this->assertSame($count, DB::table($table)->count(), $table);
        $this->assertSame('approved', $buyer->approval);
        $this->assertSame('reseller', $buyer->role);

        // Gateway-pending states have actions absent from the manual demo seed.
        app(\App\Services\Wallet::class)->topup($buyer,
            ['method'=>'VIRTUAL_ACCOUNT_BCA','amount'=>10000], (string)\Illuminate\Support\Str::uuid(), null);
        $pendingVariant=\App\Models\Variant::where('delivery','stock')->firstOrFail();
        $pendingOrder=app(\App\Services\Checkout::class)->create($buyer,
            [['variant'=>$pendingVariant->id,'quantity'=>1]], 'VIRTUAL_ACCOUNT_BCA', (string)\Illuminate\Support\Str::uuid());
        $this->actingAs($buyer);
        foreach (['/'=>'catalog','/produk/netflix'=>'netflix','/produk/canva'=>'canva',
            '/keranjang'=>'cart-empty','/saldo'=>'wallet','/pesanan'=>'orders','/akun'=>'account'] as $url=>$name) {
            $this->snapshot($name, $this->get($url)->assertOk()->getContent());
        }
        $originalEnvironment=app()->environment();
        $previousDoku=config('doku');
        config(['doku.enabled'=>true,'doku.client_id'=>'audit-client','doku.secret'=>'audit-secret']);
        \Illuminate\Support\Facades\Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Simulated timeout'));
        $this->from('/saldo')->post('/bayar/'.$pendingOrder->reference)->assertRedirect('/saldo')->assertSessionHasErrors('store');
        $errorBag=session('errors');
        $walletView=$this->get('/saldo')->assertOk()->baseResponse->getOriginalContent();
        app()->instance('env','local');
        $errorHtml=$walletView->with('errors',$errorBag)->render();
        $this->assertStringContainsString('Koneksi ke server DOKU gagal', $errorHtml);
        $this->snapshot('wallet-connection-error', $errorHtml);
        config(['doku'=>$previousDoku]);
        $this->session(['errors'=>new \Illuminate\Support\ViewErrorBag]);
        app()->instance('env',$originalEnvironment);
        $this->snapshot('order-pending', $this->get('/pesanan/'.$pendingOrder->id)->assertOk()->getContent());
        $order = Order::where('reference','DEMO-ORDER-READY')->firstOrFail();
        $this->snapshot('order', $this->get('/pesanan/'.$order->id)->assertOk()->getContent());
        $variant = \App\Models\Variant::where('delivery','invite')->firstOrFail();
        $this->post('/keranjang',['variant'=>$variant->id,'quantity'=>2,'email'=>'demo@example.test','designer'=>1])->assertRedirect();
        $this->snapshot('cart-filled', $this->get('/keranjang')->assertOk()->getContent());
        $this->get('/toko')->assertForbidden();

        foreach (['owner'=>'wira123widodo@gmail.com', 'admin'=>'auraiftitahh@gmail.com'] as $role=>$email) {
            $user=User::where('email',$email)->firstOrFail();
            $this->assertSame($role,$user->role);
            $this->actingAs($user);
            $response=$this->get('/toko')->assertOk()->assertSee('Pesanan terbaru')->assertDontSee('Tambah akun dan slot');
            $this->snapshot($role.'-orders',$response->getContent());
            foreach (['topup','stok','katalog'] as $section) {
                $this->snapshot($role.'-'.$section,$this->get('/toko?bagian='.$section)->assertOk()->getContent());
            }
            if ($role === 'owner') {
                foreach (['pengajuan','penarikan','kategori','admin','pengaturan','aktivitas'] as $section) {
                    $this->snapshot('owner-'.$section,$this->get('/toko?bagian='.$section)->assertOk()->getContent());
                }
                $this->get('/toko?bagian=aktivitas')->assertDontSee('reseller_approved');
            } else {
                $this->get('/toko?bagian=pengaturan')->assertNotFound();
                $this->get('/toko?bagian=penarikan')->assertNotFound();
            }
            $this->get('/toko?bagian=../../.env')->assertNotFound();
        }
    }

    public function test_validation_and_history_use_readable_indonesian_labels(): void
    {
        app()->setLocale('id');
        $this->seed(LocalAuditSeeder::class);
        $owner=User::where('email','wira123widodo@gmail.com')->firstOrFail();
        $buyer=User::where('email','natashasheiyla@gmail.com')->firstOrFail();
        DB::table('store_audits')->insert([
            'actor_id'=>$owner->id,'action'=>'reseller_approved','subject'=>(string)$buyer->id,'created_at'=>now(),
        ]);
        $this->actingAs($owner)->get('/toko?bagian=aktivitas')->assertOk()
            ->assertSee('Reseller disetujui')->assertSee($owner->name)->assertDontSee('reseller_approved');
        $this->post('/toko/produk', [])->assertSessionHasErrors(['name'=>'Nama wajib diisi.', 'category_id'=>'Kategori wajib diisi.']);
        $order=Order::where('reference','DEMO-ORDER-READY')->firstOrFail();
        $this->actingAs($buyer)->get('/pesanan/'.$order->id)->assertOk()->assertSee('Saldo Biuble')->assertSee('Sudah dibayar');
        foreach (range(1,35) as $i) Store::entry($buyer->id,'topup',0,'demo:pagination:'.$i,'DEMO riwayat audit');
        $this->get('/saldo')->assertOk()->assertSee('Berikutnya')->assertSee('Navigasi halaman');
    }

    private function snapshot(string $name, string $html): void
    {
        // Optional static fixtures for browser layout QA: isolated test data only.
        if ($directory=getenv('BIUBLE_AUDIT_DIR')) {
            if (! is_dir($directory)) mkdir($directory, 0777, true);
            $html=preg_replace('/(href|src)="http:\/\/localhost\//', '$1="http://127.0.0.1:8000/', $html);
            file_put_contents($directory.'/'.$name.'.html', $html);
        }
    }
}
