<span class="profile-head" aria-hidden="true"><img src="{{ asset('design/assets/'.($type === 'private' ? 'profil.svg' : 'profil-sharing.svg')) }}" alt=""></span>
<span class="variant-copy">
    <strong>{{ \App\Support\StoreLabels::variantName($variant->name) }}</strong>
    <span class="variant-description">{{ ['stock'=>'Akses dari stok akun', 'manual'=>'Disiapkan setelah bayar', 'invite'=>'Undangan melalui email'][$variant->delivery] }}</span>
    <span class="stock-availability {{ $variant->delivery === 'stock' && $variant->availableUnits() === 0 ? 'is-empty' : '' }}">@if($variant->delivery === 'stock'){{ $variant->availableUnits() > 0 ? 'Stok tersedia: '.$variant->availableUnits() : 'Stok habis' }}@else{{ $variant->delivery === 'invite' ? 'Bisa dipesan · dikirim admin' : 'Bisa dipesan · dibuat sesuai pesanan' }}@endif</span>
    <span class="variant-terms">{{ $variant->warranty_days ? 'Garansi '.$variant->warranty_days.' hari' : (str_contains(mb_strtolower($variant->name), 'dengan garansi') ? 'Masa garansi belum tercatat' : 'Tanpa garansi') }} · {{ $product->login_proof_required ? 'Bukti login wajib dalam 24 jam' : 'Bukti login tidak wajib' }}</span>
</span>
<span class="variant-price">@if($variant->effectiveDiscount())<del>Rp{{ number_format($variant->price, 0, ',', '.') }}</del>@endif<strong>Rp{{ number_format($variant->unitPrice(), 0, ',', '.') }}</strong>@if($variant->effectiveDiscount())<span class="badge ready">Diskon {{ $variant->effectiveDiscount() }}%</span>@endif</span>
