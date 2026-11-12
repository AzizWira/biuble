<?php

namespace App\Models;

class SupportCase extends StoreRecord
{
    public const CLOSED = ['resolved', 'rejected'];

    public function user() { return $this->belongsTo(User::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function messages() { return $this->hasMany(SupportMessage::class)->orderBy('id'); }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED, true);
    }
}
