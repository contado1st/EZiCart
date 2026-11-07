<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class TrackWebActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $user = $request->user();
        // Background polling and embedded refreshes do not prove human activity.
        if ($user && $response->getStatusCode() < 400 && ! $request->expectsJson()
            && (! $request->boolean('panel') || ! $request->isMethodSafe()) && ! $request->routeIs('communications.counts', 'profiles.media', 'chat.attachments.show', 'staff.avatar', 'staff.proposed-avatar', 'parcel-evidence.photo', 'delivery-attempts.proof')) {
            User::whereKey($user->id)->where(fn ($q) => $q->whereNull('last_web_activity_at')->orWhere('last_web_activity_at', '<=', now()->subMinutes((int) config('web-commerce.activity_write_minutes'))))
                ->update(['last_web_activity_at' => now()]);
        }

        return $response;
    }
}
