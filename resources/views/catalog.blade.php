@extends('layout')
@section('title', 'Katalog')
@section('body', 'catalog-screen')
@section('content')
<label class="search"><svg class="icon" aria-hidden="true"><use href="{{ asset('design/assets/ikon.svg') }}#cari"></use></svg><input id="search" type="search" placeholder="Cari aplikasi" aria-label="Cari aplikasi"></label>
<section>
    <div class="section-title"><h1>Kategori</h1><button class="text-button" type="button" data-category="">Lihat semua</button></div>
    <div class="categories">
        @foreach ($categories as $category)
            <button type="button" class="category" data-category="{{ $category->id }}" aria-pressed="false">
                <span class="category-mark" style="background:{{ $category->color }}" aria-hidden="true">
                    @if($category->image)<img src="{{ asset('storage/'.$category->image) }}" alt="">@else
                    <svg class="icon"><use href="{{ asset('design/assets/ikon.svg') }}#katalog"></use></svg>@endif
                </span>
                <span><strong>{{ $category->name }}</strong><small>{{ $products->where('category_id', $category->id)->count() }} aplikasi</small></span>
            </button>
        @endforeach
    </div>
</section>
<section>
    <div class="section-title"><h2>Pilih aplikasi</h2><span class="eyebrow">Katalog Biuble</span></div>
    <div class="products">
        @foreach ($products as $product)
            @php
                $hasOrderable = $product->variants->contains(fn ($variant) => $variant->isPurchasable());
                $canNavigate = $hasOrderable || (auth()->check() && in_array(auth()->user()->role, ['owner','admin']));
                $readyStock = $product->variants->where('delivery','stock')->sum(fn ($variant) => $variant->availableUnits());
            @endphp
            <{{ $canNavigate ? 'a' : 'div' }} class="product {{ $canNavigate ? '' : 'is-sold-out' }}" style="--card-color:{{ $product->color }}" @if($canNavigate) href="{{ route('product', $product->slug) }}" @else aria-disabled="true" @endif data-product="{{ $product->name }}" data-group="{{ $product->category_id }}">
                <span class="product-art">
                    @if($product->image)<img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}">@else<span>{{ mb_substr($product->name, 0, 1) }}</span>@endif
                </span>
                <span class="card-stitches" aria-hidden="true"></span><span class="product-info"><small>{{ $product->category->name }}</small><strong>{{ $product->name }}</strong>
                <span>{{ $product->variants->isNotEmpty() ? 'Mulai Rp'.number_format($product->variants->min(fn ($variant) => $variant->unitPrice()), 0, ',', '.') : 'Paket segera hadir' }}</span><span class="catalog-stock">{{ $readyStock > 0 ? 'Stok siap: '.$readyStock : ($hasOrderable ? 'Tersedia sesuai pesanan' : 'Stok habis') }}</span></span>
            </{{ $canNavigate ? 'a' : 'div' }}>
        @endforeach
    </div>
    <p id="empty" class="panel" hidden>Tidak ada aplikasi yang cocok dengan pencarianmu.</p>
</section>
<script>
const search = document.querySelector('#search');
const cards = [...document.querySelectorAll('[data-product]')];
const filters = [...document.querySelectorAll('[data-category]')];
let category = '';
function filterCatalog() {
    const term = search.value.trim().toLowerCase();
    for (const card of cards) {
        card.hidden = !card.dataset.product.toLowerCase().includes(term)
            || (category !== '' && card.dataset.group !== category);
    }
    const visible = cards.filter(card => !card.hidden);
    for (const card of cards) card.classList.toggle('last-visible', card === visible.at(-1));
    document.querySelector('#empty').hidden = visible.length > 0;
}
filterCatalog();
search.addEventListener('input', filterCatalog);
for (const button of filters) {
    button.addEventListener('click', () => {
        category = button.dataset.category;
        for (const filter of filters) {
            if (filter.hasAttribute('aria-pressed')) filter.setAttribute('aria-pressed', String(filter.dataset.category === category));
        }
        filterCatalog();
    });
}
</script>
@endsection


