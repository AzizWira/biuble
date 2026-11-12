<section class="panel stack">
    <div><h2>Pengajuan hapus akun</h2><p>Periksa alasan admin sebelum memutuskan apakah akses reseller perlu dicabut.</p></div>
    @forelse($removals as $removal)
        <article class="history-row stack">
            <div>
                <strong>{{ $removal->user->name }}</strong>
                <p>{{ $removal->user->email }}</p>
                <p>Diajukan oleh {{ $removal->requester->name }} · {{ \App\Support\StoreLabels::date($removal->created_at) }}</p>
                <p>Alasan: {{ $removal->reason }}</p>
            </div>
            <form method="post" class="toolbar" action="{{ route('staff.account-removal-review', $removal) }}"
                data-confirm="Setujui penghapusan akun ini?" data-confirm-title="Verifikasi penghapusan">
                @csrf
                <button class="button account-remove" name="decision" value="approved" data-confirm-label="Setujui dan hapus">Setujui dan hapus</button>
                <button class="button secondary" name="decision" value="rejected" data-confirm="Tolak pengajuan ini dan pertahankan akses reseller?" data-confirm-label="Tolak pengajuan">Tolak</button>
            </form>
        </article>
    @empty
        <p>Belum ada pengajuan yang menunggu keputusan.</p>
    @endforelse
    @include('staff.stock-pagination', ['pages' => $removals, 'section' => 'pengajuan hapus akun'])
</section>
