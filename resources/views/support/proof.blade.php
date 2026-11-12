@php
    $proof = $item->loginProof;
    $deadline = $item->loginProofDeadline();
    $proofStatus = ['submitted' => 'Menunggu pemeriksaan', 'accepted' => 'Diterima', 'correction' => 'Perlu diperbaiki'];
@endphp
@if(!$item->login_proof_required)
    @if($buyer)<p>Bukti login tidak diperlukan.</p>@endif
@elseif($proof)
    <div class="stack">
        <p><strong>Bukti login {{ mb_strtolower($proofStatus[$proof->status]) }}.</strong> Dikirim {{ \App\Support\StoreLabels::date($proof->created_at) }}.</p>
        @if($proof->review_note)<p>{{ $proof->review_note }}</p>@endif
        @foreach($proof->files->sortByDesc('id') as $file)
            <small>{{ \App\Support\StoreLabels::date($file->created_at) }}</small>
            @include('support.attachment', ['label' => 'bukti login'])
        @endforeach
        @if($staff && $proof->status === 'submitted')
            <form class="stack" method="post" action="{{ route('support.proof.review', $item) }}">
                @csrf
                <label class="field">Hasil pemeriksaan<select name="status"><option value="accepted">Terima bukti</option><option value="correction">Minta perbaikan</option></select></label>
                <label class="field">Catatan (wajib jika perlu perbaikan)<input name="review_note" maxlength="255"></label>
                <button class="button secondary">Simpan hasil pemeriksaan</button>
            </form>
        @endif
    </div>
@elseif(!$deadline)
    <p>Bukti login wajib. Waktu unggah 24 jam dimulai saat akses diserahkan.</p>
@elseif(now()->gt($deadline))
    <p>Batas 24 jam sudah lewat. Bukti login untuk item #{{ $item->id }} belum dikirim.@if($item->warranty_days) Garansi tidak dapat diajukan.@endif</p>
@else
    <p>Bukti login wajib. Unggah paling lambat <strong>{{ \App\Support\StoreLabels::date($deadline) }}</strong>.</p>
@endif

@if($buyer && $item->login_proof_required && $item->status === 'delivered' && $deadline
    && ($proof?->status === 'correction' || (!$proof && now()->lte($deadline))))
    <form class="stack" method="post" action="{{ route('support.proof.upload', $item) }}" enctype="multipart/form-data">
        @csrf
        <label class="field">Tangkapan layar login<input type="file" name="evidence" accept="image/jpeg,image/png,image/webp" required><small>JPG, PNG, atau WebP, maksimal 2 MB. Tutupi kata sandi, kode OTP, dan informasi pribadi.</small></label>
        <button class="button secondary">{{ $proof ? 'Kirim perbaikan bukti login' : 'Unggah bukti login' }}</button>
    </form>
@endif
