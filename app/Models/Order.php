<?php
namespace App\Models;

class Order extends StoreRecord
{
    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'expires_at' => 'datetime'];
    }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function payment() { return $this->hasOne(Payment::class, 'target_id')->where('kind', 'order'); }
}

