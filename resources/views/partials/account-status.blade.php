<details class="account-status-editor"><summary>Status akun: <span class="badge {{ $managedUser->isReadOnly() ? 'problem' : 'ready' }}">{{ \App\Support\StoreLabels::accountStatus($managedUser->account_status) }}</span><span class="status-edit-hint">Ubah status</span></summary>
<form class="editor-form" method="post" action="{{ route('staff.account-status',$managedUser) }}">@csrf
<label class="field">Status akun<select name="account_status">@foreach(['active'=>'Aktif','suspended'=>'Suspensi','inactive'=>'Nonaktif'] as $value=>$label)<option value="{{ $value }}" @selected($managedUser->account_status === $value)>{{ $label }}</option>@endforeach</select></label>
<label class="field">Alasan pembatasan (opsional)<input name="account_status_reason" maxlength="255" value="{{ $managedUser->account_status_reason }}" placeholder="Alasan yang akan dilihat pemilik akun"></label>
<p class="span-full">Akun suspensi/nonaktif tetap bisa login dan melihat data. Perubahan data dan transaksi baru dinonaktifkan. Aktifkan kembali untuk memulihkan akses.</p>
<button class="button">Simpan status akun</button>
</form></details>
