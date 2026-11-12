<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleLoginController extends Controller
{
    public function redirect()
    {
        $this->requireConfiguration();

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        $this->requireConfiguration();

        if ($request->filled('error')) {
            return redirect()->route('login')->withErrors(['google' => 'Masuk dengan Google dibatalkan.']);
        }

        try {
            $identity = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return redirect()->route('login')->withErrors(['google' => 'Sesi Google berakhir. Coba masuk lagi.']);
        }

        $email = Str::lower((string) $identity->getEmail());
        $authoritative = Str::endsWith($email, '@gmail.com') || filled($identity->user['hd'] ?? null);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ($identity->user['email_verified'] ?? false) !== true
            || ! $authoritative) {
            return redirect()->route('login')->withErrors(['google' => 'Gunakan kode OTP untuk memverifikasi email ini.']);
        }

        $user = DB::transaction(function () use ($identity, $email) {
            $user = User::where('google_id', $identity->getId())->first() ?? User::firstOrCreate(['email' => $email], [
                'name' => Str::limit((string) ($identity->getName() ?: Str::before($email, '@')), 120, ''),
                'password' => Hash::make(Str::random(64)),
            ]);
            $user = \App\Services\Store::lockUser($user->id);
            abort_if($user->isArchived(), 403);
            $user->forceFill([
                'google_id' => $user->google_id ?: $identity->getId(),
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();

            return $user;
        }, 3);

        $request->session()->forget('otp');
        Auth::login($user);
        $request->session()->regenerate();

        if (in_array($user->role, ['owner', 'admin'], true)) {
            $request->session()->forget('url.intended');
            return redirect()->route('staff');
        }

        return redirect()->intended(route('catalog'));
    }

    private function requireConfiguration(): void
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 404);
    }
}


