<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;
use App\Notifications\PasswordRecoveryCodeNotification;
use App\Services\RegistrationIdentityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function __construct(
        private readonly RegistrationIdentityService $registrationIdentity,
    ) {}

    /**
     * Buyer Login
     */
    public function login(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$credentials['email']])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials do not match our records.',
            ], 422);
        }

        $isCourierRequest = $request->input('app') === 'courier' || $request->input('role') === 'courier' || str_contains($request->path(), 'courier');

        if ($isCourierRequest) {
            if ($user->role !== 'courier') {
                return response()->json([
                    'success' => false,
                    'message' => 'This mobile application is exclusively for registered Drivers and Couriers.',
                    'role' => $user->role,
                ], 403);
            }
        } else {
            // Enforce Buyer Role only
            if ($user->role !== 'buyer') {
                return response()->json([
                    'success' => false,
                    'message' => 'This mobile application is exclusively for registered Buyers. Non-buyer accounts must use the web portal.',
                    'role' => $user->role,
                ], 403);
            }
        }

        // Status verification
        if ($user->status === 'unverified') {
            // Re-issue or ensure OTP exists
            $this->issueOtp($user);

            return response()->json([
                'success' => false,
                'message' => 'Your email address is not verified. Please enter the verification code sent to your email.',
                'status' => 'unverified',
                'requires_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        if ($user->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is currently suspended. Please contact customer support.',
                'status' => 'suspended',
            ], 403);
        }

        if ($user->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive.',
                'status' => $user->status,
            ], 403);
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $tokenResult = ApiToken::createTokenForUser($user, $isCourierRequest ? 'courier_mobile' : 'buyer_mobile');

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $tokenResult['plainTextToken'],
            'token_type' => 'Bearer',
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Buyer Registration
     */
    public function register(Request $request): JsonResponse
    {
        $this->normalizeIdentity($request);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:'.$this->registrationIdentity->nameRule()],
            'middle_name' => ['nullable', 'string', 'min:1', 'max:50', 'regex:'.$this->registrationIdentity->nameRule()],
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:'.$this->registrationIdentity->nameRule()],
            'suffix' => ['nullable', 'string', 'max:20', 'regex:/\A[\p{L}\p{M}\p{N} .\x27-]*\z/u'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email'), Rule::unique('users', 'canonical_email')],
            'contact_no' => ['required', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/', Rule::unique('users', 'canonical_mobile')],
            'birthday' => ['required', 'date', 'before_or_equal:today', 'before_or_equal:'.now()->subYears(18)->format('Y-m-d')],
            'password' => ['required', 'string', PasswordRule::min(8)->max(128)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'region' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'barangay' => ['nullable', 'string', 'max:120'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'digits:4'],
        ], [
            'birthday.before_or_equal' => 'You must be at least 18 years old to create a buyer account.',
            'contact_no.regex' => 'Mobile number must be a valid Philippine 11-digit number (09XXXXXXXXX).',
        ]);

        $canonicalMobile = $this->registrationIdentity->canonicalMobile($validated['contact_no']);
        $canonicalEmail = $this->registrationIdentity->canonicalEmail($validated['email']);

        $user = DB::transaction(function () use ($validated, $canonicalMobile, $canonicalEmail): User {
            return User::forceCreate([
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?? null,
                'email' => Str::lower(trim($validated['email'])),
                'canonical_email' => $canonicalEmail,
                'contact_no' => $validated['contact_no'],
                'canonical_mobile' => $canonicalMobile,
                'birthday' => $validated['birthday'],
                'age' => Carbon::parse($validated['birthday'])->age,
                'password' => Hash::make($validated['password']),
                'role' => 'buyer',
                'status' => 'unverified',
                'region' => $validated['region'] ?? null,
                'province' => $validated['province'] ?? null,
                'municipality' => $validated['municipality'] ?? null,
                'barangay' => $validated['barangay'] ?? null,
                'street_address' => $validated['street_address'] ?? null,
                'house_number' => $validated['house_number'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'terms_accepted_at' => now(),
                'privacy_accepted_at' => now(),
            ]);
        });

        // Issue 6-digit OTP
        $this->issueOtp($user);

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully! A 6-digit verification code has been sent to your email.',
            'email' => $user->email,
            'status' => 'unverified',
        ], 201);
    }

    /**
     * Verify OTP
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$validated['email']])->first();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User account not found.'], 404);
        }

        $record = EmailVerificationCode::where('user_id', $user->id)->first();

        if (! $record || ! Hash::check($validated['code'], $record->code_hash)) {
            if ($record) {
                $record->increment('attempts');
            }

            return response()->json(['success' => false, 'message' => 'Invalid verification code. Please check and try again.'], 422);
        }

        if ($record->expires_at && Carbon::parse($record->expires_at)->isPast()) {
            return response()->json(['success' => false, 'message' => 'Verification code has expired. Please request a new one.'], 422);
        }

        // Approve and verify account
        $user->forceFill([
            'email_verified_at' => now(),
            'status' => 'approved',
        ])->save();

        $record->delete();

        $tokenResult = ApiToken::createTokenForUser($user, $isCourierRequest ? 'courier_mobile' : 'buyer_mobile');

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully! You are now logged in.',
            'token' => $tokenResult['plainTextToken'],
            'token_type' => 'Bearer',
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Resend verification OTP code
     */
    public function resendVerificationCode(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$validated['email']])->first();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        $record = EmailVerificationCode::firstOrNew(['user_id' => $user->id]);

        if ($record->last_sent_at && Carbon::parse($record->last_sent_at)->addSeconds(60)->isFuture()) {
            $secondsLeft = 60 - Carbon::parse($record->last_sent_at)->diffInSeconds(now());

            return response()->json([
                'success' => false,
                'message' => "Please wait {$secondsLeft} seconds before requesting a new code.",
            ], 429);
        }

        $this->issueOtp($user, $record);

        return response()->json([
            'success' => true,
            'message' => 'A new 6-digit verification code has been sent to your email.',
        ]);
    }

    /**
     * Request Password Reset OTP
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = Str::lower(trim($validated['email']));

        $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

        if ($user && $user->status === 'approved') {
            $code = (string) random_int(100000, 999999);
            try {
                $user->notify(new PasswordRecoveryCodeNotification($code));
            } catch (\Throwable $e) {
                report($e);
            }

            // Save reset code record
            EmailVerificationCode::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'code_hash' => Hash::make($code),
                    'expires_at' => now()->addMinutes(15),
                    'attempts' => 0,
                    'last_sent_at' => now(),
                    'channel' => 'email',
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'If an active buyer account matches that email address, a password recovery code has been sent.',
        ]);
    }

    /**
     * Reset Password using OTP
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', PasswordRule::min(8)->max(128)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        $record = EmailVerificationCode::where('user_id', $user->id)->first();

        if (! $record || ! Hash::check($validated['code'], $record->code_hash)) {
            return response()->json(['success' => false, 'message' => 'Invalid recovery code.'], 422);
        }

        if ($record->expires_at && Carbon::parse($record->expires_at)->isPast()) {
            return response()->json(['success' => false, 'message' => 'Recovery code has expired. Request a new one.'], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        $record->delete();

        // Invalidate previous API tokens
        $user->apiTokens()->delete();

        $tokenResult = ApiToken::createTokenForUser($user, $isCourierRequest ? 'courier_mobile' : 'buyer_mobile');

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully! You are now logged in.',
            'token' => $tokenResult['plainTextToken'],
            'token_type' => 'Bearer',
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Get Current Authenticated Buyer Profile
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $counts = $user->role === 'courier' ? [
            'claimed_pickups' => \App\Models\Order::where('pickup_courier_id', $user->id)->where('status', 'READY_FOR_PICKUP')->whereNotNull('pickup_claimed_at')->count(),
            'in_transit_hub' => \App\Models\Order::where('pickup_courier_id', $user->id)->where('status', 'PICKED_UP')->count(),
            'assigned_delivery' => \App\Models\Order::where('delivery_courier_id', $user->id)->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])->count(),
            'returning_to_seller' => \App\Models\Order::where('delivery_courier_id', $user->id)->where('status', \App\Enums\OrderStatus::ReturnInTransit->value)->whereNull('return_handed_to_seller_at')->count(),
        ] : [
            'all' => $user->buyerOrders()->count(),
            'to_ship' => $user->buyerOrders()->whereIn('status', ['PLACED', 'CONFIRMED', 'PREPARING'])->count(),
            'to_receive' => $user->buyerOrders()->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERED', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT'])->count(),
            'completed' => $user->buyerOrders()->whereIn('status', ['COMPLETED', 'CANCELLED', 'RETURNED', 'RETURNED_TO_SELLER'])->count(),
        ];

        return response()->json([
            'success' => true,
            'user' => $this->formatUser($user),
            'order_counts' => $counts,
        ]);
    }

    /**
     * Buyer Logout
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var ApiToken|null $apiToken */
        $apiToken = $request->attributes->get('current_api_token');

        if ($apiToken) {
            $apiToken->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    private function issueOtp(User $user, ?EmailVerificationCode $record = null): void
    {
        $record = $record ?? EmailVerificationCode::firstOrNew(['user_id' => $user->id]);
        $code = (string) random_int(100000, 999999);

        try {
            $user->notify(new EmailVerificationCodeNotification($code));
        } catch (\Throwable $e) {
            report($e);
        }

        $record->code_hash = Hash::make($code);
        $record->expires_at = now()->addMinutes(15);
        $record->attempts = 0;
        $record->last_sent_at = now();
        $record->channel = 'email';
        $record->save();
    }

    private function normalizeIdentity(Request $request): void
    {
        if ($request->has('email')) {
            $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        }
        if ($request->has('contact_no')) {
            $mobile = preg_replace('/\D+/', '', (string) $request->input('contact_no'));
            if (str_starts_with($mobile, '639') && strlen($mobile) === 12) {
                $mobile = '0'.substr($mobile, 2);
            }
            $request->merge(['contact_no' => $mobile]);
        }
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'name' => trim("{$user->first_name} {$user->last_name}"),
            'email' => $user->email,
            'contact_no' => $user->contact_no,
            'birthday' => $user->birthday instanceof \Carbon\CarbonInterface ? $user->birthday->format('Y-m-d') : ($user->birthday ? (string) $user->birthday : null),
            'age' => $user->age,
            'role' => $user->role,
            'status' => $user->status,
            'badge_code' => $user->badge_code,
            'vehicle_type' => $user->vehicle_type,
            'plate_number' => $user->plate_number,
            'vehicle_category' => $user->vehicle_category,
            'vehicle_description' => $user->vehicle_description,
            'vehicle_capacity_grams' => $user->vehicle_capacity_grams,
            'hub_transfer_only' => (bool) $user->hub_transfer_only,
            'region' => $user->region,
            'province' => $user->province,
            'municipality' => $user->municipality,
            'barangay' => $user->barangay,
            'street_address' => $user->street_address,
            'house_number' => $user->house_number,
            'postal_code' => $user->postal_code,
        ];
    }
}
