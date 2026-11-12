<?php

namespace App\Models;

class SupportFile extends StoreRecord
{
    public function loginProof() { return $this->belongsTo(LoginProof::class); }
    public function supportMessage() { return $this->belongsTo(SupportMessage::class); }
}
