<?php

namespace Tests\Feature;

use App\Models\SupportCase;
use App\Models\SupportFile;
use App\Models\User;
use App\Models\Variant;
use App\Models\WhatsappContact;
use App\Services\Checkout;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerSupportTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_proof_and_warranty_enforce_deadline_privacy_and_staff_review(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->create(['approval' => 'approved', 'phone' => '081234567890']);
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'owner']);
        $outsider = User::factory()->create();
        $variant = Variant::where('delivery', 'manual')->firstOrFail();
        $variant->update(['login_proof_required' => true, 'warranty_days' => 7]);
        Store::entry($buyer->id, 'topup', $variant->price * 4, 'test:support-balance', 'Test balance');
        $checkout = app(Checkout::class);
        $order = $checkout->create($buyer, [['variant' => $variant->id, 'quantity' => 2]], 'wallet', (string) Str::uuid());
        [$item, $lateItem] = $order->items()->orderBy('id')->get()->all();
        $variant->update(['login_proof_required' => false, 'warranty_days' => null]);
        $checkout->deliver($item, 'Demo');
        $checkout->deliver($lateItem, 'Demo');
        $this->assertSame(7, $item->warranty_days);

        $proofUrl = route('support.proof.upload', $item);
        $this->actingAs($buyer)->get(route('orders.show', $order))->assertOk()->assertSee('Unggah bukti login');
        $this->get(route('orders'))->assertOk()->assertSee('Belum diunggah')->assertSee('Batas unggah')
            ->assertSee(route('orders.show', $order).'#item-'.$item->id);
        $this->post($proofUrl, ['evidence' => UploadedFile::fake()->image('besar.png')->size(2049)])
            ->assertSessionHasErrors('evidence');
        $this->post($proofUrl, ['evidence' => UploadedFile::fake()->image('login.png', 2400, 1200)])
            ->assertRedirect(route('orders.show', $order));
        $proof = $item->loginProof()->firstOrFail();
        $firstUpload = $proof->created_at;
        $file = $proof->files()->sole();
        $this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring(Storage::disk('local')->get($file->path))[2]);
        $this->assertLessThanOrEqual(2097152, Storage::disk('local')->size($file->path));
        $this->actingAs($outsider)->get(route('support.file', $file))->assertForbidden();
        $this->actingAs($buyer)->delete(route('support.file.delete', $file))->assertForbidden();

        $this->travel(25)->hours();
        $this->get(route('orders'))->assertOk()->assertSee('Sudah dikirim · menunggu pemeriksaan')
            ->assertSee('Belum diunggah · tenggat lewat')->assertDontSee('Unggah bukti untuk item #'.$lateItem->id);
        $this->post(route('support.proof.upload', $lateItem), ['evidence' => UploadedFile::fake()->image('late.png')])
            ->assertSessionHasErrors('store');
        $this->post(route('support.store', 'garansi'), [
            'item' => $lateItem->id, 'subject' => 'Gagal login', 'body' => 'Akses berhenti', 'evidence' => UploadedFile::fake()->image('error.png'),
        ])->assertSessionHasErrors('store');
        $this->post(route('support.store', 'masalah'), [
            'item' => $lateItem->id, 'subject' => 'Gagal login', 'body' => 'Akses berhenti',
        ])->assertRedirect();
        $this->post(route('support.store', 'garansi'), [
            'item' => $item->id, 'subject' => 'Akses berhenti', 'body' => 'Ada error saat membuka akun', 'evidence' => UploadedFile::fake()->image('error.png'),
        ])->assertRedirect();
        $case = SupportCase::where('kind', 'garansi')->sole();
        $this->actingAs($outsider)->get(route('support.show', $case))->assertForbidden();
        $this->actingAs($admin)->get(route('support.show', $case))->assertOk()
            ->assertSee('Unduh bukti login')->assertSee('Unduh bukti masalah')->assertSee('https://wa.me/6281234567890');
        $this->get(route('support.file', $file))->assertOk()->assertDownload('bukti-'.$file->id.'.webp');
        $this->post(route('support.review', $case), ['status' => 'resolved', 'body' => 'Selesai'])->assertSessionHasErrors('status');
        $this->delete(route('support.file.delete', $file))->assertRedirect();
        $this->assertDatabaseMissing('support_files', ['id' => $file->id]);
        Storage::disk('local')->assertMissing($file->path);
        $this->assertSame('correction', $proof->fresh()->status);
        $this->actingAs($buyer)->get(route('orders'))->assertOk()->assertSee('Perlu diperbaiki')
            ->assertSee('Perbaiki bukti untuk item #'.$item->id);
        $this->actingAs($buyer)->post($proofUrl, ['evidence' => UploadedFile::fake()->image('correction.png')])->assertRedirect();
        $this->assertTrue($firstUpload->equalTo($proof->fresh()->created_at));
        $this->actingAs($admin)->post(route('support.proof.review', $item), ['status' => 'accepted'])->assertRedirect();
        $this->actingAs($owner)->post(route('support.review', $case), ['status' => 'resolved', 'body' => 'Akses dipulihkan'])->assertRedirect();
        $this->assertSame('resolved', $case->fresh()->status);
        $this->assertSame(2, SupportFile::count());
    }

    public function test_owner_manages_whatsapp_and_inactive_contacts_have_no_link(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $admin = User::factory()->create(['role' => 'admin']);
        $details = ['name' => 'Bantuan Owner', 'role' => 'owner', 'number' => '0812-3456-7890', 'active' => '1'];

        $this->actingAs($admin)->post(route('staff.whatsapp.save'), $details)->assertForbidden();
        $this->actingAs($owner)->get(route('staff', ['bagian' => 'pengaturan']))->assertOk()->assertSee('Tambah kontak WhatsApp');
        $this->post(route('staff.whatsapp.save'), $details)->assertRedirect();
        $contact = WhatsappContact::sole();
        $this->get(route('catalog'))->assertOk()->assertSee('https://wa.me/6281234567890');
        $this->post(route('staff.whatsapp.save', $contact), array_replace($details, ['active' => '0']))->assertRedirect();
        $this->get(route('catalog'))->assertOk()->assertSee('Bantuan Owner')->assertSee('aria-disabled="true"', false)
            ->assertDontSee('https://wa.me/6281234567890');
        $this->delete(route('staff.whatsapp.remove', $contact))->assertRedirect();
        $this->get(route('catalog'))->assertOk()->assertDontSee('Bantuan Owner');
    }
}
