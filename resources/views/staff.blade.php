@extends('layout')
@section('title','Kelola toko')
@section('body', 'staff-screen')
@section('content')
<div class="intro staff-intro"><div><span class="eyebrow">{{ auth()->user()->role === 'owner' ? 'Ruang owner' : 'Ruang admin' }}</span><h1>Kelola Biuble</h1><p>Kelola pesanan, stok, dan saldo.</p></div><span class="badge ready">{{ auth()->user()->name }}</span></div>
<button class="staff-menu-toggle menu-{{ $activeTab }}" type="button" aria-controls="staff-menus" aria-expanded="true"><span><small>Menu kelola toko</small><strong>{{ $sections[$activeTab] }}</strong></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
<nav id="staff-menus" class="section-nav" aria-label="Menu kelola toko">
@foreach($sections as $key => $label)
<a class="staff-menu menu-{{ $key }}" href="{{ route('staff', ['bagian'=>$key]).($key === 'stok' ? '#stok-pilihan' : '') }}" @if($activeTab === $key) aria-current="page" @endif><span class="menu-dot" aria-hidden="true"></span>{{ $label }}</a>
@endforeach
</nav>
@include('support.links')
<div class="staff-workspace">@include('staff.'.$activeTab)</div>
@endsection
