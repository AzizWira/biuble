<?php
namespace App\Models;

class StockSlot extends StoreRecord
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected function casts(): array { return ['pin' => 'encrypted']; }

    public const RESTOCKABLE_ORDER_STATUSES = ['refunded', 'cancelled', 'refunded_gateway'];

    public function account() { return $this->belongsTo(StockAccount::class, 'stock_account_id')->withTrashed(); }

    public function latestOrderItem()
    {
        return $this->hasOne(OrderItem::class)->latestOfMany();
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function canRestock(\DateTimeInterface $now): bool
    {
        $item = $this->latestOrderItem;

        return in_array($this->state, ['inspection', 'used'], true)
            && (! $item || in_array($item->status, self::RESTOCKABLE_ORDER_STATUSES, true)
                || ($item->ends_at && $item->ends_at->lte($now)));
    }
}

