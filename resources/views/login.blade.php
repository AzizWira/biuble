@extends('layout')
@section('title', 'Masuk')
@section('body', 'login-screen')
@section('content')
<header class="top"><a class="button secondary back-button" href="{{ route('catalog') }}" aria-label="Kembali ke katalog"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="m14 5-7 7 7 7M7 12h14"/></svg></a></header>
<div class="login-intro">
    <span class="login-confetti" aria-hidden="true"></span>
    <h1>Selamat datang kembali</h1>
    <p>Masuk untuk melanjutkan ke Biuble.</p>
</div>
<div class="login-card">
    @if (session('otp'))
        <form class="stack" method="post" action="{{ route('otp.verify') }}">
            @csrf
            @if(session('otp.demo'))
                <p>Masukkan 6 digit kode dari halaman OTP demo.</p>
            @else
                <p>Masukkan 6 digit kode yang dikirim ke {{ session('otp.email') }}.</p>
            @endif
            <label class="field">Kode OTP<input name="code" autocomplete="one-time-code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required></label>
            @error('code')<p role="alert" class="form-error">{{ $message }}</p>@enderror
            <button class="button coral-action">Masuk</button>
        </form>
        <form method="post" action="{{ route('otp.reset') }}">@csrf @method('DELETE')<button class="text-button">Gunakan email lain atau minta kode baru</button></form>
    @else
        <form class="stack" method="post" action="{{ route('otp.send') }}">
            @csrf
            <label class="field">Email<input name="email" type="email" autocomplete="email" placeholder="nama@email.com" value="{{ old('email') }}" required></label>
            @error('email')<p role="alert" class="form-error">{{ $message }}</p>@enderror
            <button class="button coral-action">Kirim kode masuk</button>
        </form>
    @endif
    @error('google')<p role="alert" class="form-error">{{ $message }}</p>@enderror
    @if (config('services.google.client_id') && config('services.google.client_secret'))
        <a class="google-login" href="{{ route('google.redirect') }}">
            <img src="{{ asset('design/assets/google-signin.png') }}" alt="Masuk dengan Google">
        </a>
    @else
        <button class="google-login google-pending" type="button" disabled aria-label="Login Google menunggu konfigurasi">
            <img src="{{ asset('design/assets/google-signin.png') }}" alt="Masuk dengan Google">
        </button>
        <p class="setup-note">Login Google sedang disiapkan.</p>
    @endif
    <p class="login-footnote">Satu akun untuk semua kebutuhanmu.<br>Masuk dengan kode OTP atau akun Google.</p>
</div>
@endsection




