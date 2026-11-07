<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    protected function authenticatedUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
