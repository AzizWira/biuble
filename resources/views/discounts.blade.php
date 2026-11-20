@extends('layout')
@section('title', 'Semua diskon')
@section('body', 'discounts-screen')
@section('content')
<div class="intro"><a class="button secondary" href="{{ route('catalog') }}">&larr; Kembali ke beranda</a><h1>Semua diskon</h1><p>Pilih promo yang masih aktif. Harga diskon berlaku selama hitung mundur berjalan.</p></div>
@if($promotions->isNotEmpty())
<section class="discount-list">
<div class="promo-grid" aria-label="Daftar diskon aktif">
@foreach($promotions as $promotion)
@include('partials.promo-card')
@endforeach
</div>
{{ $promotions->links() }}
</section>
@else
<section class="panel"><h2>Belum ada diskon aktif</h2><p>Promo baru akan tampil di sini saat tersedia.</p><a class="button" href="{{ route('catalog') }}">Lihat katalog aplikasi</a></section>
@endif
@endsection
