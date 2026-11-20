<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends StoreRecord
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'published' => 'boolean'];
    }

    public function scopeLive($query)
    {
        return $query->where('published', true)->where('starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function scopeUnreadFor($query, int $user)
    {
        return $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('announcement_reads')
            ->whereColumn('announcement_id', 'announcements.id')->where('user_id', $user)
            ->whereColumn('announcement_reads.revision', '>=', 'announcements.revision'));
    }

    public function displayStatus(): string
    {
        if (! $this->published) return 'Nonaktif / draf';
        if ($this->starts_at->isFuture()) return 'Terjadwal';
        return $this->ends_at?->isPast() ? 'Berakhir' : 'Tayang';
    }
}
