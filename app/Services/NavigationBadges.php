<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NavigationBadges
{
    public static function for(User $user): array
    {
        $counts = ['informasi' => Announcement::live()->unreadFor($user->id)->count()];
        if ($user->role === 'reseller') {
            $counts['cart'] = collect(session('cart', []))->sum('quantity');
            $counts['orders'] = DB::table('store_notifications')->where('user_id', $user->id)
                ->whereNotNull('order_id')->whereNull('read_at')->distinct()->count('order_id');
            return $counts;
        }
        $counts['pesanan'] = Order::whereNotNull('paid_at')->whereHas('items', fn ($q) => $q->whereIn('status', ['waiting', 'review', 'refund_requested']))->count();
        $counts['topup'] = DB::table('topups')->where('method', 'manual')->where('status', 'pending')->count();
        $counts['garansi'] = DB::table('support_cases')->where('kind', 'garansi')->whereNotIn('status', ['resolved', 'rejected'])->count();
        $counts['masalah'] = DB::table('support_cases')->where('kind', 'masalah')->whereNotIn('status', ['resolved', 'rejected'])->count();
        if ($user->role === 'owner') {
            $counts['pengajuan'] = User::where('role', 'reseller')->where('approval', 'pending')->where('account_status', '!=', 'deleted')->count();
            $counts['penghapusan'] = DB::table('account_removal_requests')->where('status', 'pending')->count();
            $counts['penarikan'] = DB::table('withdrawals')->where('status', 'pending')->count();
            $counts['aktivitas'] = DB::table('store_audits')->where('id', '>', (int) $user->activity_read_id)->count();
        }
        return $counts;
    }
}
