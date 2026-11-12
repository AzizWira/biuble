@extends('layout')
@section('title', 'Detail pengajuan')
@section('content')
@include('support.links')
<section class="panel stack">
    <div>
        <a href="{{ route('support.index', $case->kind) }}">Kembali ke daftar pengajuan</a>
        <h1>{{ $case->subject }}</h1>
        <p>{{ $case->kind === 'garansi' ? 'Pengajuan Garansi' : 'Pengaduan Masalah' }} #{{ $case->id }} · {{ $case->user->name }}</p>
        <span class="badge">{{ \App\Support\StoreLabels::supportStatus($case->status) }}</span>
    </div>
    @if($staff)
        <div>@include('support.contact-reseller', ['phone' => $case->order?->phone ?: $case->user->phone])</div>
    @endif
    @if($case->order)
        <p>Pesanan <a href="{{ route('orders.show', $case->order) }}{{ $case->order_item_id ? '#item-'.$case->order_item_id : '' }}">{{ $case->order->reference }}</a>
        @if($case->orderItem) · Item #{{ $case->orderItem->id }} · {{ $case->orderItem->name }} @endif</p>
    @endif
    @foreach($messages as $message)
        <article class="history-row stack">
            <strong>{{ $message->user->name }} · {{ \App\Support\StoreLabels::date($message->created_at) }}</strong>
            <p class="support-message">{{ $message->body }}</p>
            @foreach($message->files as $file)
                @include('support.attachment', ['label' => 'bukti masalah'])
            @endforeach
        </article>
    @endforeach
    @include('staff.stock-pagination', ['pages' => $messages, 'section' => 'balasan'])
    @unless($case->isClosed())
        <form class="stack support-item" method="post" action="{{ route('support.reply', $case) }}" enctype="multipart/form-data">
            @csrf
            <label class="field">Balasan<textarea name="body" maxlength="3000" required></textarea></label>
            <label class="field">Bukti tambahan (opsional, maksimal 2 MB)<input type="file" name="evidence" accept="image/jpeg,image/png,image/webp"></label>
            <button class="button secondary">Kirim balasan</button>
        </form>
    @endunless
</section>
@if($staff && !$case->isClosed())
    <section class="panel stack">
        @if($case->orderItem?->login_proof_required)
            <h2>Bukti login</h2>
            @include('support.proof', ['item' => $case->orderItem, 'buyer' => false])
        @endif
        <h2>Tangani pengajuan</h2>
        <form class="stack" method="post" action="{{ route('support.review', $case) }}">
            @csrf
            <label class="field">Status<select name="status">
                @foreach(['reviewing','waiting_customer','waiting_owner'] as $status)
                    <option value="{{ $status }}" @selected($case->status === $status)>{{ \App\Support\StoreLabels::supportStatus($status) }}</option>
                @endforeach
                @if(auth()->user()->role === 'owner' || $case->kind === 'masalah')<option value="resolved">Selesai</option>@endif
                @if(auth()->user()->role === 'owner')<option value="rejected">Ditolak</option>@endif
            </select></label>
            <label class="field">Catatan untuk reseller<textarea name="body" maxlength="3000" required></textarea></label>
            @if($case->order && auth()->user()->role === 'owner')
                <p>Jika kasus membutuhkan refund, proses dari menu Pesanan sebelum menandai pengajuan selesai.</p>
            @endif
            <button class="button">Simpan status</button>
        </form>
    </section>
@endif
@endsection
