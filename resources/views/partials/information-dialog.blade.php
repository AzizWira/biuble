@if(auth()->check() && auth()->user()->role === 'reseller' && !request()->boolean('invoice'))
<dialog class="information-dialog" data-information-dialog data-signature="{{ $unreadInformation->map(fn ($a) => $a->id.'-'.$a->revision)->implode(',') }}">
    <div class="information-dialog-head"><h2>Pusat informasi</h2><button class="dialog-close" type="button" data-information-close aria-label="Tutup pusat informasi">&times;</button></div>
    <div class="information-dialog-body stack">
    @forelse($unreadInformation as $announcement)
        @include('partials.announcement-card')
    @empty
        <p>Semua informasi terbaru sudah dibaca.</p>
    @endforelse
    </div>
    <div class="information-dialog-footer transaction-actions"><a class="button secondary" href="{{ route('information') }}">Lihat semua informasi</a>
    @if($unreadInformation->isNotEmpty())<form method="post" action="{{ route('information.read') }}" data-information-read-all>@csrf<button class="button">Tandai semua sudah dibaca</button></form>@endif</div>
</dialog>
@endif
