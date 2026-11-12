@extends('layout')
@section('title','Saldo')
@section('body', 'wallet-screen')
@section('content')
<div class="intro"><h1>Saldo Biuble</h1><p>Saldo tersedia Rp{{ number_format(array_sum($balances),0,',','.') }}</p></div>
<section class="grid balance-grid">@foreach(['topup'=>'Top-up','cancellation'=>'Pembatalan','refund'=>'Refund pesanan'] as $key=>$label)
<div class="panel"><h2>{{ $label }}</h2><strong>Rp{{ number_format($balances[$key],0,',','.') }}</strong></div>@endforeach</section>
<details class="balance-explainer"><summary>Apa perbedaan sumber saldo?</summary><div class="balance-explanation">
<p><strong>Top-up:</strong> saldo dari pengisian dana yang telah dikonfirmasi.</p>
<p><strong>Pembatalan:</strong> dana pesanan yang dikembalikan karena item dibatalkan. Penarikannya bebas biaya Biuble.</p>
<p><strong>Refund pesanan:</strong> pengembalian dana yang diproses owner, misalnya karena kendala pesanan. Penarikan yang menggunakan sumber ini mengikuti biaya yang ditampilkan sebelum pengajuan.</p>
<p>Saat belanja, saldo refund pesanan dipakai terlebih dahulu, lalu pembatalan, kemudian top-up. Pemakaian sumber tersebut tetap dicatat sebagai <strong>pembelian produk</strong>, bukan refund baru.</p>
</div></details>
<section class="grid wallet-forms">
<form class="panel stack fee-form" enctype="multipart/form-data" method="post" action="{{ route('topup') }}" data-type="{{ \App\Services\Store::setting('topup_fee_type','fixed') }}" data-value="{{ \App\Services\Store::setting('topup_fee_value','0') }}" data-channels="{{ \App\Services\Store::setting('fee_channels','[]') }}">
@csrf<input name="token" type="hidden" value="{{ Str::uuid() }}"><input name="expected_total" type="hidden" value="1">
<h2>Isi saldo</h2>
<label class="field">Nominal saldo<input name="amount" type="number" min="1" max="1000000000" value="10000" required></label>
<label class="field">Metode<select name="method"><option value="manual">Transfer manual · gratis</option>@foreach($channels as $key=>$label)<option value="{{ $key }}">{{ $label }} via DOKU</option>@endforeach</select></label>
<div class="notice manual-only">{!! nl2br(e(\App\Services\Store::setting('transfer_instructions','Owner belum mengatur rekening tujuan. Hubungi admin sebelum transfer.'))) !!}</div>
<label class="field manual-only">Bukti transfer manual<input name="proof" type="file" accept=".jpg,.jpeg,.png,.pdf"><small>Maksimal 4 MB.</small></label>
<dl class="amounts"><div><dt>Biaya layanan</dt><dd data-fee>Rp0</dd></div><div class="total"><dt>Total bayar</dt><dd data-total></dd></div></dl>
<button class="button">Buat top-up</button>
</form>
<form class="panel stack withdraw-form" action="{{ route('withdraw') }}" method="post" data-fee="{{ \App\Services\Store::setting('withdraw_fee','1000') }}" data-confirm="Kirim pengajuan penarikan sesuai rincian ini?">
@csrf<input type="hidden" name="token" value="{{ Str::uuid() }}"><input name="expected_fee" type="hidden" value="0">
<h2>Tarik saldo</h2>
@foreach(['topup'=>'Dari top-up','cancellation'=>'Dari pembatalan','refund'=>'Dari refund pesanan'] as $key=>$label)
<label class="field">{{ $label }}<input name="{{ $key }}" type="number" min="0" max="{{ $balances[$key] }}" value="0" required></label>@endforeach
<label class="field">Bank / e-wallet, nomor, dan nama penerima<input name="destination" maxlength="200" required></label>
<dl class="amounts"><div><dt>Biaya penarikan</dt><dd data-fee>Rp0</dd></div><div class="total"><dt>Dana yang diterima</dt><dd data-net>Rp0</dd></div></dl>
<p>Dana ditahan saat pengajuan dikirim. Owner memeriksa dan mentransfer secara manual.</p><button class="button">Ajukan penarikan</button>
</form></section>
<section class="panel"><h2>Riwayat top-up</h2>
@forelse($topups as $topup)<article class="history-row transaction-row">
<strong>{{ $topup->reference }}</strong><small class="history-date">{{ \App\Support\StoreLabels::date($topup->created_at) }}</small>
<p>Saldo Rp{{ number_format($topup->amount,0,',','.') }} · biaya Rp{{ number_format($topup->fee,0,',','.') }} · total Rp{{ number_format($topup->total,0,',','.') }}</p>
<div class="transaction-actions"><span class="badge">{{ \App\Support\StoreLabels::status($topup->status) }}</span>
@if($topup->method !== 'manual' && $topup->status === 'pending')<form action="{{ route('payment',$topup->reference) }}" method="post">@csrf<button class="button secondary">Bayar DOKU</button></form>@endif
@if($topup->pending_doku_link)
    <form action="{{ route('payment.check', $topup->reference) }}" method="post">@csrf<button class="button secondary">Cek status DOKU</button></form>
@endif
</div>
</article>@empty<p>Belum ada top-up.</p>@endforelse
</section>
<section class="panel"><h2>Pengajuan penarikan</h2>
@forelse($withdrawals as $withdrawal)<article class="history-row"><div class="transaction-heading"><strong>Penarikan #{{ $withdrawal->id }}</strong><span class="badge">{{ \App\Support\StoreLabels::status($withdrawal->status) }}</span></div><small class="history-date">{{ \App\Support\StoreLabels::date($withdrawal->created_at) }}</small><p>Dipotong dari saldo Rp{{ number_format($withdrawal->amount,0,',','.') }} · biaya Rp{{ number_format($withdrawal->fee,0,',','.') }} · diterima Rp{{ number_format($withdrawal->net,0,',','.') }}</p></article>@empty<p>Belum ada pengajuan.</p>@endforelse
</section>
<section class="panel"><h2>Mutasi saldo</h2><p class="ledger-intro">Dana masuk bertanda +, dana keluar bertanda −. Sumber saldo menunjukkan dana mana yang digunakan.</p>
@forelse($entries as $entry)
@php($entryOrder = $ledgerOrders->get(\App\Support\StoreLabels::entryOrderId($entry)))
<article class="ledger-entry">
<div class="ledger-heading"><strong>{{ \App\Support\StoreLabels::entryTitle($entry) }}</strong><span class="ledger-amount {{ $entry->amount < 0 ? 'is-debit' : 'is-credit' }}">{{ $entry->amount < 0 ? '−' : '+' }}Rp{{ number_format(abs($entry->amount),0,',','.') }}</span></div>
<small>{{ \App\Support\StoreLabels::date($entry->created_at) }}</small>
<p>{{ $entryOrder ? 'Diambil dari saldo' : 'Sumber saldo' }}: {{ \App\Support\StoreLabels::source($entry->source) }}</p>
@if($entryOrder)<p class="ledger-products">{{ $entryOrder->items->pluck('name')->unique()->implode(', ') }}</p><a href="{{ route('orders.show',$entryOrder->id) }}">Lihat pesanan {{ $entryOrder->reference }}</a>
@elseif($entry->note)<p>{{ $entry->note }}</p>@endif
</article>
@empty<p>Belum ada pergerakan saldo.</p>@endforelse
{{ $entries->links() }}</section>
@endsection
