<?php

namespace App\Http\Controllers;

use App\Models\AccountRemovalRequest;
use App\Models\Order;
use App\Models\Topup;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountManagementController extends Controller
{
    public function changeRole(Request $request, User $user)
    {
        Store::owner();
        $role = $request->validate(['role' => 'required|in:admin,reseller'])['role'];
        $approval = DB::transaction(function () use ($user, $role) {
            $target = Store::lockUser($user->id);
            $this->requireManagedAccount($target);
            Store::check($target->role !== $role, 'Role akun sudah sesuai.');
            $approval = $role === 'reseller' && ! $target->phone ? 'draft' : 'approved';
            $target->forceFill(['role' => $role, 'approval' => $approval])->save();
            $this->closePending($target, 'cancelled');
            Store::audit('account_role_changed', $target->id.' · '.($role === 'admin' ? 'Admin' : 'Reseller'));

            return $approval;
        }, 3);

        return redirect()->route('staff', ['bagian' => $role === 'admin' ? 'admin' : 'pengajuan'])
            ->with('status', match (true) {
                $role === 'admin' => 'Akun sudah menjadi admin.',
                $approval === 'draft' => 'Akun sudah menjadi reseller. Pemilik akun perlu mengisi nomor WhatsApp dan mengajukan akses belanja.',
                default => 'Akun sudah menjadi reseller.',
            });
    }

    public function remove(User $user)
    {
        Store::owner();
        DB::transaction(function () use ($user) {
            $target = Store::lockUser($user->id);
            $this->archive($target);
            $this->closePending($target, 'approved');
        }, 3);

        return back()->with('status', 'Akun dihapus dari daftar. Riwayatnya tetap tersimpan.');
    }

    public function requestRemoval(Request $request, User $user)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['reason' => 'required|string|max:255']);
        DB::transaction(function () use ($request, $user, $data) {
            $admin = Store::lockUser($request->user()->id);
            abort_unless($admin->role === 'admin', 403);
            Store::writable($admin);
            $target = Store::lockUser($user->id);
            $this->requireManagedAccount($target);
            abort_unless($target->role === 'reseller', 403);
            Store::check(! $this->pending($target)->exists(), 'Pengajuan hapus akun ini masih menunggu keputusan owner.');
            AccountRemovalRequest::create($data + ['user_id' => $target->id, 'requested_by' => $request->user()->id]);
            Store::audit('account_removal_requested', (string) $target->id);
        }, 3);

        return back()->with('status', 'Pengajuan hapus reseller dikirim ke owner.');
    }

    public function review(Request $request, AccountRemovalRequest $removal)
    {
        Store::owner();
        $decision = $request->validate(['decision' => 'required|in:approved,rejected'])['decision'];
        DB::transaction(function () use ($removal, $decision) {
            $target = Store::lockUser($removal->user_id);
            $removal = AccountRemovalRequest::whereKey($removal->id)->lockForUpdate()->firstOrFail();
            Store::check($removal->status === 'pending' && $target->role === 'reseller' && ! $target->isArchived(),
                'Pengajuan ini sudah selesai atau role akun sudah berubah.');
            if ($decision === 'approved') {
                $this->archive($target);
            }
            $removal->update(['status' => $decision, 'reviewed_by' => auth()->id()]);
            Store::audit('account_removal_'.$decision, (string) $target->id);
        }, 3);

        return back()->with('status', $decision === 'approved' ? 'Penghapusan reseller disetujui.' : 'Pengajuan penghapusan ditolak.');
    }

    private function requireManagedAccount(User $user): void
    {
        abort_unless(in_array($user->role, ['reseller', 'admin'], true) && ! $user->isArchived(), 403);
    }

    private function pending(User $user)
    {
        return AccountRemovalRequest::where('user_id', $user->id)->where('status', 'pending');
    }

    private function closePending(User $user, string $status): void
    {
        $this->pending($user)->update(['status' => $status, 'reviewed_by' => auth()->id()]);
    }

    private function archive(User $user): void
    {
        $this->requireManagedAccount($user);
        Store::check(! array_filter(Store::balances($user->id))
            && ! Topup::where('user_id', $user->id)->where('status', 'pending')->exists()
            && ! Withdrawal::where('user_id', $user->id)->where('status', 'pending')->exists()
            && ! Order::where('user_id', $user->id)->where(fn ($orders) => $orders->whereIn('status', ['pending', 'review'])
                ->orWhereHas('items', fn ($items) => $items->whereIn('status', ['waiting', 'review', 'refund_requested'])
                    ->orWhere(fn ($active) => $active->where('status', 'delivered')->where('ends_at', '>', now()))))->exists(),
            'Selesaikan saldo, transaksi tertunda, dan pesanan yang aksesnya masih aktif sebelum menghapus akun.');

        $user->forceFill([
            'archived_email' => $user->email,
            'email' => 'archived-'.Str::uuid().'@example.invalid',
            'google_id' => null,
            'phone' => null,
            'remember_token' => null,
            'account_status' => 'deleted',
        ])->save();
        Store::audit('account_removed', (string) $user->id);
    }
}
