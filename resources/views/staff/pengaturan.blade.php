<section id="pengaturan" class="panel"><h2>Biaya dan rekening transfer</h2>
<form class="stack" method="post" action="{{ route('staff.settings') }}">@csrf
@foreach(['topup'=>'Top-up otomatis DOKU','checkout'=>'Checkout langsung DOKU'] as $flow=>$label)
<fieldset><legend>{{ $label }}</legend><label class="field">Jenis biaya<select name="{{ $flow }}_fee_type">@foreach(['fixed'=>'Nominal rupiah','percent'=>'Persentase'] as $key=>$text)<option value="{{ $key }}" @selected(\App\Services\Store::setting($flow.'_fee_type','fixed')===$key)>{{ $text }}</option>@endforeach</select></label><label class="field">Nilai<input name="{{ $flow }}_fee_value" type="number" min="0" max="100000" value="{{ \App\Services\Store::setting($flow.'_fee_value','0') }}" required></label></fieldset>
@endforeach
<p>Aktifkan biaya hanya untuk kanal yang sudah mengizinkan skema layanan ini. MDR QRIS dan biaya kartu tidak diteruskan ke reseller.</p>
@foreach(config('doku.channels') as $key=>$label)
@if(!in_array($key,['QRIS','CREDIT_CARD']))
<label><input type="checkbox" name="fee_channels[]" value="{{ $key }}" @checked(in_array($key,json_decode(\App\Services\Store::setting('fee_channels','[]'),true)))> Skema biaya {{ $label }} sudah dikonfirmasi</label>@endif
@endforeach
<label class="field">Biaya sekali per penarikan yang mencakup refund pesanan<input name="withdraw_fee" type="number" min="0" max="100000" value="{{ \App\Services\Store::setting('withdraw_fee','1000') }}" required></label>
<p>Saldo top-up dan dana pembatalan tetap bebas biaya penarikan.</p>
<label class="field">Rekening dan petunjuk top-up manual<textarea name="transfer_instructions" maxlength="2000" required>{{ \App\Services\Store::setting('transfer_instructions') }}</textarea></label>
<button class="button">Simpan pengaturan</button>
</form></section>
@include('staff.whatsapp')
