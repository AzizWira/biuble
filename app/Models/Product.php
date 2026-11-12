<?php
namespace App\Models;

class Product extends StoreRecord
{
    public function category() { return $this->belongsTo(Category::class); }
    public function variants() { return $this->hasMany(Variant::class); }
}

