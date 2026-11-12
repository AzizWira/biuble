@extends('layout')
@section('title', $product->name)
@section('body', 'product-screen')
@section('content')
<div class="intro product-intro">
    <a class="back-link" href="{{ route('catalog') }}"><span aria-hidden="true">&larr;</span> Kembali ke katalog</a>
    <div class="product-title"><div class="app-avatar" style="--card-color:{{ $product->color }}">@if($product->image)<img src="{{ asset('storage/'.$product->image) }}" alt="">@else{{ mb_substr($product->name,0,1) }}@endif</div><div><span class="eyebrow">{{ $product->category->name }}</span><h1>{{ $product->name }}</h1></div></div>
    <p>{{ $product->description }}</p>
    @if(auth()->check() && in_array(auth()->user()->role, ['owner','admin']))<a class="button secondary" href="{{ route('staff', ['bagian'=>'katalog']) }}">Kelola produk dan varian</a>@endif
</div>
@foreach ($product->variants->groupBy('type') as $type => $variants)
<section class="variant-group">
    <div class="group-title"><span class="profile-head {{ $type }}"><img src="{{ asset('design/assets/'.($type === 'private' ? 'profil.svg' : 'profil-sharing.svg')) }}" alt=""></span><div><h2>{{ $type === 'private' ? 'Private Account' : 'Sharing Account' }}</h2><p>{{ $type === 'private' ? 'Akses pribadi sesuai ketentuan paket.' : 'Akun digunakan bersama, sesuai jumlah pengguna paket.' }}</p></div></div>
    <div class="variant-list">
    @foreach ($variants as $variant)
    @php($accent = $variant->color ?? ['#ffedb7','#f9d0dc','#cce9f7','#e6d7f8','#d9f0e6'][$loop->index % 5])
    @php($isStaff = auth()->check() && in_array(auth()->user()->role, ['owner','admin']))
    @if($isStaff)
    <article class="variant-card" style="--variant-color:{{ $accent }}"><div class="variant-heading">
        @include('partials.variant-heading')
        <a class="variant-edit" href="{{ route('staff', ['bagian'=>'katalog']) }}" aria-label="Kelola varian {{ $variant->name }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5 4 4M4 20l5-1L20 8a3 3 0 0 0-4-4L5 15Z"/></svg></a>
    </div></article>
    @elseif(! $variant->isPurchasable())
    <article class="variant-card is-sold-out" aria-disabled="true" style="--variant-color:{{ $accent }}"><div class="variant-heading">
        @include('partials.variant-heading')
        <button class="plus" type="button" disabled aria-label="Stok paket habis"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg></button>
    </div></article>
    @else
    <details class="variant-card variant-disclosure" style="--variant-color:{{ $accent }}">
        <summary class="variant-heading" aria-controls="pilihan-{{ $variant->id }}" aria-label="Pilih paket {{ $variant->name }}">
            @include('partials.variant-heading')
            <span class="plus" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
        </summary>
        <div class="variant-expand" id="pilihan-{{ $variant->id }}">
            <form class="variant-purchase" method="post" action="{{ route('cart.add') }}">@csrf
                <input type="hidden" name="variant" value="{{ $variant->id }}">
                @if ($variant->delivery === 'invite')<label class="field span-full">Email tujuan undangan<input name="email" type="email" autocomplete="email" placeholder="nama@email.com" required></label>@endif
                @if ($variant->designer)<label class="check-label span-full"><input type="checkbox" name="designer" value="1"> Designer +Rp1.000 per unit</label>@endif
                <label class="field">Jumlah<span class="quantity-control"><button type="button" data-quantity-step="-1" aria-label="Kurangi jumlah">&minus;</button><input name="quantity" type="number" min="1" max="{{ min(20, $variant->availableUnits() ?? 20) }}" value="1" aria-label="Jumlah paket" required><button type="button" data-quantity-step="1" aria-label="Tambah jumlah">+</button></span></label>
                @auth
                    @if(auth()->user()->approval === 'approved')<button class="button">Tambah ke keranjang</button>@else<a class="button secondary" href="{{ route('account') }}">Periksa akses reseller</a>@endif
                @else<a class="button" href="{{ route('login') }}">Masuk untuk order</a>@endauth
            </form>
        </div>
    </details>
    @endif
    @endforeach
    </div>
</section>
@endforeach
@endsection
