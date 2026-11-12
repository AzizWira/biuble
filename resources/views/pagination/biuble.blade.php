@if ($paginator->hasPages())
<nav class="pagination" aria-label="Navigasi halaman">
@if($paginator->onFirstPage())<span aria-disabled="true">Sebelumnya</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif
@foreach($elements as $element)
@if(is_string($element))<span>{{ $element }}</span>@else
@foreach($element as $page => $url)
@if($page === $paginator->currentPage())<span aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>@endif
@endforeach
@endif
@endforeach
@if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>@else<span aria-disabled="true">Berikutnya</span>@endif
</nav>
@endif
