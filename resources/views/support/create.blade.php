@extends('layout')
@section('title', $kind === 'garansi' ? 'Ajukan garansi' : 'Laporkan masalah')
@section('content')
<section class="panel stack">
    <div>
        <a href="{{ route('support.index', $kind) }}">Kembali ke daftar pengajuan</a>
        <h1>{{ $kind === 'garansi' ? 'Ajukan garansi' : 'Laporkan masalah' }}</h1>
        @if($item)<p>{{ $item->order->reference }} · Item #{{ $item->id }} · {{ $item->name }}</p>@endif
    </div>
    @if($blocked)
        <p class="notice">{{ $blocked }}</p>
        <a class="button secondary" href="{{ route('support.create', ['kind'=>'masalah', 'item'=>$item->id]) }}">Laporkan masalah</a>
    @elseif(!auth()->user()->isReadOnly())
        <form class="stack" method="post" action="{{ route('support.store', $kind) }}" enctype="multipart/form-data">
            @csrf
            @if($item)
                <input type="hidden" name="item" value="{{ $item->id }}">
            @else
                <label class="field">Nomor transaksi (opsional)<input name="reference" value="{{ old('reference') }}" maxlength="80"></label>
            @endif
            <label class="field">Judul masalah<input name="subject" value="{{ old('subject') }}" maxlength="120" required></label>
            <label class="field">Ceritakan kendalanya<textarea name="body" maxlength="3000" required>{{ old('body') }}</textarea></label>
            <label class="field">Bukti masalah {{ $kind === 'masalah' ? '(opsional)' : '' }}<input type="file" name="evidence" accept="image/jpeg,image/png,image/webp" @required($kind === 'garansi')><small>JPG, PNG, atau WebP, maksimal 2 MB. Tutupi kata sandi, kode OTP, dan informasi pribadi.</small></label>
            <button class="button">Kirim pengajuan</button>
        </form>
    @endif
</section>
@endsection
