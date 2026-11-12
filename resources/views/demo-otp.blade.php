@extends('layout')
@section('title', 'OTP demo')
@section('body', 'login-screen')
@section('content')
<div class="login-intro">
    <h1>OTP demo</h1>
    <p>Siapkan kode masuk akun owner untuk demo.</p>
</div>
<section class="login-card">
    @if(session('demo_code'))
        <div class="stack">
            <p>Kode ini berlaku lima menit di sesi browser ini.</p>
            <output class="badge ready" aria-label="Kode OTP demo">{{ session('demo_code') }}</output>
            <a class="button coral-action" href="{{ route('login') }}">Lanjut ke login</a>
        </div>
    @else
        <form class="stack" method="post" action="{{ route('otp.demo.issue') }}">
            @csrf
            <label class="field">Kunci akses demo<input name="demo_key" type="password" autocomplete="off" required></label>
            <button class="button coral-action">Buat kode OTP</button>
        </form>
    @endif
</section>
@endsection
