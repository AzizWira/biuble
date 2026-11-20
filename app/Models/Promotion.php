<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends StoreRecord
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'active' => 'boolean'];
    }

    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsTo(Variant::class); }

    public function scopeLive($query)
    {
        return $query->where('active', true)->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->whereHas('product', fn ($q) => $q->where('active', true))
            ->where(fn ($q) => $q->whereNull('variant_id')->orWhereHas('variant', fn ($v) => $v->where('active', true)));
    }
}
