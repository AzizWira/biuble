@php
    use App\Support\StoreLabels;
    $accountList = $appFilter + ['varian' => $variant->id] + request()->only('page');
    $account = $selectedAccount;
@endphp
<section class="panel stack" id="stok-detail">
    <div>
        <h2>{{ $product->name }} · {{ $typeLabels[$variant->type] }} · {{ StoreLabels::variantName($variant->name) }}</h2>
        <p>Stok dihitung per profil. Satu akun dapat berisi beberapa profil.</p>
        @unless($variant->active)<p>Varian nonaktif di katalog.</p>@endunless
    </div>
    <dl class="stock-stats">
        @foreach(['available' => 'Siap dijual', 'reserved' => 'Dipesan, belum bayar', 'used' => 'Sedang dipakai', 'ending' => 'Habis dalam 3 hari', 'inspection' => 'Menunggu pemeriksaan'] as $key => $label)
            <div class="stock-stat stock-stat-{{ $key }}"><dt>{{ $label }}</dt><dd>{{ $counts[$key] ?? 0 }}</dd></div>
        @endforeach
    </dl>
    @unless($selectedAccount)
    <div id="stok-akun">
        <h3>Daftar akun</h3>
        <p>Pilih akun untuk melihat detail akses dan status setiap slot.</p>
    </div>
    <div id="stok-tambah">
        @include('staff.stock-feedback', ['target' => 'stok-tambah'])
        <details class="stock-add" @if(old('variant_id') == $variant->id && $errors->any()) open @endif>
        <summary class="button"><span aria-hidden="true">+</span> Tambah akun dan slot</summary>
        <form class="stack" method="post" action="{{ route('staff.stock') }}">
            @csrf
            <input type="hidden" name="variant_id" value="{{ $variant->id }}">
            <label class="field">Nama penanda akun (opsional)<input name="label" maxlength="100" value="{{ old('label') }}" placeholder="Contoh: Netflix Harian A"></label>
            <label class="field">Detail akses akun<textarea name="credentials" maxlength="8000" required placeholder="Email, kata sandi, dan petunjuk akses"></textarea></label>
            <label class="field">Jumlah slot profil<input name="slots" type="number" min="1" max="100" value="{{ old('slots', 1) }}" required></label>
            <button class="button">Simpan akun dan slot</button>
        </form>
        </details>
    </div>
    @endunless
    @if($selectedAccount)
        <div id="stok-akun">
            <a class="button secondary" href="{{ route('staff', $accountList) }}#stok-akun"><span aria-hidden="true">←</span> Kembali ke daftar akun</a>
        </div>
        <article class="stock-account stack">
            <div class="stock-account-header">
                <div>
                    <h3>{{ $account->stockLabel() }}</h3>
                    <p>{{ $account->slots->count() }} profil · Ditambahkan {{ StoreLabels::date($account->created_at) }}</p>
                </div>
                <button type="button" class="button secondary" data-stock-access="{{ route('staff.stock-access', $account) }}"
                    data-account-label="{{ $account->stockLabel() }}" aria-haspopup="dialog">Lihat detail akses</button>
            </div>
            <div>
                <h3>Status setiap slot</h3>
                <p>Setelah menutup akses lama dan memeriksa profil, pilih “Jadikan siap dijual” untuk memasukkannya kembali ke stok.</p>
            </div>
            @foreach($account->slots as $slot)
                @php
                    $item = $slot->latestOrderItem;
                    $canRestock = $slot->canRestock($now);
                    $ending = $slot->state === 'used' && $item?->status === 'delivered'
                        && $item->ends_at && $item->ends_at->gt($now) && $item->ends_at->lte($soon);
                    $status = match (true) {
                        $slot->state === 'available' => 'Siap dijual',
                        $canRestock => 'Perlu diperiksa',
                        $ending => 'Segera habis',
                        $slot->state === 'used' => 'Sedang dipakai',
                        default => StoreLabels::status($slot->state),
                    };
                @endphp
                <div class="stock-slot" id="stok-profil-{{ $slot->id }}">
                    @include('staff.stock-feedback', ['target' => 'stok-profil-'.$slot->id])
                    <div class="stock-event">
                        <strong>{{ $slot->label }}</strong>
                        <small>{{ $slot->was_delivered ? 'Pernah terjual' : 'Belum terjual' }}</small>
                        @if($slot->state !== 'available' && $item?->order)
                            <a href="{{ route('orders.show', $item->order) }}#item-{{ $item->id }}">{{ $item->order->reference }}</a>
                            @if($slot->state === 'reserved')
                                <small>Batas bayar {{ StoreLabels::date($item->order->expires_at) }}</small>
                            @elseif($item->ends_at)
                                <small>{{ $item->ends_at->lte($now) ? 'Akses berakhir' : 'Akses sampai' }} {{ StoreLabels::date($item->ends_at) }}</small>
                            @endif
                        @endif
                    </div>
                    <div class="stock-slot-actions">
                        <span class="badge {{ $slot->state === 'available' ? 'ready' : '' }} {{ $ending ? 'stock-stat-ending' : '' }} {{ $canRestock ? 'stock-stat-inspection' : '' }}">{{ $status }}</span>
                        @if($canRestock)
                            <form method="post" action="{{ route('staff.recycle', $slot) }}"
                                data-confirm="Sudah menutup akses pembeli sebelumnya dan memastikan profil ini siap diberikan ke pembeli berikutnya?"
                                data-confirm-title="Jadikan profil siap dijual?" data-confirm-label="Jadikan siap dijual">
                                @csrf
                                <button class="button secondary">Jadikan siap dijual</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </article>
    @else
        @forelse($accounts as $account)
            <a class="stock-account-link" href="{{ route('staff', $accountList + ['akun' => $account->id]) }}#stok-akun">
                <span>
                    <strong>{{ $account->stockLabel() }}</strong>
                    <small>{{ $account->slots_count }} slot · Ditambahkan {{ StoreLabels::date($account->created_at) }}</small>
                </span>
                <span class="badge ready">{{ $account->ready_slots_count }} siap dijual</span>
            </a>
        @empty
            <p class="history-row">Belum ada akun untuk varian ini. Gunakan “Tambah akun dan slot” untuk mengisi stok.</p>
        @endforelse
        @include('staff.stock-pagination', ['pages' => $accounts, 'section' => 'daftar akun'])
    @endif
</section>
@if($selectedAccount)
<dialog class="confirm-dialog stock-access-dialog" data-stock-access-dialog aria-labelledby="stock-access-title">
    <div class="stack">
        <h2 id="stock-access-title">Detail akses akun</h2>
        <p data-stock-access-status role="status"></p>
        <pre class="access" data-stock-access-content hidden></pre>
        <div class="confirm-actions"><button type="button" class="button secondary" data-stock-access-close autofocus>Tutup</button></div>
    </div>
</dialog>
@endif
