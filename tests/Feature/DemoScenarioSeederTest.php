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

    public function test_record_markers_match_navigation_counts_and_clear_after_reading(): void
    {
        Storage::fake('local');
        $this->seed(DemoScenarioSeeder::class);
        $owner = User::where('role', 'owner')->sole();
        $badges = NavigationBadges::for($owner);
        foreach (['pesanan', 'topup', 'pengajuan', 'penghapusan', 'penarikan'] as $section) {
            $response = $this->actingAs($owner)->get(route('staff', ['bagian' => $section]))->assertOk();
            $this->assertSame($badges[$section], substr_count($response->getContent(), 'data-attention="action"'), $section);
        }
        foreach (['garansi', 'masalah'] as $kind) {
            $response = $this->get('/bantuan/'.$kind)->assertOk();
            $this->assertSame($badges[$kind], substr_count($response->getContent(), 'data-attention="action"'), $kind);
        }
        $response = $this->get(route('staff', ['bagian' => 'aktivitas']))->assertOk();
        $this->assertSame($badges['aktivitas'], substr_count($response->getContent(), 'data-attention="unread"'));
        $this->get(route('staff', ['bagian' => 'aktivitas']))->assertOk()->assertDontSee('data-attention="unread"', false);
        $admin = User::where('email', 'auraiftitahh@gmail.com')->sole();
        $adminBadges = NavigationBadges::for($admin);
        foreach (['pesanan', 'topup'] as $section) {
            $response = $this->actingAs($admin)->get(route('staff', ['bagian' => $section]))->assertOk();
            $this->assertSame($adminBadges[$section], substr_count($response->getContent(), 'data-attention="action"'), 'admin '.$section);
        }
        foreach (['garansi', 'masalah'] as $kind) {
            $response = $this->get('/bantuan/'.$kind)->assertOk();
            $this->assertSame($adminBadges[$kind], substr_count($response->getContent(), 'data-attention="action"'), 'admin '.$kind);
        }
        $this->get(route('staff', ['bagian' => 'pesanan']))->assertDontSee('Refund item ke saldo');
        foreach (['penarikan', 'penghapusan', 'aktivitas'] as $section) {
            $this->get(route('staff', ['bagian' => $section]))->assertNotFound();
        }
        $buyer = User::where('email', 'natashasheiyla@gmail.com')->sole();
        $expected = NavigationBadges::for($buyer)['orders'];
        $this->assertGreaterThan(0, $expected);
        $response = $this->actingAs($buyer)->get(route('orders'))->assertOk();
        $this->assertSame($expected, substr_count($response->getContent(), 'data-attention="unread"'));
        $orderId = DB::table('store_notifications')->where('user_id', $buyer->id)->whereNull('read_at')->whereNotNull('order_id')->value('order_id');
        $this->get(route('orders.show', $orderId))->assertOk();
        $response = $this->get(route('orders'))->assertOk();
        $this->assertSame($expected - 1, substr_count($response->getContent(), 'data-attention="unread"'));
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

    public function test_home_limits_promotions_and_all_discounts_are_paginated(): void
    {
        Storage::fake('local');
        $this->seed(DemoScenarioSeeder::class);
        $promotion = Promotion::live()->firstOrFail();
        for ($i = 1; $i <= 12; $i++) {
            $copy = $promotion->replicate();
            $copy->title = 'Additional demo discount '.$i;
            $copy->save();
        }
        $home = $this->get(route('catalog'))->assertOk()->assertSee(route('discounts'));
        $this->assertSame(5, substr_count($home->getContent(), 'data-promo-expires='));
        $page = $this->get(route('discounts'))->assertOk();
        $this->assertSame(12, substr_count($page->getContent(), 'data-promo-expires='));
        $page = $this->get(route('discounts', ['page' => 2]))->assertOk();
        $this->assertSame(3, substr_count($page->getContent(), 'data-promo-expires='));
        Promotion::query()->update(['active' => false]);
        $this->get(route('catalog'))->assertOk()->assertDontSee('data-promo-expires=', false);
        $this->get(route('discounts'))->assertOk()->assertSee('Belum ada diskon aktif');
    }
}
