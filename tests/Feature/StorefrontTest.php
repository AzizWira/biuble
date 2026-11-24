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
    public function test_demo_login_is_removed_and_legacy_demo_codes_cannot_authenticate(): void
    {
        $this->get('/masuk/demo')->assertNotFound();
        $this->post('/masuk/demo', ['demo_key'=>'legacy'])->assertNotFound();
        $this->withSession(['otp'=>[
            'email'=>'owner@example.test','hash'=>Hash::make('123456'),'expires'=>now()->addMinutes(5)->timestamp,'attempts'=>0,'demo'=>true,
        ]])->post('/masuk/verifikasi', ['code'=>'123456'])->assertSessionHasErrors('email')->assertSessionMissing('otp');
        $this->assertGuest();
        $this->get('/masuk')->assertDontSee('OTP demo');
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

