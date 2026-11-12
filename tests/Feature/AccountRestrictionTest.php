<?php

namespace Tests\Feature;

use App\Models\{User, Variant};
use Database\Seeders\LocalAuditSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash};
use Tests\TestCase;

class AccountRestrictionTest extends TestCase
{
    use RefreshDatabase;
    protected $seed = true;

    public function test_restricted_accounts_can_login_read_and_logout_but_cannot_write(): void
    {
        $this->seed(LocalAuditSeeder::class);
        foreach (['suspended','inactive'] as $status) {
            foreach (['reseller'=>'natashasheiyla@gmail.com','admin'=>'auraiftitahh@gmail.com'] as $role=>$email) {
                $user=User::where('email',$email)->firstOrFail();
                $user->forceFill(['account_status'=>$status,'account_status_reason'=>'Pembatasan untuk pengujian'])->save();
                $this->withSession(['otp'=>['email'=>$email,'hash'=>Hash::make('123456'),
                    'expires'=>now()->addMinutes(5)->timestamp,'attempts'=>0]])
                    ->post('/masuk/verifikasi',['code'=>'123456'])->assertRedirect($role === 'admin' ? '/toko' : '/');
                $this->assertAuthenticatedAs($user);
                $this->capture($role.'-'.$status.'-account', $this->get('/akun')->assertOk()->assertSee('Hanya lihat')->assertSee('Pembatasan untuk pengujian')->getContent());
                $this->get('/')->assertOk();
                $this->get('/produk/canva')->assertOk();
                if ($role === 'reseller') {
                    $this->capture('reseller-'.$status.'-wallet', $this->get('/saldo')->assertOk()->getContent());
                    $this->get('/pesanan')->assertOk();
                    $this->get('/keranjang')->assertOk();
                    $this->post('/keranjang',[])->assertForbidden();
                    $this->post('/saldo/topup',[])->assertForbidden();
                    $this->post('/saldo/tarik',[])->assertForbidden();
                    $this->post('/checkout',[])->assertForbidden();
                    $this->delete('/keranjang/0')->assertForbidden();
                } else {
                    $this->get('/toko')->assertOk()->assertSee('Hanya lihat');
                    $this->capture('admin-'.$status.'-catalog', $this->get('/toko?bagian=katalog')->assertOk()->getContent());
                    $this->post('/toko/stok',[])->assertForbidden();
                    $variant=Variant::first();
                    $price=$variant->price;
                    $this->post('/toko/varian/'.$variant->id,['price'=>1])->assertForbidden();
                    $this->assertSame($price,$variant->fresh()->price);
                }
                $this->post('/keluar')->assertRedirect('/');
                $this->assertGuest();
            }
        }
    }

    public function test_only_verified_owner_can_change_admin_or_reseller_status_and_restore_it(): void
    {
        $this->seed(LocalAuditSeeder::class);
        $owner=User::where('role','owner')->firstOrFail();
        $admin=User::where('email','auraiftitahh@gmail.com')->firstOrFail();
        $buyer=User::where('email','natashasheiyla@gmail.com')->firstOrFail();
        $url='/toko/akun/'.$buyer->id.'/status';
        $this->actingAs($admin)->post($url,['account_status'=>'suspended'])->assertForbidden();
        $this->actingAs($buyer)->post($url,['account_status'=>'suspended'])->assertForbidden();
        $this->actingAs($owner)->post($url,['account_status'=>'suspended'])->assertRedirect('/keamanan');
        $this->assertSame('active',$buyer->fresh()->account_status);
        $this->withSession(['owner_verified_at'=>now()->timestamp])->from('/toko?bagian=pengajuan')
            ->post($url,['account_status'=>'suspended','account_status_reason'=>'Perlu pemeriksaan'])->assertRedirect('/toko?bagian=pengajuan');
        $this->assertSame('suspended',$buyer->fresh()->account_status);
        $this->assertSame('approved',$buyer->fresh()->approval);
        $this->post('/toko/akun/'.$owner->id.'/status',['account_status'=>'inactive'])->assertForbidden();
        $this->assertSame('active',$owner->fresh()->account_status);
        $this->post($url,['account_status'=>'active'])->assertRedirect();
        $this->assertSame('active',$buyer->fresh()->account_status);
        $this->assertNull($buyer->fresh()->account_status_reason);
        $this->post('/toko/akun/'.$admin->id.'/status',['account_status'=>'inactive'])->assertRedirect();
        $this->assertSame('inactive',$admin->fresh()->account_status);
        $this->assertDatabaseHas('store_audits',['action'=>'account_status_changed']);
        $this->capture('owner-account-resellers', $this->get('/toko?bagian=pengajuan')->assertOk()->assertSee('Daftar akun reseller')->getContent());
        $this->capture('owner-account-admins', $this->get('/toko?bagian=admin')->assertOk()->getContent());
    }

    public function test_an_existing_session_loses_write_access_immediately_after_restriction(): void
    {
        $this->seed(LocalAuditSeeder::class);
        $buyer=User::where('email','natashasheiyla@gmail.com')->firstOrFail();
        $this->actingAs($buyer);
        DB::table('users')->where('id',$buyer->id)->update(['account_status'=>'suspended']);
        $before=DB::table('topups')->count();
        $this->post('/saldo/topup',[])->assertForbidden();
        $this->assertSame($before,DB::table('topups')->count());
    }
    public function test_restriction_does_not_discard_confirmed_payments_already_in_progress(): void
    {
        $this->seed(LocalAuditSeeder::class);
        $buyer=User::where('email','natashasheiyla@gmail.com')->firstOrFail();
        $before=\App\Services\Store::balances($buyer->id)['topup'];
        $topup=app(\App\Services\Wallet::class)->topup($buyer,['amount'=>5000,'method'=>'QRIS'],(string)\Illuminate\Support\Str::uuid(),null);
        $buyer->forceFill(['account_status'=>'suspended'])->save();
        config(['doku.enabled'=>true,'doku.client_id'=>'audit-client','doku.secret'=>'audit-secret']);
        $body=json_encode(['order'=>['invoice_number'=>$topup->reference,'amount'=>5000],'transaction'=>['status'=>'SUCCESS']]);
        $timestamp=now()->utc()->format('Y-m-d\TH:i:s\Z');
        $headers=['Content-Type'=>'application/json','Client-Id'=>'audit-client','Request-Id'=>'readonly-callback',
            'Request-Timestamp'=>$timestamp,'Signature'=>app(\App\Services\Doku::class)->signature('readonly-callback',$timestamp,'/doku/notification',$body)];
        foreach (range(1,2) as $attempt) $this->call('POST','/doku/notification',[],[],[],$this->transformHeadersToServerVars($headers),$body)->assertOk();
        $this->assertSame($before+5000,\App\Services\Store::balances($buyer->id)['topup']);
        $this->assertSame('paid',$topup->fresh()->status);
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
