<div class="stock-field">
    <span class="stock-field-label">{{ $label }}</span>
    <details class="stock-select {{ $expanded ? 'stock-select-inline' : '' }}" data-stock-select @if($expanded) open @endif>
        <summary aria-label="{{ $label }}: {{ $selected ?? 'belum dipilih' }}">{{ $selected ?? 'Pilih '.mb_strtolower($label) }}</summary>
        <div class="stock-options">
            <input type="search" placeholder="Cari {{ mb_strtolower($label) }}…" aria-label="Cari {{ mb_strtolower($label) }}" data-stock-search hidden>
            <div class="stock-option-list">
                @forelse($options as $option)
                    <a href="{{ $option['url'] }}" data-stock-option @if($option['current']) aria-current="true" @endif>
                        <strong>{{ $option['label'] }}</strong>
                        @isset($option['note'])<small>{{ $option['note'] }}</small>@endisset
                    </a>
                @empty
                    <p>Belum ada pilihan.</p>
                @endforelse
                <p data-stock-empty role="status" hidden>Tidak ada hasil pencarian.</p>
            </div>
        </div>
    </details>
</div>
