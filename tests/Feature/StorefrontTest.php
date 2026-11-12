<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;
    protected $seed = true;

    public function test_public_catalog_lists_products_and_variant_prices(): void
    {
        $this->get('/')->assertOk()->assertSee('Netflix')->assertSee('ChatGPT');
        $this->get('/produk/canva')->assertOk()->assertSee('Rp500')->assertSee('Designer');
        $this->get('/produk/unknown')->assertNotFound();
        $this->get('/masuk')->assertOk()->assertSee('Kirim kode masuk');
    }

    public function test_email_code_is_consumed_when_login_succeeds(): void
    {
        $this->withSession(['otp' => [
            'email' => 'reseller@example.test',
            'hash' => Hash::make('123456'),
            'expires' => now()->addMinutes(5)->timestamp,
            'attempts' => 0,
        ]])->post('/masuk/verifikasi', ['code' => '123456'])
            ->assertRedirect('/')
            ->assertSessionMissing('otp');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'reseller@example.test']);
    }
    public function test_demo_otp_requires_key_and_session_and_expires_when_disabled(): void
    {
        $owner = \App\Models\User::factory()->unverified()->create();
        $owner->forceFill(['role' => 'owner', 'approval' => 'approved'])->save();
        $this->app->instance('env', 'local');
        config(['app.demo_otp_key' => 'test-demo-key']);

        \Illuminate\Support\Facades\Route::middleware(['web', 'guest'])->group(function () {
            \Illuminate\Support\Facades\Route::get('/masuk/demo', [\App\Http\Controllers\EmailLoginController::class, 'showDemo'])->name('otp.demo');
            \Illuminate\Support\Facades\Route::post('/masuk/demo', [\App\Http\Controllers\EmailLoginController::class, 'issueDemo'])->name('otp.demo.issue');
        });
        app('router')->getRoutes()->refreshNameLookups();

        $token = 'demo-test-csrf';
        $this->withSession(['_token' => $token])->from('/masuk/demo')
            ->post('/masuk/demo', ['_token' => $token, 'demo_key' => 'wrong-key'])
            ->assertSessionHasErrors('demo_key')->assertSessionMissing('otp');
        $this->post('/masuk/demo', ['_token' => $token, 'demo_key' => 'test-demo-key'])
            ->assertRedirect('/masuk/demo')->assertSessionHas('demo_code');

        $code = session('demo_code');
        $this->post('/masuk/verifikasi', ['_token' => $token, 'code' => $code])
            ->assertRedirect('/toko')->assertSessionMissing('otp');
        $this->assertAuthenticatedAs($owner);
        $this->assertNull($owner->fresh()->email_verified_at);

        config(['app.demo_otp_key' => null]);
        $this->get('/toko')->assertRedirect('/masuk');
        $this->assertGuest();
    }

    public function test_staff_login_and_navigation_lead_to_store_management(): void
    {
        foreach (['owner', 'admin'] as $role) {
            $user = \App\Models\User::factory()->create();
            $user->forceFill(['role' => $role, 'approval' => 'approved'])->save();
            $this->withSession(['url.intended' => url('/keranjang'), 'otp' => [
                'email' => $user->email, 'hash' => Hash::make('123456'),
                'expires' => now()->addMinutes(5)->timestamp, 'attempts' => 0,
            ]])->post('/masuk/verifikasi', ['code' => '123456'])
                ->assertRedirect('/toko')->assertSessionMissing('url.intended');
            $this->get('/toko')->assertOk()->assertSee('Kelola Biuble')
                ->assertDontSee('href="'.route('cart').'"', false)
                ->assertDontSee('href="'.route('wallet').'"', false);
            foreach (['/keranjang', '/saldo', '/pesanan'] as $path) {
                $this->get($path)->assertRedirect('/toko');
            }
            $this->post('/checkout', [])->assertForbidden();
            $this->post('/keluar');
        }
    }
}

