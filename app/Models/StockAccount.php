<?php
namespace App\Models;

class StockAccount extends StoreRecord
{
    protected function casts(): array { return ['credentials' => 'encrypted']; }
    public function slots() { return $this->hasMany(StockSlot::class); }
    public function variant() { return $this->belongsTo(Variant::class); }

    public function stockLabel(): string
    {
        return $this->label ?: 'Akun #'.$this->id;
    }
}

