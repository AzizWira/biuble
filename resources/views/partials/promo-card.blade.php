@php
    $promoVariant = $promotion->variant ?? $promotion->product->variants->where('active', true)->sortBy(fn ($v) => $v->unitPrice())->first();
    $remaining = max(0, $promotion->ends_at->timestamp - now()->timestamp);
    $days = intdiv($remaining, 86400);
    $clockParts = [str_pad(intdiv($remaining % 86400, 3600), 2, '0', STR_PAD_LEFT), str_pad(intdiv($remaining % 3600, 60), 2, '0', STR_PAD_LEFT), str_pad($remaining % 60, 2, '0', STR_PAD_LEFT)];
@endphp
@if($promoVariant)
<a class="promo-card" href="{{ route('product', $promotion->product->slug) }}" data-promo-expires="{{ $promotion->ends_at->toIso8601String() }}">
<span class="promo-timer flip-timer" data-countdown="{{ $promotion->ends_at->toIso8601String() }}" title="Berakhir {{ \App\Support\StoreLabels::date($promotion->ends_at) }} WIB" aria-label="Sisa waktu promo {{ $days }} hari {{ $clockParts[0] }} jam {{ $clockParts[1] }} menit {{ $clockParts[2] }} detik">
<small>Sisa waktu <span data-countdown-days @if(!$days) hidden @endif>· {{ $days }} hari</span></small>
<span class="flip-clock" data-countdown-clock aria-hidden="true">
@foreach($clockParts as $part)
@if(!$loop->first)<span class="flip-colon">:</span>@endif
<span class="flip-unit"><span class="flip-pair">
@foreach(str_split($part) as $digit)
<span class="flip-digit" data-digit="{{ $digit }}"><span class="flip-half flip-top"><span>{{ $digit }}</span></span><span class="flip-half flip-bottom"><span>{{ $digit }}</span></span><span class="flip-half flip-front"><span>{{ $digit }}</span></span><span class="flip-half flip-back"><span>{{ $digit }}</span></span></span>
@endforeach
</span><small>{{ ['jam', 'mnt', 'dtk'][$loop->index] }}</small></span>
@endforeach
</span></span>
<span class="badge">Diskon {{ $promotion->discount_percent }}%</span><h3>{{ $promotion->title }}</h3><p>{{ $promotion->product->name }}@if($promotion->variant) · {{ $promotion->variant->name }}@endif</p>
<p class="promo-price">@unless($promotion->variant)<small>Mulai</small> @endunless<del>Rp{{ number_format($promoVariant->price,0,',','.') }}</del> <strong>Rp{{ number_format($promoVariant->unitPrice(),0,',','.') }}</strong></p>
</a>
@endif
