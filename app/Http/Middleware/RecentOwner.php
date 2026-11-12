<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RecentOwner
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->role === 'owner', 403);
        if ((int) $request->session()->get('owner_verified_at', 0) < now()->subMinutes(15)->timestamp) {
            if ($request->routeIs('staff.account-removal-review')) {
                $request->session()->put('owner_verify_section', 'penghapusan');
            } elseif ($request->routeIs('staff.account-role', 'staff.account-remove')) {
                $request->session()->put('owner_verify_section', $request->route('user')->role === 'admin' ? 'admin' : 'pengajuan');
            }
            return redirect()->route('security')->with('status', 'Verifikasi ulang sebelum tindakan keuangan atau pengaturan owner.');
        }
        return $next($request);
    }
}

