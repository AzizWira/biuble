<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureRoleAccess;
use App\Models\{Announcement, AccountRemovalRequest, Category, Order, Payment, Product, Promotion, StockAccount, StockSlot, SupportCase, SupportFile, Topup, User, Variant, WhatsappContact, Withdrawal};
use App\Services\{NavigationBadges, Store};
use Database\Seeders\DemoScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash, Http, Mail, Route, Storage};
use Tests\TestCase;

class RoleAuthorizationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemo(): array
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Mail::fake();
        $this->seed(DemoScenarioSeeder::class);
        return [User::where('role', 'owner')->sole(), User::where('email', 'hrchyosazu@gmail.com')->sole(), User::where('email', 'biubleemail01@gmail.com')->sole()];
    }

    public function test_forbidden_role_routes_reject_direct_requests_across_all_modules(): void
    {
        [$owner, $admin, $buyer] = $this->seedDemo();
        $parameters = ['user'=>$buyer->id, 'order'=>Order::first()->id, 'item'=>Order::first()->items()->first()->id,
            'topup'=>Topup::first()->id, 'withdrawal'=>Withdrawal::first()->id, 'removal'=>AccountRemovalRequest::first()->id,
            'product'=>Product::first()->id, 'variant'=>Variant::first()->id, 'category'=>Category::first()->id,
            'account'=>StockAccount::first()->id, 'slot'=>StockSlot::first()->id, 'announcement'=>Announcement::first()->id,
            'promotion'=>Promotion::first()->id, 'contact'=>WhatsappContact::first()->id, 'case'=>SupportCase::first()->id,
            'file'=>SupportFile::first()->id, 'reference'=>Payment::first()->reference, 'line'=>0, 'kind'=>'masalah', 'id'=>Product::first()->id];
        $before = DB::table('store_audits')->count();
        foreach ([$owner, $admin, $buyer] as $actor) {
            $forbidden = match ($actor->role) {
                'owner' => [...EnsureRoleAccess::RESELLER, 'staff.account-removal-request'],
                'admin' => [...EnsureRoleAccess::OWNER, ...EnsureRoleAccess::RESELLER],
                'reseller' => [...EnsureRoleAccess::OWNER, ...EnsureRoleAccess::STAFF, 'staff.account-removal-request'],
            };
            $this->actingAs($actor);
            foreach ($forbidden as $name) {
                $route = Route::getRoutes()->getByName($name);
                $args = array_intersect_key($parameters, array_flip($route->parameterNames()));
                if ($name === 'staff.catalog.remove') $args['kind'] = 'product';
                $this->json($route->methods()[0], route($name, $args), [])->assertForbidden();
            }
        }
        $this->assertSame($before, DB::table('store_audits')->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_payment_controls_are_for_the_reseller_owner_only_including_after_promotion(): void
    {
        [$owner, $admin, $buyer] = $this->seedDemo();
        $order = Order::where('reference', 'DEMO-BAYAR-ATAU-GANTI-METODE')->sole();
        foreach ([$owner, $admin] as $staff) {
            $this->actingAs($staff)->get(route('orders.show', $order))->assertOk()
                ->assertDontSee('Lanjutkan pembayaran DOKU')->assertDontSee('Ubah metode pembayaran')->assertDontSee('Batalkan pesanan');
        }
        $this->actingAs($buyer)->get(route('orders.show', $order))->assertOk()->assertSee('Ubah metode pembayaran');
        $other = User::factory()->create(['role'=>'reseller', 'approval'=>'approved']);
        $this->actingAs($other)->get(route('orders.show', $order))->assertForbidden();
        $this->postJson(route('orders.cancel', $order))->assertForbidden();
        $buyer->forceFill(['role'=>'admin'])->save();
        $this->actingAs($buyer)->get(route('orders.show', $order))->assertOk()->assertDontSee('Ubah metode pembayaran');
        $this->postJson(route('orders.method', $order), [])->assertForbidden();
        $this->postJson(route('payment', $order->payment->reference))->assertForbidden();
    }

    public function test_badges_follow_role_work_and_support_reads_do_not_clear_other_notifications(): void
    {
        [$owner, $admin, $buyer] = $this->seedDemo();
        $this->assertGreaterThan(NavigationBadges::for($admin)['pesanan'], NavigationBadges::for($owner)['pesanan']);
        $case = SupportCase::where('status','waiting_customer')->firstOrFail();
        $this->assertNotContains($case->status, NavigationBadges::caseActionStatuses($owner));
        $before = NavigationBadges::for($owner)['masalah'];
        $this->actingAs($buyer)->post(route('support.reply', $case), ['body'=>'Sudah ditambahkan informasi perangkat.'])->assertRedirect();
        $this->assertSame('reviewing', $case->fresh()->status);
        $this->assertSame($before+1, NavigationBadges::for($owner)['masalah']);
        Store::notify($buyer->id, 'Update tiket khusus', $case->order_id, $case->id);
        Store::notify($buyer->id, 'Update akses pesanan', $case->order_id);
        $this->get(route('orders.show', $case->order_id))->assertOk();
        $this->assertDatabaseHas('store_notifications',['message'=>'Update tiket khusus','read_at'=>null]);
        $this->get(route('support.show', $case))->assertOk();
        $this->assertNotNull(DB::table('store_notifications')->where('message','Update tiket khusus')->value('read_at'));
        $this->assertSame('reviewing', $case->fresh()->status);
        $waitingOwner = SupportCase::where('status','waiting_owner')->firstOrFail();
        $this->actingAs($admin)->postJson(route('support.review', $waitingOwner), ['status'=>'resolved','body'=>'Coba tutup garansi'])->assertUnprocessable();
        $this->actingAs($buyer)->postJson(route('support.review', $waitingOwner), ['status'=>'resolved','body'=>'Tutup'])->assertForbidden();
        $this->actingAs($owner)->post(route('support.review', $waitingOwner), ['status'=>'resolved','body'=>'Garansi ditangani, akses sudah dipulihkan.'])->assertRedirect();
        $this->assertSame('resolved', $waitingOwner->fresh()->status);
        $this->actingAs($admin)->post(route('support.review', $case), ['status'=>'resolved','body'=>'Masalah sudah diselesaikan.'])->assertRedirect();
        $this->assertSame('resolved', $case->fresh()->status);
    }

    public function test_admin_can_change_price_but_cannot_mutate_variant_or_another_authors_announcement(): void
    {
        [$owner, $admin] = $this->seedDemo();
        $variant = Variant::firstOrFail();
        $this->actingAs($admin)->post(route('staff.variant', $variant), ['price'=>12345])->assertRedirect();
        $this->assertSame(12345, $variant->fresh()->price);
        $this->postJson(route('staff.variant', $variant), ['price'=>1,'delivery'=>'manual'])->assertForbidden();
        $announcement = Announcement::where('user_id', $owner->id)->firstOrFail();
        $this->postJson(route('staff.information.save', $announcement), [])->assertForbidden();
        $this->deleteJson(route('staff.information.remove', $announcement))->assertForbidden();
    }

    public function test_login_resets_elevated_session_and_new_accounts_cannot_choose_their_role(): void
    {
        $this->seedDemo();
        $this->withSession(['owner_verified_at'=>now()->timestamp,'reauth'=>['hash'=>'old'], 'otp'=>[
            'email'=>'fresh-reseller@example.test','hash'=>Hash::make('123456'),'expires'=>now()->addMinutes(5)->timestamp,'attempts'=>0,
        ]])->post(route('otp.verify'), ['code'=>'123456','role'=>'owner','approval'=>'approved'])
            ->assertRedirect()->assertSessionMissing('owner_verified_at')->assertSessionMissing('reauth');
        $user = User::where('email','fresh-reseller@example.test')->sole();
        $this->assertSame('reseller', $user->role);
        $this->assertSame('draft', $user->approval);
        $this->postJson(route('checkout'), [])->assertForbidden();
    }

    public function test_expired_otp_and_five_wrong_attempts_cannot_authenticate(): void
    {
        $this->seedDemo();
        $challenge = ['email'=>'btrihapsari@gmail.com','hash'=>Hash::make('123456'),'expires'=>now()->subSecond()->timestamp,'attempts'=>0];
        $this->withSession(['otp'=>$challenge])->post(route('otp.verify'), ['code'=>'123456'])->assertSessionHasErrors();
        $this->assertGuest();
        $challenge['expires'] = now()->addMinutes(5)->timestamp;
        $this->withSession(['otp'=>$challenge]);
        for ($i=0; $i<5; $i++) $this->post(route('otp.verify'), ['code'=>'111111'])->assertSessionHasErrors();
        $this->post(route('otp.verify'), ['code'=>'123456'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_google_cannot_use_an_unverified_email_to_enter_an_existing_owner_account(): void
    {
        $this->seedDemo();
        config(['services.google.client_id'=>'test-client','services.google.client_secret'=>'test-secret']);
        $identity = (new \Laravel\Socialite\Two\User())->map(['id'=>'untrusted-google-id','email'=>'btrihapsari@gmail.com','name'=>'Not verified']);
        $identity->setRaw(['email_verified'=>false]);
        $provider = \Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($identity);
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
        $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull(User::where('role','owner')->sole()->google_id);
    }
}
