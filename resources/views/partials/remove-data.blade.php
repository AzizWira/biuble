@if(auth()->user()->role === 'owner')
<form class="remove-data-form" method="post" action="{{ route('staff.catalog.remove', ['kind' => $removeKind, 'id' => $removeId]) }}" data-confirm="Hapus {{ $removeLabel }} dari daftar aktif? Data di dalamnya ikut dikeluarkan. Riwayat transaksi tetap tersimpan; stok yang sedang dipesan atau dipakai harus diselesaikan terlebih dahulu." data-confirm-title="Hapus {{ $removeLabel }}?" data-confirm-label="Hapus data">
@csrf @method('delete')<button class="button secondary danger">Hapus {{ $removeLabel }}</button>
</form>
@endif
