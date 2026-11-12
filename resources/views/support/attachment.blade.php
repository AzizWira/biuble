<div class="toolbar support-attachment">
    <a class="button secondary" href="{{ route('support.file', ['file' => $file, 'lihat' => 1]) }}" target="_blank" rel="noopener">Lihat {{ $label }}</a>
    <a class="button secondary" href="{{ route('support.file', $file) }}">Unduh {{ $label }}</a>
    @if($staff && !auth()->user()->isReadOnly())
        <form method="post" action="{{ route('support.file.delete', $file) }}"
            data-confirm-title="Hapus gambar bukti?" data-confirm="Gambar akan dihapus dari pengajuan dan penyimpanan." data-confirm-label="Hapus gambar">
            @csrf
            @method('DELETE')
            <button class="button secondary account-remove">Hapus gambar</button>
        </form>
    @endif
</div>
