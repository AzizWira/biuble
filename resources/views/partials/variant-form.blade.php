@if(auth()->user()->role === 'admin')
<form class="editor-form" method="post" action="{{ route('staff.variant',$variant->id) }}">@csrf
<input type="hidden" name="product_id" value="{{ $variant->product_id }}"><input type="hidden" name="name" value="{{ $variant->name }}">
<input type="hidden" name="type" value="{{ $variant->type }}"><input type="hidden" name="delivery" value="{{ $variant->delivery }}"><input type="hidden" name="discount_percent" value="{{ $variant->discount_percent }}">
<label class="field">Harga rupiah<input name="price" type="number" min="1" max="1000000000" value="{{ $variant->price }}" required></label>
<p class="span-full">Admin dapat mengubah harga. Nama paket, warna, durasi, dan pengaturan lainnya dikelola owner.</p>
<button class="button">Simpan harga</button>
</form>
@else
<form class="editor-form" method="post" action="{{ route('staff.variant',$variant?->id) }}">@csrf
<label class="field">Produk<select name="product_id">@foreach($products as $productOption)<option value="{{ $productOption->id }}" @selected(($variant?->product_id ?? $selectedProduct)===$productOption->id)>{{ $productOption->name }}</option>@endforeach</select></label>
<label class="field">Nama varian<input name="name" value="{{ $variant?->name }}" maxlength="180" required></label>
<fieldset class="segmented span-full"><legend>Tipe paket</legend>
<label><input name="type" type="radio" value="sharing" @checked(!$variant || $variant->type==='sharing') required><span>Sharing</span></label>
<label><input name="type" type="radio" value="private" @checked($variant?->type==='private') required><span>Private</span></label>
</fieldset>
<label class="field">Penyerahan<select name="delivery">@foreach(['stock'=>'Otomatis dari stok','manual'=>'Akun disiapkan setelah bayar','invite'=>'Undangan email'] as $key=>$text)<option value="{{ $key }}" @selected($variant?->delivery===$key)>{{ $text }}</option>@endforeach</select></label>
<label class="field">Warna paket<input name="color" type="color" value="{{ $variant?->color ?? '#def3f3' }}"></label>
<label class="field">Harga rupiah<input name="price" type="number" min="1" max="1000000000" value="{{ $variant?->price }}" required></label>
<label class="field">Durasi hari, kosong untuk lifetime<input name="duration_days" type="number" min="1" max="3660" value="{{ $variant?->duration_days }}"></label>
<label class="field">Garansi (hari, kosong jika tanpa garansi)<input name="warranty_days" type="number" min="1" max="3660" value="{{ $variant?->warranty_days }}"></label>
<label class="field">Bukti login dalam 24 jam<select name="login_proof_required" required><option value="0" @selected(!$variant?->login_proof_required)>Tidak wajib</option><option value="1" @selected($variant?->login_proof_required)>Wajib</option></select></label>
<label class="field">Diskon %<input name="discount_percent" type="number" min="0" max="99" value="{{ $variant?->discount_percent ?? 0 }}" required></label>
<label><input name="designer" type="checkbox" value="1" @checked($variant?->designer)> Izinkan Designer, khusus Canva Member</label>
<label><input name="active" type="checkbox" value="1" @checked(!$variant || $variant->active)> Aktif</label>
@if(auth()->user()->role === 'admin')<p>Akun admin hanya dapat menyimpan perubahan harga.</p>@endif
<button class="button">Simpan varian</button>
</form>


@endif
