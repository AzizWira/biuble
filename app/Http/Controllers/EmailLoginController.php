<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class EmailLoginController extends Controller
{
    public function show()
    {
        return view('login');
    }

    public function send(Request $request)
    {
        $email = Str::lower($request->validate(['email' => 'required|email:rfc|max:254'])['email']);
        $key = 'otp-mail:'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withErrors(['email' => 'Tunggu satu menit sebelum meminta kode lagi.']);
        }

        RateLimiter::hit($key, 60);
        abort_unless(app()->isLocal() || ! in_array(config('mail.default'), ['log', 'array']), 503, 'Email belum dikonfigurasi.');
        $code = (string) random_int(100000, 999999);
        Mail::send('mail.otp', compact('code'), fn ($mail) => $mail->to($email)->subject('Kode masuk Biuble'));

        $request->session()->put('otp', [
            'email' => $email, 'hash' => Hash::make($code),
            'expires' => now()->addMinutes(5)->timestamp, 'attempts' => 0,
        ]);

        return redirect()->route('login')->with('status', 'Kode masuk sudah dikirim.');
    }

    public function verify(Request $request)
    {
        $code = $request->validate(['code' => 'required|digits:6'])['code'];
        $challenge = $request->session()->get('otp');
        if (! $challenge || !empty($challenge['demo']) || $challenge['expires'] <= now()->timestamp || $challenge['attempts'] >= 5) {
            $request->session()->forget('otp');

            return back()->withErrors(['email' => 'Minta kode baru untuk melanjutkan.']);
        }

        $request->session()->increment('otp.attempts');
        if (! Hash::check((string) $code, $challenge['hash'])) {
            return back()->withErrors(['code' => 'Kode belum cocok. Periksa lagi emailmu.']);
        }

        $request->session()->forget('otp');
        $request->session()->forget(['owner_verified_at', 'reauth', 'owner_verify_section']);
        $user = User::firstOrCreate(['email' => $challenge['email']], [
            'name' => Str::before($challenge['email'], '@'),
            'password' => Hash::make(Str::random(64)),
        ]);
        if ($user->isArchived()) {
            return redirect()->route('login')->withErrors(['email' => 'Akun ini sudah dihapus.']);
        }
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user);
        $request->session()->regenerate();

        if (in_array($user->role, ['owner', 'admin'], true)) {
            $request->session()->forget('url.intended');
            return redirect()->route('staff');
        }

        return redirect()->intended(route('catalog'));
    }

    public function reset(Request $request)
    {
        $request->session()->forget('otp');

        return redirect()->route('login');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('catalog');
    }
}


