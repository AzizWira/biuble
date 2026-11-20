<?php
namespace App\Models;

class Product extends StoreRecord
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    public function category() { return $this->belongsTo(Category::class)->withTrashed(); }
    public function promotions() { return $this->hasMany(Promotion::class); }
    public function variants() { return $this->hasMany(Variant::class); }
}

