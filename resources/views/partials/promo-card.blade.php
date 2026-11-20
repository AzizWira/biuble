@php
    $promoVariant = $promotion->variant ?? $promotion->product->variants->where('active', true)->sortBy(fn ($v) => $v->unitPrice())->first();
@endphp
@if($promoVariant)
<a class="promo-card" href="{{ route('product', $promotion->product->slug) }}" data-promo-expires="{{ $promotion->ends_at->toIso8601String() }}">
<span class="promo-timer" data-countdown="{{ $promotion->ends_at->toIso8601String() }}" title="Berakhir {{ \App\Support\StoreLabels::date($promotion->ends_at) }} WIB"><small>Sisa waktu</small><strong data-countdown-clock>--:--:--</strong></span>
<span class="badge">Diskon {{ $promotion->discount_percent }}%</span><h3>{{ $promotion->title }}</h3><p>{{ $promotion->product->name }}@if($promotion->variant) · {{ $promotion->variant->name }}@endif</p>
<p class="promo-price">@unless($promotion->variant)<small>Mulai</small> @endunless<del>Rp{{ number_format($promoVariant->price,0,',','.') }}</del> <strong>Rp{{ number_format($promoVariant->unitPrice(),0,',','.') }}</strong></p>
</a>
@endif
