<?php
namespace App\Models;

class Withdrawal extends StoreRecord
{
    protected function casts(): array { return ['allocation' => 'array']; }
    public function user() { return $this->belongsTo(User::class); }
}

