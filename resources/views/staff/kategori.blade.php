<section id="kategori" class="panel"><h2>Kelola kategori</h2>
<details class="category-create"><summary class="button">Tambah kategori</summary>
<form class="editor-form" method="post" enctype="multipart/form-data" action="{{ route('staff.category') }}">@csrf
<label class="field">Nama kategori<input name="name" maxlength="80" required></label>
<label class="field">Warna kategori<input name="color" type="color" value="#def3f3" required></label>
<label class="field span-full">Foto kategori<input name="image" type="file" accept=".jpg,.jpeg,.png,.webp"><small>JPG, PNG, atau WebP, maksimal 3 MB.</small></label>
<button class="button">Simpan kategori baru</button>
</form></details>
<h3 class="category-list-title">Kategori yang sudah ada</h3>
@foreach($categories as $category)
<details class="history-row"><summary>{{ $category->name }}</summary>
<form class="stack" action="{{ route('staff.category',$category) }}" method="post" enctype="multipart/form-data">@csrf
<label class="field">Nama<input name="name" value="{{ $category->name }}" maxlength="80" required></label>
<label class="field">Warna<input name="color" type="color" value="{{ $category->color }}"></label>
<label class="field">Foto<input name="image" type="file" accept=".jpg,.jpeg,.png,.webp"></label>
<button class="button">Simpan kategori</button></form></details>
@endforeach</section>
