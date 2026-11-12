<?php

namespace App\Http\Middleware;

use App\Support\DemoOtp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpireDemoLogin
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->session()->has('demo_otp_login')
            && ! DemoOtp::available($request)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
