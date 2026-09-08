<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminModerationController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->query('role');
        $search = $request->query('search');

        $users = User::whereNotIn('role', ['admin'])
            ->when($role, fn($q) => $q->where('role', $role))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15);

        return view('admin.moderation.index', compact('users', 'role', 'search'));
    }

    public function suspend(Request $request, User $user)
    {
        abort_if($user->role === 'admin', 403);

        $validated = $request->validate([
            'suspension_reason' => 'required|string|max:500',
        ]);

        $user->update([
            'status'            => 'suspended',
            'suspension_reason' => $validated['suspension_reason'],
            'suspended_at'      => now(),
        ]);

        return back()->with('success', "Account {$user->email} has been suspended.");
    }

    public function reactivate(User $user)
    {
        abort_if($user->role === 'admin', 403);

        $user->update([
            'status'            => 'approved',
            'suspension_reason' => null,
            'suspended_at'      => null,
        ]);

        return back()->with('success', "Account {$user->email} has been restored to active standing.");
    }
}