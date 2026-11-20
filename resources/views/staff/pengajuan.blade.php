@if(auth()->user()->role === 'owner')
<section id="pengajuan" class="panel"><h2>Pengajuan reseller</h2>
@forelse($applicants as $applicant)
<form class="history-row toolbar status-row tone-amber" method="post" action="{{ route('staff.applicant',$applicant) }}">@csrf
@include('partials.attention') <span class="status-pill">Pengajuan baru</span><strong>{{ $applicant->name }}</strong><span>{{ $applicant->phone }}</span><span>{{ $applicant->email }}</span>
<button class="button" name="decision" value="approved">Setujui setelah cek grup</button><button class="button secondary" name="decision" value="rejected">Tolak</button>
</form>@empty<p>Semua pengajuan sudah ditangani.</p>@endforelse
</section>
@endif
<section class="panel"><h2>Daftar akun reseller</h2>
@forelse($resellers as $reseller)<article class="managed-account status-row tone-{{ \App\Support\StoreLabels::tone($reseller->isReadOnly() ? $reseller->account_status : $reseller->approval) }}"><div class="transaction-heading"><strong>{{ $reseller->name }}</strong><span class="status-pill tone-{{ \App\Support\StoreLabels::tone($reseller->approval) }}">Pengajuan: {{ \App\Support\StoreLabels::status($reseller->approval) }}</span></div><p>{{ $reseller->email }}@if($reseller->phone) · {{ $reseller->phone }}@endif</p>
@if(auth()->user()->role === 'owner')
    @include('partials.account-status', ['managedUser' => $reseller])
    @include('partials.account-management', ['managedUser' => $reseller])
@else
    <details class="account-actions">
        <summary class="button secondary">Ajukan hapus reseller</summary>
        <form class="stack" method="post" action="{{ route('staff.account-removal-request', $reseller) }}">
            @csrf
            <label class="field">Alasan penghapusan<input name="reason" maxlength="255" required></label>
            <button class="button">Kirim ke owner</button>
        </form>
    </details>
@endif
</article>@empty<p>Belum ada akun reseller.</p>@endforelse
{{ $resellers->links() }}
</section>
