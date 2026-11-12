<section id="pesanan" class="panel"><h2>Pesanan terbaru</h2>
@forelse($orders as $order)
<details class="history-row"><summary>{{ $order->reference }} · {{ $order->user->name }} · {{ \App\Support\StoreLabels::status($order->status) }}</summary>
<p>WhatsApp {{ $order->phone }} · Rp{{ number_format($order->total,0,',','.') }}</p><a href="{{ route('orders.show',$order) }}">Lihat detail / invoice</a>
@foreach($order->items as $item)
<div class="panel"><strong>{{ $item->name }}</strong><p>{{ \App\Support\StoreLabels::status($item->status) }} @if($item->email) · {{ $item->email }} @endif @if($item->designer) · Designer @endif</p>
@if(in_array($item->status,['waiting','review']) && ($item->delivery === 'invite' || auth()->user()->role === 'owner'))
<form class="stack" method="post" action="{{ route('staff.deliver',$item) }}">@csrf<label class="field">{{ $item->delivery === 'invite' ? 'Catatan undangan yang sudah dikirim' : 'Detail akses untuk reseller' }}<textarea name="access" maxlength="8000" required></textarea></label><button class="button">{{ $item->delivery === 'invite' ? 'Tandai undangan dikirim' : 'Berikan akses' }}</button></form>
@endif
@if(auth()->user()->role === 'owner' && $order->paid_at)
@if($item->status === 'refund_requested')
<form class="stack" method="post" action="{{ route('staff.gateway-refund',$item) }}">@csrf<label class="field">Referensi refund kanal asal yang sudah selesai<input name="reference" maxlength="120" required></label><button class="button secondary">Catat refund DOKU selesai</button></form>
@elseif($order->method === 'wallet' && in_array($item->status,['waiting','review','delivered']))
<form class="toolbar" method="post" action="{{ route('staff.refund',$item) }}" data-confirm="Kembalikan nilai item ke saldo refund reseller?">@csrf<input name="reason" placeholder="Alasan refund" maxlength="200" required><button class="button secondary">Refund item ke saldo</button></form>
@endif
@endif
</div>
@endforeach
</details>@empty<p>Belum ada pesanan.</p>@endforelse</section>
