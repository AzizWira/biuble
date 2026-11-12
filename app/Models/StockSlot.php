<?php
namespace App\Models;

class StockSlot extends StoreRecord
{
    public const RESTOCKABLE_ORDER_STATUSES = ['refunded', 'cancelled', 'refunded_gateway'];

    public function account() { return $this->belongsTo(StockAccount::class, 'stock_account_id'); }

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

