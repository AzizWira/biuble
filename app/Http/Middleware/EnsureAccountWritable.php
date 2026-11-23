<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAccountWritable
{
    public function handle(Request $request, Closure $next)
    {
        $writing = ! $request->isMethodSafe() && ! $request->routeIs('logout');
        $user = $request->user()->refresh();
        if ($user->isArchived()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Akun ini sudah dihapus.']);
        }
        if ($writing) {
            \App\Services\Store::writable($user);
        }
        return $next($request);
    }
}
