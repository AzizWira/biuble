<?php

namespace Tests\Feature;

use App\Models\AccountRemovalRequest;
use App\Models\User;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;
    protected $seed = true;

    public function test_only_owner_can_change_roles_and_existing_emails_cannot_create_another_role(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $admin = User::factory()->create(['role' => 'admin']);
        $reseller = User::factory()->create(['email' => 'reseller@example.test', 'approval' => 'approved']);
        $roleUrl = route('staff.account-role', $reseller);

        $this->actingAs($admin)->post($roleUrl, ['role' => 'admin'])->assertForbidden();
        $this->post(route('reseller.apply'), ['name' => 'Admin', 'phone' => '081234567890'])->assertForbidden();
        $this->actingAs($owner)->post($roleUrl, ['role' => 'admin'])->assertRedirect(route('security'));
        $this->withSession(['owner_verified_at' => now()->timestamp])
            ->post(route('staff.admin'), ['name' => 'Duplikat', 'email' => 'RESELLER@example.test'])->assertSessionHasErrors('email');
        $this->assertSame('reseller', $reseller->fresh()->role);
        $this->post($roleUrl, ['role' => 'admin'])->assertRedirect(route('staff', ['bagian' => 'admin']));
        $this->assertSame('admin', $reseller->fresh()->role);
        $this->post($roleUrl, ['role' => 'reseller'])->assertRedirect(route('staff', ['bagian' => 'pengajuan']));
        $this->assertSame('reseller', $reseller->fresh()->role);
        $this->assertSame('draft', $reseller->fresh()->approval);
        $this->post(route('staff.account-remove', $admin))->assertRedirect();
        $this->assertTrue($admin->fresh()->isArchived());
    }

    public function test_admin_removal_needs_owner_otp_and_preserves_history_and_email_can_be_reused(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $admin = User::factory()->create(['role' => 'admin']);
        $reseller = User::factory()->create(['approval' => 'approved']);
        $email = $reseller->email;
        $queue = route('staff', ['bagian' => 'penghapusan']);
        $submit = route('staff.account-removal-request', $reseller);

        $this->actingAs($admin)->get(route('staff', ['bagian' => 'pengajuan']))->assertOk()->assertSee('Ajukan hapus reseller');
        $this->get($queue)->assertNotFound();
        $this->post(route('staff.account-remove', $reseller))->assertForbidden();
        $this->post($submit, ['reason' => 'Akun reseller duplikat'])->assertRedirect();
        $this->post($submit, ['reason' => 'Pengajuan ulang'])->assertSessionHasErrors('store');
        $removal = AccountRemovalRequest::sole();
        $review = route('staff.account-removal-review', $removal);
        $this->post($review, ['decision' => 'approved'])->assertForbidden();

        $this->actingAs($owner)->get($queue)->assertOk()->assertSee('Akun reseller duplikat')->assertSee($admin->name);
        $this->post($review, ['decision' => 'approved'])->assertRedirect(route('security'));
        $this->assertFalse($reseller->fresh()->isArchived());
        $this->withSession(['reauth' => ['hash' => Hash::make('123456'), 'expires' => now()->addMinutes(5)->timestamp, 'attempts' => 0]])
            ->post(route('security.verify'), ['code' => '123456'])->assertRedirect($queue);
        Store::entry($reseller->id, 'topup', 5000, 'test:account-balance', 'Saldo awal');
        $this->from($queue)->post($review, ['decision' => 'approved'])->assertSessionHasErrors('store');
        $this->assertSame('pending', $removal->fresh()->status);
        Store::entry($reseller->id, 'topup', -5000, 'test:account-settled', 'Saldo diselesaikan');
        $this->post($review, ['decision' => 'approved'])->assertRedirect($queue);
        $this->assertSame('approved', $removal->fresh()->status);
        $this->assertDatabaseHas('wallet_entries', ['user_id' => $reseller->id, 'key' => 'test:account-balance']);
        $this->actingAs($reseller->fresh())->get(route('account'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($owner)->withSession(['owner_verified_at' => now()->timestamp])
            ->post(route('staff.admin'), ['name' => 'Admin baru', 'email' => $email])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => $email, 'role' => 'admin', 'account_status' => 'active']);
        $this->assertDatabaseHas('users', ['id' => $reseller->id, 'archived_email' => $email, 'account_status' => 'deleted']);
    }
}
