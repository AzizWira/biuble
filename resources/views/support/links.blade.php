<nav class="support-links" aria-label="Bantuan Biuble">
    <a class="button secondary" href="{{ route('support.index', 'garansi') }}" @if(request()->routeIs('support.index') && request()->route('kind') === 'garansi') aria-current="page" @endif>Pengajuan Garansi</a>
    <a class="button secondary" href="{{ route('support.index', 'masalah') }}" @if(request()->routeIs('support.index') && request()->route('kind') === 'masalah') aria-current="page" @endif>Pengaduan Masalah</a>
</nav>
