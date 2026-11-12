@extends('layout')
@section('title','Verifikasi owner')
@section('content')
<section class="panel stack"><h1>Verifikasi tindakan owner</h1><p>Kode dikirim ke email akun owner. Setelah diverifikasi, kembali ke dashboard untuk melanjutkan tindakan.</p>
<form action="{{ route('security.send') }}" method="post">@csrf<button class="button secondary">Kirim kode verifikasi</button></form>
<form class="stack" action="{{ route('security.verify') }}" method="post">@csrf<label class="field">Kode OTP<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required></label><button class="button">Verifikasi</button></form>
</section>
@endsection

