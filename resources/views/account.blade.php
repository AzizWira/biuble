@extends('layout')
@section('title','Akun')
@section('body', 'account-screen')
@section('content')
<div class="intro"><h1>Akun Biuble</h1><p>{{ $user->name }} · {{ $user->email }}</p></div>
<section class="panel stack"><span class="badge">{{ ['owner'=>'Owner toko','admin'=>'Admin toko','reseller'=>'Reseller'][$user->role] ?? 'Akun' }} / {{ \App\Support\StoreLabels::status($user->approval) }}</span>
@if($user->role === 'reseller' && in_array($user->approval, ['draft','rejected']))
<form class="stack" action="{{ route('reseller.apply') }}" method="post">@csrf
    <h2>Ajukan akses reseller</h2>
    <label class="field">Nama<input name="name" value="{{ old('name',$user->name) }}" maxlength="120" required></label>
    <label class="field">Nomor WhatsApp<input name="phone" type="tel" value="{{ old('phone',$user->phone) }}" placeholder="08…" required><small>Nomor WhatsApp yang digunakan saat bergabung dengan grup BIUBLE.</small></label>
    <button class="button">Kirim pengajuan</button>
</form>
@elseif($user->approval === 'pending')
    <img class="illustration" src="{{ asset('design/assets/ilustrasi-menunggu.svg') }}" alt="">
    <h2>Pengajuan sedang ditinjau</h2><p>Owner memeriksa nomor WhatsApp kamu di grup BIUBLE.</p>
@endif
<p>Status akses: <strong>{{ \App\Support\StoreLabels::accountStatus($user->account_status) }}</strong></p>
<form data-allow-readonly action="{{ route('logout') }}" method="post"
    data-confirm="Kamu perlu masuk lagi untuk membuka akun Biuble." data-confirm-title="Keluar dari akun?" data-confirm-label="Ya, keluar">
    @csrf<button class="button secondary">Keluar dari akun</button>
</form>
</section>
@if($user->role === 'reseller')
    @include('support.links')
@endif
<section class="panel"><h2>Notifikasi</h2>
@forelse($notifications as $notification)<p>{{ $notification->message }} @if($notification->support_case_id)<a href="{{ route('support.show',$notification->support_case_id) }}">Lihat pengajuan</a>@elseif($notification->order_id)<a href="{{ route('orders.show',$notification->order_id) }}">Lihat pesanan</a>@endif</p>
@empty<p>Belum ada pemberitahuan.</p>@endforelse
</section>
@endsection

