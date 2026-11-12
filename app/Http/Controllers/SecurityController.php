<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\Store;

class SecurityController extends Controller
{
    public function show()
    {
        Store::owner();
        return view('security');
    }

    public function send(Request $request)
    {
        Store::owner();
        $key = 'reauth:'.$request->user()->id;
        Store::check(! RateLimiter::tooManyAttempts($key, 1), 'Tunggu satu menit sebelum meminta kode lagi.');
        Store::check(app()->isLocal() || ! in_array(config('mail.default'), ['log', 'array']),
            'Email produksi belum dikonfigurasi.');
        RateLimiter::hit($key, 60);
        $code = (string) random_int(100000, 999999);
        Mail::send('mail.otp', [
            'code' => $code,
            'purpose' => 'owner-verification',
        ], fn ($mail) => $mail->to($request->user()->email)->subject('Verifikasi tindakan owner Biuble'));
        $request->session()->put('reauth', [
            'hash' => Hash::make($code), 'expires' => now()->addMinutes(5)->timestamp, 'attempts' => 0,
        ]);
        return back()->with('status', 'Kode verifikasi dikirim ke email owner.');
    }

    public function verify(Request $request)
    {
        Store::owner();
        $code = $request->validate(['code' => 'required|digits:6'])['code'];
        $challenge = $request->session()->get('reauth');
        Store::check($challenge && $challenge['expires'] > now()->timestamp && $challenge['attempts'] < 5,
            'Kode berakhir. Minta kode baru.');
        $request->session()->increment('reauth.attempts');
        Store::check(Hash::check((string) $code, $challenge['hash']), 'Kode belum cocok.');
        $request->session()->forget('reauth');
        $request->session()->put('owner_verified_at', now()->timestamp);
        $section = $request->session()->pull('owner_verify_section');
        return redirect()->route('staff', $section ? ['bagian' => $section] : [])
            ->with('status', 'Verifikasi berhasil. Tindakan sensitif tersedia selama 15 menit.');
    }
}

