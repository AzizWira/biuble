<?php
namespace App\Models;

class Variant extends StoreRecord
{
    public function product() { return $this->belongsTo(Product::class); }

    public function stockSlots()
    {
        return $this->hasManyThrough(StockSlot::class, StockAccount::class, 'variant_id', 'stock_account_id');
    }

    public function scopeWithAvailableStock($query)
    {
        return $query->withCount(['stockSlots as available_stock' => fn ($slots) => $slots->where('state', 'available')]);
    }

    public function availableUnits(): ?int
    {
        return $this->delivery === 'stock'
            ? (int) ($this->available_stock ?? $this->stockSlots()->where('state', 'available')->count())
            : null;
    }

    public function isPurchasable(): bool
    {
        return $this->active && ($this->delivery !== 'stock' || $this->availableUnits() > 0);
    }

    public function unitPrice(bool $designer = false): int
    {
        $price = max(1, intdiv($this->price * (100 - $this->discount_percent) + 50, 100));
        return $price + ($this->designer && $designer ? 1000 : 0);
    }
}

