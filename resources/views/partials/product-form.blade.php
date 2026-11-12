<form class="editor-form" method="post" enctype="multipart/form-data" action="{{ route('staff.product',$product?->id) }}">@csrf
<label class="field">Nama produk<input name="name" value="{{ $product?->name }}" maxlength="100" required></label>
<label class="field">Kategori<select name="category_id">@foreach($categories as $category)<option value="{{ $category->id }}" @selected($product?->category_id===$category->id)>{{ $category->name }}</option>@endforeach</select></label>
<label class="field span-full">Deskripsi<textarea name="description" maxlength="3000" required>{{ $product?->description }}</textarea></label>
<label class="field">Warna kartu<input type="color" name="color" value="{{ $product?->color ?? '#def3f3' }}" required></label>
<label class="field">Foto produk<input name="image" type="file" accept=".jpg,.jpeg,.png,.webp"></label>
<label><input type="checkbox" name="active" value="1" @checked(!$product || $product->active)> Tampilkan di katalog</label><button class="button">Simpan produk</button>
</form>
