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
    @forelse($cases as $case)
        <a class="stock-account-link" href="{{ route('support.show', $case) }}">
            <span>
                <strong>#{{ $case->id }} · {{ $case->subject }}</strong>
                <small>{{ $staff ? $case->user->name.' · ' : '' }}{{ $case->order ? $case->order->reference.' · ' : '' }}{{ \App\Support\StoreLabels::date($case->updated_at) }}</small>
            </span>
            <span class="badge">{{ \App\Support\StoreLabels::supportStatus($case->status) }}</span>
        </a>
    @empty
        <p>Belum ada pengajuan.</p>
    @endforelse
    @include('staff.stock-pagination', ['pages' => $cases, 'section' => 'pengajuan'])
</section>
@if($proofs)
    <section class="panel stack">
        <h2>Bukti login menunggu pemeriksaan</h2>
        @forelse($proofs as $proof)
            <a class="stock-account-link" href="{{ route('orders.show', $proof->orderItem->order_id) }}#item-{{ $proof->order_item_id }}">
                <span><strong>{{ $proof->orderItem->name }}</strong><small>{{ $proof->orderItem->order->reference }} · Item #{{ $proof->order_item_id }}</small></span>
                <span>Periksa bukti</span>
            </a>
        @empty
            <p>Semua bukti login sudah diperiksa.</p>
        @endforelse
        @include('staff.stock-pagination', ['pages' => $proofs, 'section' => 'bukti login'])
    </section>
@endif
@endsection
