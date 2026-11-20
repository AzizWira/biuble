<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\StockAccount;
use App\Models\User;
use App\Models\Variant;
use App\Services\Checkout;
use App\Services\Doku;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class NovemberUpdatesTest extends TestCase
{
    use RefreshDatabase;
    protected $seed = true;

    private function buyer(): User
    {
        return User::factory()->create(['approval' => 'approved', 'phone' => '081234567890']);
    }

    private function gateway(): void
    {
        config(['doku.enabled' => true, 'doku.client_id' => 'test', 'doku.secret' => 'test-secret']);
        Http::preventStrayRequests();
    }

    private function success(Payment $payment): void
    {
        $body = json_encode(['order' => ['invoice_number' => $payment->reference, 'amount' => $payment->amount], 'transaction' => ['status' => 'SUCCESS']]);
        $time = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $id = (string) Str::uuid();
        $this->call('POST', '/doku/notification', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_CLIENT_ID' => 'test', 'HTTP_REQUEST_ID' => $id,
            'HTTP_REQUEST_TIMESTAMP' => $time, 'HTTP_SIGNATURE' => app(Doku::class)->signature($id, $time, '/doku/notification', $body),
        ], $body)->assertOk();
    }

    public function test_payment_changes_preserve_old_invoices_and_credit_late_success_once(): void
    {
        $this->gateway();
        $buyer = $this->buyer();
        $variant = Variant::where('delivery', 'manual')->firstOrFail();
        $order = app(Checkout::class)->create($buyer, [['variant' => $variant->id, 'quantity' => 1]], 'VIRTUAL_ACCOUNT_BCA', (string) Str::uuid());
        $old = $order->payment;
        $this->actingAs($buyer)->post(route('orders.method', $order), [
            'method' => 'EMONEY_DANA', 'expected_total' => $order->total, 'expected_payment' => $old->id,
        ])->assertSessionHasNoErrors();
        $new = $order->fresh()->payment;
        $this->assertNotEquals($old->reference, $new->reference);
        $this->assertSame('superseded', $old->fresh()->status);
        $this->post(route('payment', $old->reference))->assertSessionHasErrors('store');
        $this->post(route('orders.method', $order), ['method' => 'wallet', 'expected_total' => $order->total, 'expected_payment' => $old->id])->assertSessionHasErrors('store');
        $this->success($old);
        $this->success($old);
        $this->assertSame($old->amount, Store::balances($buyer->id)['cancellation']);
        $this->assertNull($order->fresh()->paid_at);
        $this->success($new);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertDatabaseCount('wallet_entries', 1);
        $this->get(route('orders.show', $order))->assertOk();
    }

    public function test_pending_cancellation_releases_stock_and_cannot_cancel_paid_order(): void
    {
        $this->gateway();
        $buyer = $this->buyer();
        $variant = Variant::where('delivery', 'stock')->firstOrFail();
        $account = StockAccount::create(['variant_id' => $variant->id, 'credentials' => 'test']);
        $slot = $account->slots()->create(['label' => 'P1']);
        $order = app(Checkout::class)->create($buyer, [['variant' => $variant->id, 'quantity' => 1]], 'VIRTUAL_ACCOUNT_BCA', (string) Str::uuid());
        $payment = $order->payment;
        $this->actingAs($buyer)->post(route('orders.cancel', $order))->assertSessionHasNoErrors();
        $this->assertSame('available', $slot->fresh()->state);
        $this->post(route('orders.cancel', $order))->assertSessionHasNoErrors();
        $this->success($payment);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame($payment->amount, Store::balances($buyer->id)['cancellation']);
        $paid = app(Checkout::class)->create($buyer, [['variant' => $variant->id, 'quantity' => 1]], 'wallet', (string) Str::uuid());
        $this->post(route('orders.cancel', $paid))->assertSessionHasErrors('store');
        $this->actingAs(User::factory()->create())->post(route('orders.cancel', $order))->assertForbidden();
    }

    public function test_profile_bulk_entry_encrypts_pins_and_delivers_only_the_purchased_profile(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $variant = Variant::where('delivery', 'stock')->firstOrFail();
        $variant->product->update(['stock_mode' => 'profiles']);
        $this->actingAs($owner)->post(route('staff.stock'), [
            'variant_id' => $variant->id, 'credentials' => 'shared-credentials', 'slots' => 2, 'label' => 'Profil test',
            'profiles' => [['label' => 'Biru', 'pin' => '0115'], ['label' => 'Merah', 'pin' => '0226']],
        ])->assertSessionHasNoErrors();
        $account = StockAccount::where('label', 'Profil test')->sole();
        $this->assertNotEquals('0115', DB::table('stock_slots')->where('stock_account_id', $account->id)->value('pin'));
        $buyer = $this->buyer();
        Store::entry($buyer->id, 'topup', 100000, 'test-credit', 'test');
        $order = app(Checkout::class)->create($buyer, [['variant' => $variant->id, 'quantity' => 1]], 'wallet', (string) Str::uuid());
        $this->actingAs($buyer)->get(route('orders.show', $order))->assertOk()->assertSee('0115')->assertDontSee('0226');
        $this->getJson(route('staff.stock-access', $account))->assertForbidden();
        $this->actingAs($owner)->getJson(route('staff.stock-access', $account))->assertOk()->assertJsonPath('profiles.1.pin', '0226');
        $this->post(route('staff.stock'), ['variant_id' => $variant->id, 'credentials' => 'test', 'slots' => 2, 'profiles' => [['label' => 'Only one']]])->assertSessionHasErrors('store');
    }

    public function test_product_proof_setting_is_snapshotted_and_requires_customer_details(): void
    {
        Storage::fake('local');
        $buyer = $this->buyer();
        $variant = Variant::where('delivery', 'manual')->firstOrFail();
        $variant->product->update(['login_proof_required' => true]);
        Store::entry($buyer->id, 'topup', 100000, 'credit', 'test');
        $order = app(Checkout::class)->create($buyer, [['variant' => $variant->id, 'quantity' => 1]], 'wallet', (string) Str::uuid());
        $item = $order->items()->firstOrFail();
        $variant->product->update(['login_proof_required' => false]);
        app(Checkout::class)->deliver($item, 'test');
        $this->actingAs($buyer)->post(route('support.proof.upload', $item), ['evidence' => UploadedFile::fake()->image('login.png')])->assertSessionHasErrors(['device', 'location']);
        $this->post(route('support.proof.upload', $item), ['evidence' => UploadedFile::fake()->image('login.png'), 'device' => 'iPhone 14', 'location' => 'Bandung'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('login_proofs', ['order_item_id' => $item->id, 'device' => 'iPhone 14', 'location' => 'Bandung']);
    }

    public function test_deletion_preserves_order_history_and_rejects_reserved_stock_and_admins(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $buyer = $this->buyer();
        $variant = Variant::where('delivery', 'stock')->firstOrFail();
        $account = StockAccount::create(['variant_id' => $variant->id, 'credentials' => 'test']);
        $slot = $account->slots()->create(['label' => 'P1']);
        $order = app(Checkout::class)->create($buyer, [['variant' => $variant->id, 'quantity' => 1]], 'VIRTUAL_ACCOUNT_BCA', (string) Str::uuid());
        $url = route('staff.catalog.remove', ['kind' => 'product', 'id' => $variant->product_id]);
        $this->actingAs($owner)->delete($url)->assertSessionHasErrors('store');
        $this->actingAs($buyer)->post(route('orders.cancel', $order));
        $this->actingAs(User::factory()->create(['role' => 'admin']))->delete($url)->assertForbidden();
        $this->actingAs($owner)->delete($url)->assertSessionHasNoErrors();
        $this->assertSoftDeleted('stock_slots', ['id' => $slot->id]);
        $this->assertNotNull($order->items()->first()->stockSlot->account);
        $this->actingAs($buyer)->get(route('orders.show', $order))->assertOk();
    }

    public function test_information_is_flexible_scoped_scheduled_and_read_by_revision(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = $this->buyer();
        $this->actingAs($owner)->get(route('staff', ['bagian' => 'informasi']))->assertOk();
        $data = ['title' => 'Stok beberapa aplikasi', 'body' => 'Netflix dan Canva tersedia', 'published' => 1, 'starts_at' => now()->subMinute()->format('Y-m-d H:i'), 'until' => 'duration', 'hours' => 2];
        $this->post(route('staff.information.save'), $data)->assertSessionHasNoErrors();
        $announcement = Announcement::sole();
        $this->actingAs($admin)->post(route('staff.information.save', $announcement), $data)->assertForbidden();
        $this->actingAs($buyer)->getJson(route('updates'))->assertJsonPath('badges.informasi', 1);
        $this->get('/')->assertOk()->assertSee('Netflix dan Canva tersedia');
        $this->postJson(route('information.read'), ['announcement' => $announcement->id, 'revision' => 1])->assertOk();
        $this->getJson(route('updates'))->assertJsonPath('badges.informasi', 0);
        $this->actingAs($owner)->post(route('staff.information.save', $announcement), $data + ['notify_again' => 1])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->postJson(route('information.read'), ['announcement' => $announcement->id, 'revision' => 1])->assertOk();
        $this->getJson(route('updates'))->assertJsonPath('badges.informasi', 1);
        $this->travel(3)->hours();
        $this->get(route('information'))->assertOk()->assertDontSee('Netflix dan Canva tersedia');
    }

    public function test_discounts_apply_to_catalog_cart_checkout_and_expire_on_server(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $variant = Variant::where('delivery', 'manual')->firstOrFail();
        $this->actingAs($owner)->get(route('staff', ['bagian' => 'diskon']))->assertOk();
        $this->post(route('staff.promotion.save'), [
            'title' => 'Promo dua jam', 'product_id' => $variant->product_id, 'variant_id' => $variant->id,
            'discount_percent' => 20, 'starts_at' => now()->subMinute()->format('Y-m-d H:i'), 'until' => 'duration', 'hours' => 2, 'active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame((int) round($variant->price * .8), $variant->fresh()->unitPrice());
        $this->get('/')->assertOk()->assertSee('Promo dua jam')->assertSee('data-countdown', false);
        $buyer = $this->buyer();
        Store::entry($buyer->id, 'topup', 100000, 'credit', 'test');
        $this->actingAs($buyer)->post(route('cart.add'), ['variant' => $variant->id, 'quantity' => 1])->assertRedirect();
        $discounted = $variant->fresh()->unitPrice();
        $this->get(route('cart'))->assertOk();
        $this->travel(3)->hours();
        $this->post(route('checkout'), ['method' => 'wallet', 'token' => (string) Str::uuid(), 'expected_total' => $discounted])->assertSessionHasErrors('store');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame($variant->price, $variant->fresh()->unitPrice());
        $this->get('/')->assertOk()->assertDontSee('Promo dua jam');
    }

    public function test_badges_track_cart_ready_orders_and_pending_staff_work(): void
    {
        $buyer = $this->buyer();
        $owner = User::factory()->create(['role' => 'owner']);
        $variant = Variant::where('delivery', 'manual')->firstOrFail();
        Store::entry($buyer->id, 'topup', 100000, 'credit', 'test');
        $order = app(Checkout::class)->create($buyer, [['variant' => $variant->id, 'quantity' => 1]], 'wallet', (string) Str::uuid());
        $this->actingAs($owner)->getJson(route('updates'))->assertJsonPath('badges.pesanan', 1);
        app(Checkout::class)->deliver($order->items()->firstOrFail(), 'test');
        $this->actingAs($buyer)->withSession(['cart' => [['variant' => $variant->id, 'quantity' => 3]]])->getJson(route('updates'))
            ->assertJsonPath('badges.cart', 3)->assertJsonPath('badges.orders', 1);
        $this->get(route('orders.show', $order))->assertOk();
        $this->getJson(route('updates'))->assertJsonPath('badges.orders', 0);
        $this->actingAs($owner)->getJson(route('updates'))->assertJsonPath('badges.pesanan', 0);
    }

    public function test_inline_announcements_are_atomic_and_new_owner_forms_render(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $variant = Variant::where('delivery', 'stock')->firstOrFail();
        $variant->product->update(['stock_mode' => 'profiles']);
        $data = ['variant_id' => $variant->id, 'credentials' => 'test-only-access', 'slots' => 1,
            'profiles' => [['label' => 'Profil 1', 'pin' => '1122']], 'announce' => 1,
            'announcement_title' => 'Stok baru tersedia', 'announcement_body' => 'Netflix dan Canva kembali tersedia.',
            'announcement_until' => 'duration', 'announcement_hours' => 24];
        $this->actingAs($owner)->post(route('staff.stock'), array_replace($data, ['slots' => 2]))->assertSessionHasErrors('store');
        $this->assertDatabaseCount('announcements', 0);
        $this->post(route('staff.stock'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('announcements', 1);
        $urls = [
            'owner-information' => route('staff', ['bagian' => 'informasi']),
            'owner-discount' => route('staff', ['bagian' => 'diskon']),
            'owner-profiles' => route('staff', ['bagian' => 'stok', 'app' => $variant->product->slug, 'varian' => $variant->id]),
            'owner-products' => route('staff', ['bagian' => 'katalog']),
        ];
        foreach ($urls as $name => $url) {
            $html = $this->get($url)->assertOk()->getContent();
            if ($directory = getenv('BIUBLE_AUDIT_DIR')) {
                if (! is_dir($directory)) mkdir($directory, 0777, true);
                file_put_contents($directory.'/'.$name.'.html', str_replace('http://localhost', 'http://127.0.0.1:8916', $html));
            }
        }
        $buyer = $this->buyer();
        $this->actingAs($buyer);
        $html = $this->get('/')->assertOk()->assertSee('Netflix dan Canva kembali tersedia.')->getContent();
        if ($directory = getenv('BIUBLE_AUDIT_DIR')) file_put_contents($directory.'/information-popup.html', str_replace('http://localhost', 'http://127.0.0.1:8916', $html));
    }
}
