<article class="information-card stack" data-information-card="{{ $announcement->id }}">
    <div class="section-title"><h3>{{ $announcement->title }}</h3><small>{{ \App\Support\StoreLabels::date($announcement->starts_at) }}</small></div>
    <div class="information-body">{!! \Illuminate\Support\Str::markdown($announcement->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
    @if($announcement->ends_at)<small>Tayang sampai {{ \App\Support\StoreLabels::date($announcement->ends_at) }} WIB</small>@endif
    <form method="post" action="{{ route('information.read') }}" data-information-read>@csrf
        <input type="hidden" name="announcement" value="{{ $announcement->id }}"><input type="hidden" name="revision" value="{{ $announcement->revision }}">
        <button class="button secondary">Tandai sudah dibaca</button>
    </form>
</article>
