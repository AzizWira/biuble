@extends('layout')
@section('title','Detail pesanan')
@section('body', 'order-screen')
@section('content')
<div class="intro"><h1>{{ $invoice ? 'Invoice pemesanan' : 'Detail pesanan' }}</h1><p>{{ $order->reference }}</p></div>
<section class="panel"><p>Status pembayaran <strong>{{ \App\Support\StoreLabels::status($order->status) }}</strong></p>
<p>WhatsApp {{ $order->phone }}</p><p>Metode {{ \App\Support\StoreLabels::method($order->method) }}</p>
<dl class="amounts"><div><dt>Subtotal</dt><dd>Rp{{ number_format($order->subtotal,0,',','.') }}</dd></div><div><dt>Biaya layanan</dt><dd>Rp{{ number_format($order->fee,0,',','.') }}</dd></div><div class="total"><dt>Total</dt><dd>Rp{{ number_format($order->total,0,',','.') }}</dd></div></dl>
<div class="transaction-actions">
@if(!$invoice && in_array(auth()->user()->role, ['owner', 'admin'], true))
    @include('support.contact-reseller', ['phone' => $order->phone])
@endif
@if(!$invoice)<a href="{{ route('orders.show',[$order,'invoice'=>1]) }}">Lihat invoice</a>@else<button type="button" class="button secondary" onclick="window.print()">Cetak / simpan PDF</button>@endif
@if($order->status === 'pending' && !$order->expires_at->isPast())
<form method="post" action="{{ route('payment',$order->reference) }}">@csrf<button class="button">Lanjutkan pembayaran DOKU</button></form>
@endif
@if(!$invoice && $order->pending_doku_link && auth()->id() === $order->user_id)
    <form method="post" action="{{ route('payment.check', $order->reference) }}">@csrf<button class="button secondary">Cek status DOKU</button></form>
@endif
</div>
</section>
@foreach($order->items as $item)
<article class="panel" id="item-{{ $item->id }}"><h2>{{ $item->name }}</h2><p class="badge">{{ \App\Support\StoreLabels::status($item->status) }}</p>
<p>Rp{{ number_format($item->amount,0,',','.') }} @if($item->designer) · Designer @endif</p>
@if($item->email)<p>Email undangan {{ $item->email }}</p>@endif
@if($item->status === 'delivered')
@if(!$invoice)<pre class="access">{{ $item->access }}</pre>@endif
<p>Mulai {{ \App\Support\StoreLabels::date($item->delivered_at) }} @if($item->ends_at) · Berakhir {{ \App\Support\StoreLabels::date($item->ends_at) }}@endif</p>
@endif
@if($item->status === 'waiting' && $item->delivery === 'manual' && $order->paid_at && $order->paid_at->lte(now()->subMinutes(30)) && auth()->id() === $order->user_id)
<form action="{{ route('item.cancel',$item) }}" method="post" data-confirm="Batalkan item ini?">@csrf<button class="button secondary">Batalkan item ini</button></form>
@endif
@unless($invoice)
    @include('support.item')
@endunless
</article>
@endforeach
@endsection

