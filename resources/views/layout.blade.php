<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · Biuble</title>
    <link rel="icon" href="{{ asset('design/assets/ikon-tab.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('design/biuble.css') }}">
    <link rel="stylesheet" href="{{ asset('design/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('store.css').'?v='.filemtime(public_path('store.css')) }}">
</head>
<body class="@yield('body')" @if(auth()->check() && auth()->user()->isReadOnly()) data-account-readonly="true" @endif>
<main class="store" @if(auth()->check() && request()->routeIs('wallet', 'orders.show')) data-updates-url="{{ route('updates') }}" data-updates-version="{{ \App\Services\Store::updates(auth()->id()) }}" @endif>
    <p class="notice" id="updates-notice" role="status" hidden>Ada perubahan status. <a href="{{ url()->full() }}">Muat ulang untuk melihatnya.</a></p>
    @if (!request()->routeIs('login', 'otp.*'))
    <header class="top">
        <a href="{{ route('catalog') }}"><img class="logo" src="{{ asset('design/assets/biuble-logo-transparent.png') }}" alt="Biuble"></a>
        <div class="toolbar">
            @auth
                @if (in_array(auth()->user()->role, ['owner', 'admin']))
                    <a class="button secondary" href="{{ route('staff') }}">Kelola toko</a>
                @endif
                <a class="button secondary" href="{{ route('account') }}">Akun</a>
            @else
                <a class="button secondary" href="{{ route('login') }}">Masuk</a>
            @endauth
        </div>
    </header>
    @endif
    @if (!request()->routeIs('login', 'otp.*'))
    <nav class="dock" aria-label="Navigasi utama">
        @php
            $staffNavigation = auth()->check() && in_array(auth()->user()->role, ['owner', 'admin'], true);
            $navigation = $staffNavigation
                ? ['staff' => ['Kelola toko', 'pesanan'], 'catalog' => ['Katalog', 'katalog'], 'account' => ['Akun', 'akun']]
                : ['catalog' => ['Katalog', 'katalog'], 'cart' => ['Keranjang', 'keranjang'], 'wallet' => ['Saldo', 'saldo'], 'orders' => ['Pesanan', 'pesanan'], 'account' => ['Akun', 'akun']];
        @endphp
        @foreach ($navigation as $route => [$label, $icon])
            <a href="{{ route($route) }}" @if(request()->routeIs($route, $route.'.*')) aria-current="page" @endif>
                <svg class="icon" aria-hidden="true"><use href="{{ asset('design/assets/ikon.svg') }}#{{ $icon }}"></use></svg><span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
    @endif
    @if(auth()->check() && auth()->user()->isReadOnly())
    <aside class="notice readonly-notice" role="status"><strong>Status akun: {{ \App\Support\StoreLabels::accountStatus(auth()->user()->account_status) }} · Hanya lihat</strong><p>Kamu tetap bisa melihat data yang tersedia untuk akunmu. Perubahan data dan transaksi baru dibatasi; hubungi owner untuk mengaktifkan kembali akses.</p>@if(auth()->user()->account_status_reason)<p>Alasan: {{ auth()->user()->account_status_reason }}</p>@endif</aside>
    @endif
    @if (session('status') && !session('stock_feedback_target'))<p class="notice" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any() && !session('stock_feedback_target'))
        <div class="notice form-error" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif
    @if(auth()->check() && app()->isLocal() && \App\Services\Store::setting('local_audit_data') === '1')<p class="demo-notice">Mode audit lokal · Data berlabel DEMO dan saldo contoh adalah simulasi.</p>@endif
    @yield('content')

</main>
@unless(request()->routeIs('login', 'otp.*') || request()->boolean('invoice'))
    @include('partials.whatsapp')
@endunless
<dialog class="confirm-dialog" data-confirm-dialog aria-labelledby="confirm-title" aria-describedby="confirm-copy">
    <form method="dialog" class="stack">
        <h2 id="confirm-title">Konfirmasi tindakan</h2>
        <p id="confirm-copy"></p>
        <div class="confirm-actions">
            <button class="button secondary" value="cancel" autofocus>Batal</button>
            <button class="button" value="accept" data-confirm-accept>Lanjutkan</button>
        </div>
    </form>
</dialog>
<script src="{{ asset('shop.js').'?v='.filemtime(public_path('shop.js')) }}" defer></script>
</body></html>


