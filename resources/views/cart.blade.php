@extends('layout')
@section('title','Keranjang')
@section('body', 'cart-screen')
@section('content')
<div class="intro"><h1>Keranjangmu</h1><p>Periksa paket dan cara pembayaran sebelum konfirmasi.</p></div>
@forelse($lines as $line)
<article class="panel cart-item"><h2>{{ $line['variant']->product->name }}</h2><p>{{ $line['variant']->name }} · {{ $line['row']['quantity'] }} unit</p>
<p>Rp{{ number_format($line['price']*$line['row']['quantity'],0,',','.') }}</p>
@if(!empty($line['row']['email']))<p>Undangan ke {{ $line['row']['email'] }}</p>@endif
@if(!empty($line['row']['designer']))<p>Dengan Designer</p>@endif
<form method="post" action="{{ route('cart.remove',$line['key']) }}">@csrf @method('DELETE')<button class="text-button">Hapus paket</button></form>
</article>
@empty
<section class="panel"><img class="illustration" src="{{ asset('design/assets/ilustrasi-keranjang.svg') }}" alt=""><p>Keranjang masih kosong.</p><a class="button" href="{{ route('catalog') }}">Lihat katalog</a></section>
@endforelse
@if(count($lines))
@foreach($stockIssues as $issue)<p class="notice form-error">{{ $issue }}</p>@endforeach
@if(collect($lines)->contains(fn($line)=>$line['variant']->delivery === 'manual'))
<div class="notice">Sebelum membayar, hubungi admin untuk memastikan admin siap memproses akunmu. Jika akun belum siap 30 menit setelah pembayaran berhasil, item manual bisa dibatalkan. <a href="https://wa.me/6289527095323" target="_blank" rel="noopener">Hubungi admin</a></div>
@endif
<form class="panel stack fee-form" method="post" action="{{ route('checkout') }}" data-flow="checkout" data-base="{{ $subtotal }}" data-fees='@json($fees)'>
@csrf<input type="hidden" name="token" value="{{ Str::uuid() }}"><input name="expected_total" type="hidden" value="{{ $subtotal }}">
<label class="field">Bayar dengan<select name="method"><option value="wallet">Saldo Biuble · Rp{{ number_format(array_sum($balances),0,',','.') }}</option>@foreach($channels as $key=>$label)<option value="{{ $key }}">{{ $label }} via DOKU</option>@endforeach</select></label>
<dl class="amounts"><div><dt>Subtotal</dt><dd>Rp{{ number_format($subtotal,0,',','.') }}</dd></div><div><dt>Biaya layanan</dt><dd data-fee>Rp0</dd></div><div class="total"><dt>Total bayar</dt><dd data-total>Rp{{ number_format($subtotal,0,',','.') }}</dd></div></dl>
<p>Saldo kurang? <a href="{{ route('wallet') }}">Top-up dulu</a> atau pilih pembayaran penuh lewat DOKU.</p>
<button class="button" @disabled(count($stockIssues) > 0)>Konfirmasi pesanan</button>
</form>
@endif
@endsection


