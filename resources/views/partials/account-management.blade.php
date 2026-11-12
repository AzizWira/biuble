@php
    $newRole = $managedUser->role === 'admin' ? 'reseller' : 'admin';
@endphp
<div class="toolbar account-actions">
    <form method="post" action="{{ route('staff.account-role', $managedUser) }}"
        data-confirm-title="Jadikan {{ $newRole }}?"
        data-confirm="{{ $managedUser->name }} akan menjadi {{ $newRole }}. Riwayat dan saldo tetap tersimpan.{{ $newRole === 'reseller' && !$managedUser->phone ? ' Akun perlu mengisi nomor WhatsApp dan mengajukan akses belanja.' : '' }}"
        data-confirm-label="Jadikan {{ $newRole }}">
        @csrf
        <input type="hidden" name="role" value="{{ $newRole }}">
        <button class="button secondary">Jadikan {{ $newRole }}</button>
    </form>
    <form method="post" action="{{ route('staff.account-remove', $managedUser) }}"
        data-confirm-title="Hapus akun {{ $managedUser->role }}?"
        data-confirm="Akses {{ $managedUser->name }} akan dicabut. Riwayatnya tetap tersimpan dan emailnya bisa digunakan untuk pendaftaran baru."
        data-confirm-label="Hapus akun">
        @csrf
        <button class="button secondary account-remove">Hapus akun</button>
    </form>
</div>
