<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRoleAccess
{
    // Route-level permissions supplement resource ownership and business checks.
    public const OWNER = [
        'security', 'security.send', 'security.verify', 'staff.applicant', 'staff.withdrawal',
        'staff.refund', 'staff.gateway-refund', 'staff.category', 'staff.account-status',
        'staff.admin', 'staff.account-role', 'staff.account-remove', 'staff.account-removal-review',
        'staff.product', 'staff.settings', 'staff.promotion.save', 'staff.promotion.remove',
        'staff.catalog.remove', 'staff.whatsapp.save', 'staff.whatsapp.remove',
    ];
    public const STAFF = [
        'staff', 'staff.topup', 'staff.deliver', 'staff.variant', 'staff.stock', 'staff.stock-access',
        'staff.recycle', 'staff.information.save', 'staff.information.remove',
        'support.review', 'support.proof.review', 'support.file.delete',
    ];
    public const RESELLER = [
        'reseller.apply', 'cart.add', 'cart.remove', 'checkout', 'orders.cancel', 'orders.method',
        'item.cancel', 'topup', 'withdraw', 'payment', 'payment.check', 'payment.return',
        'support.create', 'support.store', 'support.proof.upload',
    ];

    public function handle(Request $request, Closure $next)
    {
        $route = $request->route()->getName();
        $roles = match (true) {
            in_array($route, self::OWNER, true) => ['owner'],
            in_array($route, self::STAFF, true) => ['owner', 'admin'],
            in_array($route, self::RESELLER, true) => ['reseller'],
            $route === 'staff.account-removal-request' => ['admin'],
            default => $request->isMethodSafe() || in_array($route, ['logout', 'support.reply', 'information.read'], true)
                ? ['owner', 'admin', 'reseller'] : [],
        };
        abort_unless(in_array($request->user()->role, $roles, true), 403, 'Tindakan ini tidak tersedia untuk role akunmu.');

        return $next($request);
    }
}
