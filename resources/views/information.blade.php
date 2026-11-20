@extends('layout')
@section('title', 'Pusat informasi')
@section('content')
<div class="intro"><h1>Pusat informasi</h1><p>Pengumuman, stok terbaru, dan kabar dari tim Biuble.</p></div>
<div class="stack information-list">
@forelse($announcements as $announcement)
    @include('partials.announcement-card')
@empty
    <section class="panel"><p>Belum ada informasi yang sedang tayang.</p></section>
@endforelse
{{ $announcements->links() }}
</div>
@endsection
