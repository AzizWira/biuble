@extends('layout')
@section('title', $kind === 'garansi' ? 'Pengajuan Garansi' : 'Pengaduan Masalah')
@section('content')
@include('support.links')
<section class="panel stack">
    <div class="support-heading">
        <h1>{{ $kind === 'garansi' ? 'Pengajuan Garansi' : 'Pengaduan Masalah' }}</h1>
        @if(!$staff && !auth()->user()->isReadOnly())
            @if($kind === 'masalah')
                <a class="button" href="{{ route('support.create', $kind) }}">Laporkan masalah</a>
            @else
                <a class="button secondary" href="{{ route('orders') }}">Pilih item dari pesanan</a>
            @endif
        @endif
    </div>
    <p class="attention-explanation">@if($staff)Badge menghitung pengajuan yang perlu tindakan role kamu. Menunggu reseller tidak dihitung; menunggu owner hanya dihitung untuk owner. Membuka pengajuan tidak menyelesaikannya.@else Badge menunjukkan pembaruan yang belum dibaca. Membuka detail pengajuan menghapus tanda baru, tanpa mengubah status pengajuan.@endif</p>
    @forelse($cases as $case)
        <a class="stock-account-link status-row tone-{{ \App\Support\StoreLabels::tone($case->status) }}" href="{{ route('support.show', $case) }}">
            <span>
                @if($staff && in_array($case->status, \App\Services\NavigationBadges::caseActionStatuses(auth()->user()), true))@include('partials.attention')@elseif(!$staff && in_array($case->id, $unreadCaseIds, true))@include('partials.attention', ['attentionLabel'=>'Pembaruan baru', 'attentionKind'=>'unread'])@endif<strong>#{{ $case->id }} · {{ $case->subject }}</strong>
                <small>{{ $staff ? $case->user->name.' · ' : '' }}{{ $case->order ? $case->order->reference.' · ' : '' }}{{ \App\Support\StoreLabels::date($case->updated_at) }}</small>
            </span>
            <span class="status-pill">{{ \App\Support\StoreLabels::supportStatus($case->status) }}</span>
        </a>
    @empty
        <p>Belum ada pengajuan.</p>
    @endforelse
    @include('staff.stock-pagination', ['pages' => $cases, 'section' => 'pengajuan'])
</section>
@if($proofs)
    <section class="panel stack" id="bukti-login">
        <h2>Bukti login menunggu pemeriksaan</h2>
        @forelse($proofs as $proof)
            <a class="stock-account-link status-row tone-amber" href="{{ route('orders.show', $proof->orderItem->order_id) }}#item-{{ $proof->order_item_id }}">
                <span>@include('partials.attention', ['attentionLabel'=>'Periksa bukti login', 'attentionKind'=>'proof'])<strong>{{ $proof->orderItem->name }}</strong><small>{{ $proof->orderItem->order->reference }} · Item #{{ $proof->order_item_id }}</small></span>
                <span class="status-pill">Periksa bukti</span>
            </a>
        @empty
            <p>Semua bukti login sudah diperiksa.</p>
        @endforelse
        @include('staff.stock-pagination', ['pages' => $proofs, 'section' => 'bukti login'])
    </section>
@endif
@endsection
