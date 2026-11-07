<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $roleRequired = 'buyer'): Response
    {
        $token = $request->bearerToken();

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication token required.',
            ], 401);
        }

        $apiToken = ApiToken::findByPlainTextToken($token);

        if (! $apiToken || ! $apiToken->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Your authentication token is invalid or has expired. Please sign in again.',
            ], 401);
        }

        $user = $apiToken->user;

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Associated user account not found.',
            ], 401);
        }

        // Account status verification
        if ($user->status === 'unverified') {
            return response()->json([
                'success' => false,
                'message' => 'Your email address is not verified. Please complete OTP verification.',
                'status' => 'unverified',
                'requires_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        if ($user->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact support.',
                'status' => 'suspended',
            ], 403);
        }

        if ($user->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is currently inactive.',
                'status' => $user->status,
            ], 403);
        }

        // Role verification
        if ($roleRequired !== null && $user->role !== $roleRequired) {
            return response()->json([
                'success' => false,
                'message' => "Access denied. This endpoint requires '{$roleRequired}' role permissions.",
            ], 403);
        }

        // Touch token usage
        $apiToken->updateQuietly(['last_used_at' => now()]);

        // Attach authenticated user to request and Auth facade
        $request->setUserResolver(fn () => $user);
        Auth::setUser($user);

        // Store active token in request for logout endpoint
        $request->attributes->set('current_api_token', $apiToken);

        return $next($request);
    }
}

