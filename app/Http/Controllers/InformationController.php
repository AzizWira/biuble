<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InformationController extends Controller
{
    public function index(Request $request)
    {
        return view('information', ['readRevisions' => DB::table('announcement_reads')->where('user_id', $request->user()->id)->pluck('revision', 'announcement_id'), 'announcements' => Announcement::live()->latest('starts_at')->paginate(15)]);
    }

    public function read(Request $request)
    {
        $data = $request->validate(['announcement' => 'nullable|integer|min:1', 'revision' => 'required_with:announcement|integer|min:1']);
        DB::transaction(function () use ($request, $data) {
            $query = Announcement::live();
            if (isset($data['announcement'])) $query->whereKey($data['announcement'])->where('revision', $data['revision']);
            foreach ($query->lockForUpdate()->get() as $announcement) {
                DB::table('announcement_reads')->updateOrInsert(
                    ['announcement_id' => $announcement->id, 'user_id' => $request->user()->id],
                    ['revision' => $announcement->revision, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        });
        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', 'Informasi ditandai sudah dibaca.');
    }

    public function save(Request $request, ?Announcement $announcement = null)
    {
        Store::staff();
        abort_if($announcement && $request->user()->role !== 'owner' && $announcement->user_id !== $request->user()->id, 403);
        $data = $request->validate([
            'title' => 'required|string|max:150', 'body' => 'required|string|max:5000',
            'published' => 'nullable|boolean', 'starts_at' => 'required|date',
            'until' => 'required|in:manual,duration,date', 'hours' => 'required_if:until,duration|nullable|integer|min:1|max:8760',
            'ends_at' => 'required_if:until,date|nullable|date|after:starts_at', 'notify_again' => 'nullable|boolean',
        ]);
        DB::transaction(function () use ($request, $announcement, $data) {
            $record = $announcement ? Announcement::whereKey($announcement->id)->lockForUpdate()->firstOrFail() : new Announcement(['user_id' => $request->user()->id]);
            $end = match ($data['until']) {
                'duration' => \Illuminate\Support\Carbon::parse($data['starts_at'])->addHours((int) $data['hours']),
                'date' => $data['ends_at'], default => null,
            };
            $record->fill(['title' => $data['title'], 'body' => $data['body'], 'starts_at' => $data['starts_at'],
                'ends_at' => $end, 'published' => $request->boolean('published')]);
            if ($record->exists && $request->boolean('notify_again')) $record->revision++;
            $record->save();
            Store::audit('announcement_save', (string) $record->id);
        });
        return back()->with('status', 'Pengumuman disimpan.');
    }

    public function remove(Request $request, Announcement $announcement)
    {
        Store::staff();
        abort_unless($request->user()->role === 'owner' || $announcement->user_id === $request->user()->id, 403);
        $announcement->delete();
        Store::audit('announcement_remove', (string) $announcement->id);
        return back()->with('status', 'Pengumuman dihapus.');
    }
}
