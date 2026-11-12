@php($contacts = \App\Models\WhatsappContact::orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")->orderBy('id')->get())
<section class="panel stack">
    <div><h2>Kontak bantuan WhatsApp</h2><p>Kontak aktif bisa dihubungi lewat tombol WhatsApp. Kontak nonaktif tampil abu-abu dan tidak bisa diklik.</p></div>
    @foreach($contacts as $contact)
        <details class="history-row">
            <summary>{{ $contact->name }} · {{ $contact->role === 'owner' ? 'Owner' : 'Admin' }} · {{ $contact->active ? 'Aktif' : 'Nonaktif' }}</summary>
            @include('staff.whatsapp-form', ['contact' => $contact])
            <form method="post" action="{{ route('staff.whatsapp.remove', $contact) }}"
                data-confirm-title="Hapus kontak WhatsApp?" data-confirm="Kontak {{ $contact->name }} akan dihapus dari daftar bantuan." data-confirm-label="Hapus kontak">
                @csrf
                @method('DELETE')
                <button class="button secondary account-remove">Hapus kontak</button>
            </form>
        </details>
    @endforeach
    <details class="history-row">
        <summary>Tambah kontak WhatsApp</summary>
        @include('staff.whatsapp-form', ['contact' => null])
    </details>
</section>
