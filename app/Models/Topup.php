<?php
namespace App\Models;

class Topup extends StoreRecord
{
    public function user() { return $this->belongsTo(User::class); }
    public function payment() { return $this->hasOne(Payment::class, 'target_id')->where('kind', 'topup'); }
}

