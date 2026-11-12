<section id="topup" class="panel"><h2>Top-up transfer manual</h2>
@forelse($topups as $topup)
<article class="history-row"><strong>{{ $topup->user->name }} · {{ $topup->reference }}</strong><p>Periksa mutasi Rp{{ number_format($topup->amount,0,',','.') }} sebelum menyetujui.</p>
<div class="transaction-actions">
@if($topup->proof)<a href="{{ route('proof',$topup) }}">Unduh bukti transfer</a>@endif
<form class="toolbar" action="{{ route('staff.topup',$topup) }}" method="post" data-confirm="Sudah cocokkan transfer ini dengan mutasi rekening?">
    @csrf
    <button name="decision" value="approve" class="button" data-confirm-title="Setujui top-up?" data-confirm-label="Setujui">Setujui</button>
    <button name="decision" value="reject" class="button secondary" data-confirm="Pengajuan ini akan ditolak dan saldo reseller tidak bertambah." data-confirm-title="Tolak top-up?" data-confirm-label="Tolak pengajuan">Tolak</button>
</form>
</div>
</article>@empty<p>Semua top-up sudah diproses.</p>@endforelse</section>
