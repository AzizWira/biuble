@php
    $ownerVerification = ($purpose ?? 'login') === 'owner-verification';
    $canvas = $ownerVerification ? '#f4effa' : '#eaf7f6';
    $banner = $ownerVerification ? '#e9ddf6' : '#d3eeec';
    $accent = $ownerVerification ? '#fff0c9' : '#f9d6c8';
    $border = $ownerVerification ? '#c7b4df' : '#a8d0d1';
    $label = $ownerVerification ? 'Keamanan owner' : 'Masuk ke Biuble';
    $heading = $ownerVerification ? 'Konfirmasi tindakan owner' : 'Selamat datang di Biuble';
    $description = $ownerVerification
        ? 'Masukkan kode ini di halaman verifikasi owner untuk melanjutkan tindakan sensitif di Kelola Toko.'
        : 'Masukkan kode ini di halaman masuk untuk membuka akun Biuble kamu.';
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $label }} · Biuble</title>
</head>
<body style="margin:0;padding:0;background:{{ $canvas }};color:#233451;font-family:Arial,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="{{ $canvas }}">
    <tr><td align="center" style="padding:28px 12px">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#fffaf3" style="max-width:520px;border:1px solid {{ $border }};border-radius:24px;border-collapse:separate">
            <tr><td align="center" bgcolor="{{ $banner }}" style="padding:22px 16px 18px;border-radius:23px 23px 0 0">
                <img src="{{ $message->embed(public_path('design/assets/biuble-logo.png')) }}" alt="Biuble" width="148" style="display:block;width:148px;max-width:100%;height:auto;border:0;border-radius:16px">
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px auto 0">
                    <tr><td bgcolor="{{ $accent }}" style="border:1px solid {{ $border }};border-radius:18px;padding:6px 13px;color:#233451;font-size:12px;line-height:18px;font-weight:bold">{{ $label }}</td></tr>
                </table>
            </td></tr>
            <tr><td style="padding:24px 22px">
                <h1 style="margin:0 0 10px;font-size:25px;line-height:32px;color:#233451">{{ $heading }}</h1>
                <p style="margin:0 0 20px;font-size:15px;line-height:23px;color:#425572">{{ $description }}</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="{{ $accent }}" style="border:1px solid {{ $border }};border-radius:16px">
                    <tr><td align="center" style="padding:14px 8px">
                        <p style="margin:0 0 5px;font-size:12px;line-height:18px;font-weight:bold;color:#425572">Kode verifikasi</p>
                        <p style="margin:0;font-family:'Courier New',monospace;font-size:32px;line-height:38px;letter-spacing:4px;font-weight:bold;color:#233451">{{ $code }}</p>
                    </td></tr>
                </table>
                <p style="margin:18px 0 0;font-size:14px;line-height:21px;color:#425572">Kode berlaku <strong>5 menit</strong> dan hanya bisa dipakai sekali. Simpan kode ini untuk dirimu sendiri.</p>
            </td></tr>
            <tr><td style="padding:15px 22px;border-top:1px dashed {{ $border }};font-size:13px;line-height:20px;color:#425572">
                Jika kamu tidak meminta kode ini, abaikan email ini.
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
