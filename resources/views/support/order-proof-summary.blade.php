<div class="order-proof-summary">
    @if($order->items->isEmpty())
        <p>Bukti login tidak diperlukan.</p>
    @else
        <h2>Bukti login</h2>
        <ul>
            @foreach($order->items as $item)
                @php
                    $deadline = $item->loginProofDeadline();
                    [$label, $tone, $action] = match (true) {
                        in_array($item->status, ['cancelled', 'expired', 'refunded', 'refund_requested', 'refunded_gateway'], true)
                            => ['Tidak perlu tindakan', '', null],
                        $item->loginProof?->status === 'accepted' => ['Bukti diterima', 'ready', null],
                        $item->loginProof?->status === 'submitted' => ['Sudah dikirim · menunggu pemeriksaan', '', null],
                        !$deadline || $item->status !== 'delivered' => ['Menunggu akses', '', null],
                        $item->loginProof?->status === 'correction' => ['Perlu diperbaiki', 'problem', 'Perbaiki bukti'],
                        now()->gt($deadline) => ['Belum diunggah · tenggat lewat', 'problem', null],
                        default => ['Belum diunggah', 'pending', 'Unggah bukti'],
                    };
                @endphp
                <li class="order-proof-row">
                    <div>
                        <strong>{{ $item->name }}</strong>
                        <small>Item #{{ $item->id }}@if($action === 'Unggah bukti') · Batas unggah {{ \App\Support\StoreLabels::date($deadline) }}@endif</small>
                    </div>
                    <div class="order-proof-actions">
                        <span class="badge {{ $tone }}">{{ $label }}</span>
                        @if($action && !auth()->user()->isReadOnly())
                            <a href="{{ route('orders.show', $order) }}#item-{{ $item->id }}" aria-label="{{ $action }} untuk item #{{ $item->id }}">{{ $action }}</a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
