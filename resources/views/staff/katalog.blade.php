<section id="katalog" class="panel catalog-editor"><h2>Katalog dan varian</h2>
@foreach($products as $product)
<details class="history-row status-disclosure tone-{{ $product->active ? 'mint' : 'slate' }}"><summary>{{ $product->name }} <span class="status-pill">{{ $product->active ? 'Tayang' : 'Disembunyikan' }}</span></summary>
@if(auth()->user()->role === 'owner')
@include('partials.product-form',['product'=>$product])
@endif
@foreach($product->variants as $variant)
<details class="variant-editor status-disclosure tone-{{ $variant->active ? 'mint' : 'slate' }}"><summary><span>{{ $variant->name }} · Rp{{ number_format($variant->price,0,',','.') }}</span><span class="status-pill">{{ $variant->active ? 'Aktif' : 'Nonaktif' }}</span></summary>
@include('partials.variant-form',['variant'=>$variant,'selectedProduct'=>$product->id])
</details>@endforeach
</details>@endforeach
@if(auth()->user()->role === 'owner')

<details class="history-row"><summary>Tambah produk</summary>@include('partials.product-form',['product'=>null])</details>
<details class="history-row"><summary>Tambah varian</summary>@include('partials.variant-form',['variant'=>null,'selectedProduct'=>null])</details>
@endif
</section>
