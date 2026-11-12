@if($pages->hasPages())
    <nav class="stock-pages" aria-label="Halaman {{ $section }}">
        @if($pages->previousPageUrl())<a href="{{ $pages->previousPageUrl() }}">Sebelumnya</a>@endif
        <span>Halaman {{ $pages->currentPage() }} dari {{ $pages->lastPage() }}</span>
        @if($pages->nextPageUrl())<a href="{{ $pages->nextPageUrl() }}">Berikutnya</a>@endif
    </nav>
@endif
