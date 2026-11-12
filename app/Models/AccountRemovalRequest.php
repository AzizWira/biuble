<?php

namespace App\Models;

class AccountRemovalRequest extends StoreRecord
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
