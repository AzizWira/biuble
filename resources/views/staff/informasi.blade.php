<section class="panel stack"><h2>Pusat informasi</h2><p>Buat pengumuman bebas, gabungkan informasi beberapa aplikasi, atau kelola pengumuman dari penambahan stok.</p>
<details><summary class="button">+ Buat pengumuman</summary>@include('staff.information-form', ['announcement' => null])</details>
@forelse($announcements as $announcement)
<details class="history-row"><summary>{{ $announcement->title }} <span class="badge">{{ $announcement->displayStatus() }}</span></summary>
    @include('staff.information-form')
    <form method="post" action="{{ route('staff.information.remove', $announcement) }}" data-confirm="Hapus pengumuman ini dari pusat informasi?">@csrf @method('delete')<button class="button secondary danger">Hapus pengumuman</button></form>
</details>
@empty<p>Belum ada pengumuman yang dapat kamu kelola.</p>@endforelse
{{ $announcements->links() }}
</section>
