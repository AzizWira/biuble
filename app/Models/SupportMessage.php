<?php

namespace App\Models;

class SupportMessage extends StoreRecord
{
    public function user() { return $this->belongsTo(User::class); }
    public function supportCase() { return $this->belongsTo(SupportCase::class); }
    public function files() { return $this->hasMany(SupportFile::class); }
}
