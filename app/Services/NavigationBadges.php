<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Order;
use App\Models\User;
use App\Models\SupportCase;
use Illuminate\Support\Facades\DB;

class NavigationBadges
{
    public static function actionItems($query, User $user)
    {
        return $query->whereIn('status', ['waiting', 'review', 'refund_requested'])
            ->when($user->role === 'admin', fn ($q) => $q->where('delivery', 'invite')->whereIn('status', ['waiting', 'review']));
    }

    public static function orderNeedsAttention(Order $order, User $user): bool
    {
        return $order->paid_at && $order->items->contains(fn ($item) => in_array($item->status, ['waiting', 'review', 'refund_requested'], true)
            && ($user->role === 'owner' || ($item->delivery === 'invite' && $item->status !== 'refund_requested')));
    }

    public static function caseActionStatuses(User $user): array
    {
        return $user->role === 'owner' ? ['submitted', 'reviewing', 'waiting_owner'] : ['submitted', 'reviewing'];
    }

    public static function for(User $user): array
    {
        $counts = ['informasi' => Announcement::live()->unreadFor($user->id)->count()];
        if ($user->role === 'reseller') {
            $counts['cart'] = collect(session('cart', []))->sum('quantity');
            $counts['orders'] = DB::table('store_notifications')->where('user_id', $user->id)
                ->whereNotNull('order_id')->whereNull('support_case_id')->whereNull('read_at')->distinct()->count('order_id');
            foreach (['garansi', 'masalah'] as $kind) {
                $counts[$kind] = DB::table('store_notifications')->where('user_id', $user->id)->whereNull('read_at')
                    ->whereIn('support_case_id', SupportCase::where('user_id', $user->id)->where('kind', $kind)->select('id'))
                    ->distinct()->count('support_case_id');
            }
            return $counts;
        }
        $counts['pesanan'] = Order::whereNotNull('paid_at')->whereHas('items', fn ($q) => self::actionItems($q, $user))->count();
        $counts['topup'] = DB::table('topups')->where('method', 'manual')->where('status', 'pending')->count();
        $counts['bukti-login'] = \App\Models\LoginProof::where('status', 'submitted')->count();
        $counts['garansi'] = SupportCase::where('kind', 'garansi')->whereIn('status', self::caseActionStatuses($user))->count();
        $counts['masalah'] = SupportCase::where('kind', 'masalah')->whereIn('status', self::caseActionStatuses($user))->count();
        if ($user->role === 'owner') {
            $counts['pengajuan'] = User::where('role', 'reseller')->where('approval', 'pending')->where('account_status', '!=', 'deleted')->count();
            $counts['penghapusan'] = DB::table('account_removal_requests')->where('status', 'pending')->count();
            $counts['penarikan'] = DB::table('withdrawals')->where('status', 'pending')->count();
            $counts['aktivitas'] = DB::table('store_audits')->where('id', '>', (int) $user->activity_read_id)->count();
        }
        return $counts;
    }
}
