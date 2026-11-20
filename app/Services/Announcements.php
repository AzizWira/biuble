<?php

namespace App\Services;

use App\Models\Announcement;
use Illuminate\Http\Request;

class Announcements
{
    public static function validate(Request $request): array
    {
        return $request->validate([
            'announce' => 'nullable|boolean',
            'announcement_title' => 'required_if:announce,1|nullable|string|max:150',
            'announcement_body' => 'required_if:announce,1|nullable|string|max:5000',
            'announcement_until' => 'required_if:announce,1|nullable|in:manual,duration,date',
            'announcement_hours' => 'required_if:announcement_until,duration|nullable|integer|min:1|max:8760',
            'announcement_end' => 'required_if:announcement_until,date|nullable|date|after:now',
        ]);
    }

    public static function publish(Request $request, array $data): void
    {
        if (! $request->boolean('announce')) return;
        $end = match ($data['announcement_until']) {
            'duration' => now()->addHours((int) $data['announcement_hours']),
            'date' => $data['announcement_end'],
            default => null,
        };
        Announcement::create([
            'user_id' => $request->user()->id, 'title' => $data['announcement_title'],
            'body' => $data['announcement_body'], 'starts_at' => now(), 'ends_at' => $end,
        ]);
    }
}
