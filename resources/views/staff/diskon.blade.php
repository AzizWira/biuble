<section class="panel stack"><h2>Diskon dan promo</h2><p>Atur potongan harga dan waktu tayang. Promo aktif tampil di atas kategori pada beranda.</p>
<details><summary class="button">+ Tambah diskon</summary>@include('staff.promotion-form', ['promotion' => null])</details>
@forelse($promotions as $promotion)
<details class="history-row"><summary>{{ $promotion->title }} <span class="badge">{{ !$promotion->active ? 'Nonaktif' : ($promotion->starts_at->isFuture() ? 'Terjadwal' : ($promotion->ends_at->isPast() ? 'Berakhir' : 'Tayang')) }}</span></summary>
@if($promotion->product)@include('staff.promotion-form')@else<p>Aplikasi terkait sudah dihapus.</p>@endif
<form method="post" action="{{ route('staff.promotion.remove', $promotion) }}" data-confirm="Hapus diskon ini? Harga akan mengikuti diskon lain yang masih berlaku atau harga normal.">@csrf @method('delete')<button class="button secondary danger">Hapus diskon</button></form>
</details>
@empty<p>Belum ada diskon. Gunakan tombol Tambah diskon untuk membuat promo.</p>@endforelse
{{ $promotions->links() }}
</section>
