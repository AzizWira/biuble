<form class="editor-form" method="post" action="{{ route('staff.promotion.save', $promotion?->id) }}" data-duration-form data-promotion-form>@csrf
<label class="field span-full">Judul promo<input name="title" maxlength="150" value="{{ $promotion?->title }}" placeholder="Contoh Promo akhir pekan" required></label>
<label class="field">Aplikasi<select name="product_id" data-promotion-product required>@foreach($products as $option)<option value="{{ $option->id }}" @selected($promotion?->product_id === $option->id)>{{ $option->name }}</option>@endforeach</select></label>
<label class="field">Varian<select name="variant_id" data-promotion-variant><option value="">Semua varian aplikasi</option>@foreach($products as $option)@foreach($option->variants as $variantOption)<option value="{{ $variantOption->id }}" data-product-id="{{ $option->id }}" @selected($promotion?->variant_id === $variantOption->id)>{{ $variantOption->name }}</option>@endforeach @endforeach</select></label>
<label class="field">Diskon (%)<input name="discount_percent" type="number" min="1" max="99" value="{{ $promotion?->discount_percent ?? 10 }}" required><small>Jika ada beberapa diskon, harga memakai potongan terbesar.</small></label>
<label class="field">Mulai (WIB)<input name="starts_at" type="datetime-local" value="{{ ($promotion?->starts_at ?? now())->format('Y-m-d\TH:i') }}" required></label>
<label class="field">Masa berlaku<select name="until" data-until-select><option value="duration" @selected(!$promotion)>Durasi tertentu</option><option value="date" @selected($promotion)>Tanggal dan jam tertentu</option></select></label>
<label class="field" data-until="duration">Durasi dalam jam<input name="hours" type="number" min="1" max="8760" value="24"></label>
<label class="field" data-until="date">Berakhir (WIB)<input name="ends_at" type="datetime-local" value="{{ $promotion?->ends_at?->format('Y-m-d\TH:i') }}"></label>
<label class="check-label"><input type="checkbox" name="active" value="1" @checked(!$promotion || $promotion->active)> Aktifkan diskon</label>
@include('partials.announcement-option')
<button class="button">Simpan diskon</button>
</form>
