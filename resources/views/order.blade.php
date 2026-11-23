@extends('layout')
@section('title','Detail pesanan')
@section('body', 'order-screen')
@section('content')
@php
    $buyerActions = auth()->user()->role === 'reseller' && auth()->id() === $order->user_id && !auth()->user()->isReadOnly();
@endphp
<div class="intro"><h1>{{ $invoice ? 'Invoice pemesanan' : 'Detail pesanan' }}</h1><p>{{ $order->reference }}</p></div>
@if(!$invoice && in_array(auth()->user()->role, ['owner', 'admin'], true))
<p class="notice">Kamu melihat pesanan sebagai {{ auth()->user()->role === 'owner' ? 'owner' : 'admin' }}. Pembayaran, perubahan metode, dan pembatalan pembelian dilakukan oleh reseller pemilik pesanan. <a class="button secondary" href="{{ route('staff', ['bagian'=>'pesanan']) }}">Tangani di Kelola toko</a></p>
@endif
<section class="panel"><p>Status pembayaran <strong class="status-pill tone-{{ \App\Support\StoreLabels::tone($order->status) }}">{{ \App\Support\StoreLabels::status($order->status) }}</strong></p>
<p>WhatsApp {{ $order->phone }}</p><p>Metode {{ \App\Support\StoreLabels::method($order->method) }}</p>
<dl class="amounts"><div><dt>Subtotal</dt><dd>Rp{{ number_format($order->subtotal,0,',','.') }}</dd></div><div><dt>Biaya layanan</dt><dd>Rp{{ number_format($order->fee,0,',','.') }}</dd></div><div class="total"><dt>Total</dt><dd>Rp{{ number_format($order->total,0,',','.') }}</dd></div></dl>
<div class="transaction-actions">
@if(!$invoice && in_array(auth()->user()->role, ['owner', 'admin'], true))
    @include('support.contact-reseller', ['phone' => $order->phone])
@endif
@if(!$invoice)<a href="{{ route('orders.show',[$order,'invoice'=>1]) }}">Lihat invoice</a>@else<button type="button" class="button secondary" onclick="window.print()">Cetak / simpan PDF</button>@endif
@if(!$invoice && $buyerActions && $order->payment && $order->status === 'pending' && !$order->expires_at->isPast())
<form method="post" action="{{ route('payment',$order->payment?->reference ?? $order->reference) }}">@csrf<button class="button">Lanjutkan pembayaran DOKU</button></form>
@endif
@if(!$invoice && $order->pending_doku_link && $buyerActions)
    <form method="post" action="{{ route('payment.check', $order->payment?->reference ?? $order->reference) }}">@csrf<button class="button secondary">Cek status DOKU</button></form>
@endif
</div>
@if(!$invoice && $buyerActions && $order->status === 'pending' && $order->expires_at->isFuture())
<div class="stack payment-options">
    <p>Batas pembayaran {{ \App\Support\StoreLabels::date($order->expires_at) }}. Jika sudah membayar, cek status DOKU sebelum mengganti metode.</p>
    <details><summary class="button secondary">Ubah metode pembayaran</summary>
    <form class="stack fee-form" method="post" action="{{ route('orders.method', $order) }}" data-base="{{ $order->subtotal }}" data-fees='@json($paymentFees)' data-confirm="Ganti metode dan gunakan rincian tagihan terbaru? Tagihan sebelumnya tidak boleh dibayar lagi.">
        @csrf
        <input type="hidden" name="expected_payment" value="{{ $order->payment?->id ?? 0 }}">
        <label class="field">Metode pembayaran<select name="method"><option value="wallet">Saldo Biuble</option>@foreach($channels as $key => $label)<option value="{{ $key }}" @selected($order->method === $key)>{{ $label }}</option>@endforeach</select></label>
        <p>Untuk transfer manual, <a href="{{ route('wallet') }}">top-up saldo terlebih dahulu</a>, tunggu disetujui, lalu kembali memilih Saldo Biuble. Batas pembayaran pesanan tetap berlaku.</p>
        <dl class="amounts"><div><dt>Biaya layanan</dt><dd data-fee></dd></div><div><dt>Total</dt><dd data-total></dd></div></dl>
        <input type="hidden" name="expected_total" value="{{ $order->total }}"><button class="button">Konfirmasi metode pembayaran</button>
    </form></details>
    <form method="post" action="{{ route('orders.cancel', $order) }}" data-confirm="Batalkan pembelian ini dan lepaskan reservasi stoknya?" data-confirm-title="Batalkan pesanan?">@csrf<button class="button secondary danger">Batalkan pesanan</button></form>
</div>
@endif
</section>
@foreach($order->items as $item)
<article class="panel" id="item-{{ $item->id }}"><h2>{{ $item->name }}</h2><p class="status-pill tone-{{ \App\Support\StoreLabels::tone($item->status) }}">{{ \App\Support\StoreLabels::status($item->status) }}</p>
<p>Rp{{ number_format($item->amount,0,',','.') }} @if($item->designer) · Designer @endif</p>
@if($item->email)<p>Email undangan {{ $item->email }}</p>@endif
@if($item->status === 'delivered')
@if(!$invoice)<pre class="access">{{ $item->access }}</pre>@endif
<p>Mulai {{ \App\Support\StoreLabels::date($item->delivered_at) }} @if($item->ends_at) · Berakhir {{ \App\Support\StoreLabels::date($item->ends_at) }}@endif</p>
@endif
@if(!$invoice && $buyerActions && $item->status === 'waiting' && $item->delivery === 'manual' && $order->paid_at && $order->paid_at->lte(now()->subMinutes(30)))
<form action="{{ route('item.cancel',$item) }}" method="post" data-confirm="Batalkan item ini?">@csrf<button class="button secondary">Batalkan item ini</button></form>
@endif
@unless($invoice)
    @include('support.item')
@endunless
</article>
@endforeach
@endsection

