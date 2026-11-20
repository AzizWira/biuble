<form class="editor-form" method="post" action="{{ route('staff.information.save', $announcement?->id) }}" data-duration-form>@csrf
    <label class="field span-full">Judul<input name="title" maxlength="150" value="{{ $announcement?->title }}" required></label>
    <label class="field span-full">Isi pengumuman<textarea name="body" rows="5" maxlength="5000" required>{{ $announcement?->body }}</textarea><small>Bisa memuat beberapa aplikasi sekaligus. Gunakan baris baru atau daftar untuk merapikan isi.</small></label>
    <label class="field">Mulai tayang (WIB)<input name="starts_at" type="datetime-local" value="{{ ($announcement?->starts_at ?? now())->format('Y-m-d\TH:i') }}" required></label>
    <label class="field">Masa tayang<select name="until" data-until-select><option value="manual" @selected(!$announcement?->ends_at)>Sampai dinonaktifkan</option><option value="duration">Durasi tertentu</option><option value="date" @selected($announcement?->ends_at)>Tanggal dan jam tertentu</option></select></label>
    <label class="field" data-until="duration" hidden>Durasi dalam jam<input name="hours" type="number" min="1" max="8760" value="24"></label>
    <label class="field" data-until="date">Selesai tayang (WIB)<input name="ends_at" type="datetime-local" value="{{ $announcement?->ends_at?->format('Y-m-d\TH:i') }}"></label>
    <label class="check-label"><input type="checkbox" name="published" value="1" @checked(!$announcement || $announcement->published)> Aktifkan pengumuman</label>
    @if($announcement)<label class="check-label"><input type="checkbox" name="notify_again" value="1"> Tandai sebagai pembaruan baru untuk reseller</label>@endif
    <button class="button">{{ $announcement ? 'Simpan pengumuman' : 'Buat pengumuman' }}</button>
</form>
