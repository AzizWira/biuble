@php
    use App\Support\StoreLabels;
    $base = ['bagian' => 'stok'];
    $appFilter = $product ? $base + ['app' => $product->slug] : $base;
    $typeLabels = ['sharing' => 'Sharing', 'private' => 'Private'];
@endphp

<section class="panel stack stock-picker" id="stok-pilihan">
    @if($product)
        <div class="stock-picker-header">
            <h2>Stok {{ $product->name }}</h2>
            <a class="button secondary" href="{{ route('staff', $base) }}#stok-pilihan"><span aria-hidden="true">←</span> Semua aplikasi</a>
        </div>
        @include('staff.stock-select', ['label' => 'Varian', 'selected' => $variant ? $typeLabels[$variant->type].' · '.StoreLabels::variantName($variant->name) : null, 'expanded' => !$variant, 'options' => $stockVariants->map(fn ($option) => [
            'label' => $typeLabels[$option->type].' · '.StoreLabels::variantName($option->name), 'note' => $option->availableUnits().' slot siap'.($option->active ? '' : ' · Varian nonaktif'),
            'url' => route('staff', $appFilter + ['varian' => $option->id]).'#stok-akun',
            'current' => $option->id === $variant?->id,
        ])])
    @else
        <h2>Pilih aplikasi</h2>
        <div class="stock-apps">
            @forelse($stockProducts as $option)
                <a class="stock-app" href="{{ route('staff', $base + ['app' => $option->slug]) }}#stok-pilihan" style="--stock-app-color:{{ $option->color }}">
                    <strong>{{ $option->name }}</strong>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                </a>
            @empty
                <p>Belum ada aplikasi dengan varian yang memakai stok akun.</p>
            @endforelse
        </div>
    @endif
</section>

@if($variant)
    @include('staff.stock-detail')
@elseif($product)
    <div class="stock-history">
        <section class="panel stack" id="stok-masuk">
            <div><h2>Stok masuk</h2><p>Akun dan slot baru yang ditambahkan ke toko.</p></div>
            @forelse($incoming as $account)
                <div class="history-row stock-event">
                    <strong>{{ $account->stockLabel() }}</strong>
                    <span>{{ $typeLabels[$account->variant->type] }} · {{ StoreLabels::variantName($account->variant->name) }}</span>
                    <small>{{ $account->slots_count }} slot · {{ StoreLabels::date($account->created_at) }}</small>
                    <a href="{{ route('staff', $appFilter + ['varian' => $account->variant_id]) }}#stok-akun">Buka daftar akun varian</a>
                </div>
            @empty
                <p>Belum ada stok masuk.</p>
            @endforelse
            @include('staff.stock-pagination', ['pages' => $incoming, 'section' => 'stok masuk'])
        </section>
        <section class="panel stack" id="stok-keluar">
            <div><h2>Stok keluar</h2><p>Profil yang aksesnya sudah diberikan lewat pesanan.</p></div>
            @forelse($outgoing as $item)
                <div class="history-row stock-event">
                    <a href="{{ route('orders.show', $item->order) }}#item-{{ $item->id }}">{{ $item->order->reference }}</a>
                    <span>{{ $item->name }}</span>
                    <span>{{ $item->stockSlot->account->stockLabel() }} · {{ $item->stockSlot->label }}</span>
                    <small>{{ StoreLabels::date($item->delivered_at) }} · {{ StoreLabels::status($item->status) }}</small>
                </div>
            @empty
                <p>Belum ada stok yang diserahkan.</p>
            @endforelse
            @include('staff.stock-pagination', ['pages' => $outgoing, 'section' => 'stok keluar'])
        </section>
    </div>
@endif
