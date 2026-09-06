<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        $pendingUsers = User::where('status', 'pending')
            ->latest()
            ->get();

        return view('admin.registrations', compact('pendingUsers'));
    }

    public function approve(User $user)
    {
        $user->update(['status' => 'approved']);

        return back()->with('success', "Account for {$user->first_name} {$user->last_name} ({$user->role}) has been approved.");
    }

    public function reject(User $user)
    {
        $user->update(['status' => 'rejected']);

        return back()->with('success', "Account for {$user->first_name} {$user->last_name} ({$user->role}) has been rejected.");
    }
}