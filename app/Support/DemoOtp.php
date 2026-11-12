<?php

namespace App\Support;

use Illuminate\Http\Request;

class DemoOtp
{
    public static function available(Request $request): bool
    {
        $key = (string) config('app.demo_otp_key');
        if ($key === '') {
            return false;
        }
        if (app()->isLocal()) {
            return true;
        }

        return strlen($key) >= 32
            && ! config('doku.production')
            && $request->isSecure()
            && $request->getHost() === config('app.demo_otp_host')
            && filter_var(config('app.demo_otp_owner'), FILTER_VALIDATE_EMAIL) !== false
            && now()->timestamp < (int) config('app.demo_otp_expires_at');
    }

    public static function ownerEmail(): ?string
    {
        return app()->isLocal() ? null : config('app.demo_otp_owner');
    }
}
