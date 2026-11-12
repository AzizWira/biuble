<section id="penarikan" class="panel"><h2>Penarikan menunggu transfer</h2>
@forelse($withdrawals as $withdrawal)
<article class="history-row"><strong>#{{ $withdrawal->id }} · {{ $withdrawal->user->name }}</strong><p>{{ $withdrawal->destination }}</p>
<p>Nominal Rp{{ number_format($withdrawal->amount,0,',','.') }} · biaya Rp{{ number_format($withdrawal->fee,0,',','.') }} · transfer Rp{{ number_format($withdrawal->net,0,',','.') }}</p>
<p>Sumber @foreach($withdrawal->allocation as $source=>$value) {{ \App\Support\StoreLabels::source($source) }} Rp{{ number_format($value,0,',','.') }} @endforeach</p>
<form class="stack" method="post" action="{{ route('staff.withdrawal',$withdrawal) }}" data-confirm="Pastikan transfer sudah berhasil sebelum menandainya selesai.">@csrf
<label class="field">Referensi transfer<input name="reference" maxlength="120"></label><div class="toolbar"><button class="button" name="decision" value="approve">Sudah ditransfer</button><button class="button secondary" name="decision" value="reject">Tolak dan lepas dana</button></div>
</form></article>@empty<p>Belum ada pengajuan.</p>@endforelse
</section>
