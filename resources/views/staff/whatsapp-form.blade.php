<form class="editor-form" method="post" action="{{ route('staff.whatsapp.save', $contact?->id) }}">
    @csrf
    <label class="field">Nama yang ditampilkan<input name="name" value="{{ $contact?->name }}" maxlength="80" required></label>
    <label class="field">Peran<select name="role"><option value="owner" @selected($contact?->role === 'owner')>Owner</option><option value="admin" @selected($contact?->role === 'admin')>Admin</option></select></label>
    <label class="field">Nomor WhatsApp<input name="number" type="tel" value="{{ $contact ? '+'.$contact->number : '' }}" placeholder="08… atau +62…" maxlength="30" required></label>
    <label class="field">Status<select name="active"><option value="1" @selected(!$contact || $contact->active)>Aktif</option><option value="0" @selected($contact && !$contact->active)>Nonaktif</option></select></label>
    <button class="button">{{ $contact ? 'Simpan kontak' : 'Tambah kontak' }}</button>
</form>
