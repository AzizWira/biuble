@extends('layout')
@section('title','Pesanan')
@section('body', 'orders-screen')
@section('content')
<div class="intro"><h1>Riwayat pesanan</h1></div>
@forelse($orders as $order)
    @php
        $progress = \App\Support\StoreLabels::orderProgress($order);
    @endphp
    <article class="panel order-summary status-row tone-{{ \App\Support\StoreLabels::tone($progress['status']) }}">
        <a class="order-link" href="{{ route('orders.show', $order) }}">
            <div class="transaction-heading">
                <strong>{{ $order->reference }}</strong>
                <span class="status-pill">{{ $progress['label'] }}</span>
            </div>
            <p>{{ \App\Support\StoreLabels::status($order->status) }} · Rp{{ number_format($order->total, 0, ',', '.') }} · {{ \App\Support\StoreLabels::date($order->created_at) }}</p>
        </a>
        @include('support.order-proof-summary')
    </article>
@empty
    <div class="panel"><p>Pesanan pertamamu akan muncul di sini.</p></div>
@endforelse
{{ $orders->links() }}
@endsection

