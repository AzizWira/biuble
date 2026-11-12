@php
    $buyer = auth()->user()->role === 'reseller' && auth()->id() === $order->user_id;
    $staff = in_array(auth()->user()->role, ['owner', 'admin'], true);
@endphp
@if($buyer || $staff)
    <div class="support-item stack">
        @if($buyer)
            @if($item->warranty_days)
                <p><strong>Garansi {{ $item->warranty_days }} hari.</strong> @if($item->warrantyDeadline()) Berlaku sampai {{ \App\Support\StoreLabels::date($item->warrantyDeadline()) }}. @else Dimulai setelah akses diserahkan. @endif</p>
            @else
                <p><strong>{{ $item->warrantyBlock() }}</strong></p>
            @endif
        @endif
        @include('support.proof')
        @if($buyer)
            @if($item->warranty_days && ($reason = $item->warrantyBlock()))
                <p>{{ $reason }}</p>
            @endif
            <div class="toolbar">
                @if(!$item->warrantyBlock() && !auth()->user()->isReadOnly())
                    <a class="button" href="{{ route('support.create', ['kind'=>'garansi', 'item'=>$item->id]) }}">Ajukan garansi</a>
                @endif
                @unless(auth()->user()->isReadOnly())
                    <a class="button secondary" href="{{ route('support.create', ['kind'=>'masalah', 'item'=>$item->id]) }}">Laporkan masalah</a>
                @endunless
            </div>
        @endif
    </div>
@endif
