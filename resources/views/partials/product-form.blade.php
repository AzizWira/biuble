<form class="editor-form" method="post" enctype="multipart/form-data" action="{{ route('staff.product',$product?->id) }}">@csrf
<label class="field">Nama produk<input name="name" value="{{ $product?->name }}" maxlength="100" required></label>
<label class="field">Kategori<select name="category_id">@foreach($categories as $category)<option value="{{ $category->id }}" @selected($product?->category_id===$category->id)>{{ $category->name }}</option>@endforeach</select></label>
<label class="field span-full">Deskripsi<textarea name="description" maxlength="3000" required>{{ $product?->description }}</textarea></label>
<label class="field">Warna kartu<input type="color" name="color" value="{{ $product?->color ?? '#def3f3' }}" required></label>
<label class="field">Foto produk<input name="image" type="file" accept=".jpg,.jpeg,.png,.webp"></label>
<label class="field">Mode stok aplikasi<select name="stock_mode" required><option value="slots" @selected($product?->stock_mode !== 'profiles')>Tanpa profil - akun dibagi beberapa slot</option><option value="profiles" @selected($product?->stock_mode === 'profiles')>Dengan profil dan PIN per profil</option></select></label>
<label class="field">Bukti login dalam 24 jam<select name="login_proof_required" required><option value="0" @selected(!$product?->login_proof_required)>Tidak wajib</option><option value="1" @selected($product?->login_proof_required)>Wajib - gambar, perangkat, dan lokasi</option></select><small>Berlaku untuk pembelian baru. Syarat pesanan sebelumnya tetap mengikuti saat pembelian.</small></label>
@include('partials.announcement-option')
<label><input type="checkbox" name="active" value="1" @checked(!$product || $product->active)> Tampilkan di katalog</label><button class="button">Simpan produk</button>
</form>
@if($product)@include('partials.remove-data', ['removeKind' => 'product', 'removeId' => $product->id, 'removeLabel' => 'produk'])@endif
