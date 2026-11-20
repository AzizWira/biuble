<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\StockAccount;
use App\Models\User;
use App\Models\Variant;
use App\Services\NavigationBadges;
use App\Services\Store;
use Database\Seeders\DemoScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoScenarioSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_demo_is_consistent_repeatable_and_visible_to_each_role(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Mail::fake();
        $this->seed(DemoScenarioSeeder::class);
        $owner = User::where('email', 'wira123widodo@gmail.com')->sole();
        $admin = User::where('email', 'auraiftitahh@gmail.com')->sole();
        $buyer = User::where('email', 'natashasheiyla@gmail.com')->sole();
        $this->assertSame('owner', $owner->role);
        $this->assertSame('admin', $admin->role);
        $this->assertSame('reseller', $buyer->role);
        $this->assertSame('profiles', Product::where('slug', 'netflix')->value('stock_mode'));
        $this->assertSame('slots', Product::where('slug', 'chatgpt')->value('stock_mode'));
        $netflix = Variant::where('name', 'Sharing · Bulanan · 1 user · 1 bulan')->sole();
        $accounts = StockAccount::where('variant_id', $netflix->id)->with('slots')->get();
        $this->assertCount(2, $accounts);
        $this->assertCount(5, $accounts->first()->slots);
        $this->assertCount(5, $accounts->first()->slots->pluck('pin')->unique());
        $this->assertSame(2, $netflix->availableUnits());
        $this->assertEqualsCanonicalizing(['available', 'reserved', 'used', 'inspection'], $netflix->stockSlots()->distinct()->pluck('state')->all());
        $this->assertSame(3, Promotion::live()->count());
        $this->assertSame(3, Announcement::live()->unreadFor($buyer->id)->count());
        $badges = NavigationBadges::for($owner);
        foreach (['pesanan', 'topup', 'pengajuan', 'penghapusan', 'penarikan', 'aktivitas', 'garansi', 'masalah'] as $key) {
            $this->assertGreaterThan(0, $badges[$key], $key);
        }
        foreach (Store::balances($buyer->id) as $amount) $this->assertGreaterThan(0, $amount);
        foreach (DB::table('support_files')->pluck('path') as $path) Storage::disk('local')->assertExists($path);
        foreach (['pesanan', 'stok', 'katalog', 'kategori', 'informasi', 'diskon', 'pengajuan', 'penghapusan', 'penarikan', 'admin', 'aktivitas'] as $section) {
            $this->actingAs($owner)->get(route('staff', ['bagian' => $section]))->assertOk();
        }
        $this->actingAs($admin)->get(route('staff', ['bagian' => 'informasi']))->assertOk()->assertSee('Stok Netflix, Disney+, dan Prime tersedia');
        $this->actingAs($buyer)->get('/')->assertOk()->assertSee('DEMO Promo Netflix 48 jam')->assertSee('Selamat datang di demo Biuble');
        $this->get(route('orders'))->assertOk();
        foreach (Order::where('user_id', $buyer->id)->get() as $order) {
            $this->get(route('orders.show', $order))->assertOk();
        }
        $archived = Order::where('reference', 'DEMO-RIWAYAT-PRODUK-SUDAH-DIHAPUS')->sole();
        $this->assertNotNull($archived->items()->first()->stockSlot->account);
        $counts = collect(['orders', 'users', 'wallet_entries', 'stock_slots', 'support_files', 'announcements', 'promotions'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
        $balance = Store::balances($buyer->id);
        $this->seed(DemoScenarioSeeder::class);
        foreach ($counts as $table => $count) $this->assertSame($count, DB::table($table)->count(), $table);
        $this->assertSame($balance, Store::balances($buyer->id));
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_seeded_cart_is_restored_once_after_reseller_login(): void
    {
        Storage::fake('local');
        $this->seed(DemoScenarioSeeder::class);
        $buyer = User::where('email', 'natashasheiyla@gmail.com')->sole();
        $challenge = ['email' => $buyer->email, 'hash' => Hash::make('123456'), 'expires' => now()->addMinutes(5)->timestamp, 'attempts' => 0];
        $this->withSession(['otp' => $challenge])->post(route('otp.verify'), ['code' => '123456'])->assertRedirect(route('catalog'));
        $this->getJson(route('updates'))->assertJsonPath('badges.cart', 3);
        $this->get(route('cart'))->assertOk()->assertSee('Konfirmasi pesanan');
        $this->post(route('logout'))->assertRedirect();
        $this->withSession(['otp' => $challenge])->post(route('otp.verify'), ['code' => '123456'])->assertRedirect(route('catalog'));
        $this->getJson(route('updates'))->assertJsonPath('badges.cart', 0);
    }
}
