@extends('layout')
@section('title','Kelola toko')
@section('body', 'staff-screen')
@section('content')
<div class="intro staff-intro"><div><span class="eyebrow">{{ auth()->user()->role === 'owner' ? 'Ruang owner' : 'Ruang admin' }}</span><h1>Kelola Biuble</h1><p>Kelola pesanan, stok, dan saldo.</p></div><span class="badge ready">{{ auth()->user()->name }}</span></div>
<button class="staff-menu-toggle menu-{{ $activeTab }}" type="button" aria-controls="staff-menus" aria-expanded="true"><span><small>Menu kelola toko</small><strong>{{ $sections[$activeTab] }}</strong></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
<nav id="staff-menus" class="section-nav" aria-label="Menu kelola toko">
@foreach($sections as $key => $label)
<a class="staff-menu menu-{{ $key }} {{ !empty($navBadges[$key]) ? 'has-notifications' : '' }}" href="{{ route('staff', ['bagian'=>$key]).($key === 'stok' ? '#stok-pilihan' : '') }}" @if($activeTab === $key) aria-current="page" @endif><span class="menu-dot" aria-hidden="true"></span>{{ $label }}<span class="nav-badge" data-badge="{{ $key }}" @if(empty($navBadges[$key])) hidden @endif>{{ ($navBadges[$key] ?? 0) > 99 ? '99+' : ($navBadges[$key] ?? 0) }}</span></a>
@endforeach
</nav>
@include('support.links')
<details class="role-guide"><summary>Panduan akses dan badge {{ auth()->user()->role }}</summary><p>@if(auth()->user()->role === 'owner')Owner mengelola produk, kategori, diskon, akun tim, keuangan, dan keputusan garansi. Semua pekerjaan yang memerlukan keputusan owner masuk ke badge owner.@else Admin mengelola stok, undangan, pemeriksaan bukti, top-up manual, pengaduan, harga varian, serta pengumuman sendiri. Produk baru, kategori, diskon, refund, penarikan, perubahan role, dan keputusan akhir garansi dikelola owner.@endif</p><p>Badge Pesanan, Top-up, Pengajuan, dan Penarikan menghitung pekerjaan yang belum ditangani. Pusat informasi dan Aktivitas menghitung yang belum dibaca. Angka mengikuti kewenangan akunmu.</p></details>
<div class="staff-workspace">@include('staff.'.$activeTab)</div>
@endsection
