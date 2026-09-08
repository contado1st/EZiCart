<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;

class AdminAnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with('author')->latest()->paginate(10);
        return view('admin.announcements.index', compact('announcements'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'content'     => 'required|string|max:2000',
            'type'        => 'required|in:info,warning,urgent,maintenance',
            'target_role' => 'required|in:all,buyer,seller,courier,sorting_center',
            'expires_at'  => 'nullable|date|after:today',
        ]);

        auth()->user()->announcements()->create([
            'title'       => $validated['title'],
            'content'     => $validated['content'],
            'type'        => $validated['type'],
            'target_role' => $validated['target_role'],
            'expires_at'  => $validated['expires_at'] ?? null,
            'is_active'   => true,
        ]);

        return back()->with('success', 'Platform bulletin announcement published successfully.');
    }

    public function toggle(Announcement $announcement)
    {
        $announcement->update([
            'is_active' => !$announcement->is_active,
        ]);

        $status = $announcement->is_active ? 'activated' : 'archived';
        return back()->with('success', "Announcement '{$announcement->title}' {$status}.");
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return back()->with('success', 'Announcement deleted.');
    }
}