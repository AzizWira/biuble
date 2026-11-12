<?php

namespace App\Models;

class LoginProof extends StoreRecord
{
    public function files()
    {
        return $this->hasMany(SupportFile::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
