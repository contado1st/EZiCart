<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;
use App\Services\PsgcAddressService;
use App\Services\RegistrationIdentityService;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const TIN_FORMAT_ERROR = 'TIN must contain exactly 12 digits in the format 000-000-000-000.';

    private const MOBILE_FORMAT_ERROR = 'Mobile number must contain exactly 11 digits in the format 09XXXXXXXXX, or use the equivalent +63 format.';

    private const MOBILE_DUPLICATE_ERROR = 'This mobile number is already in use.';

    private const SEEDED_OTP_EXEMPT_ACCOUNTS = [
        'buyer@ezicart.com' => 'buyer',
        'seller@ezicart.com' => 'seller',
    ];

    public function __construct(
        private readonly PsgcAddressService $psgcAddressService,
        private readonly RegistrationIdentityService $registrationIdentity,
    ) {}

    private const PHILIPPINE_REGIONS = [
        'National Capital Region (NCR)',
        'Cordillera Administrative Region (CAR)',
        'Region I (Ilocos)',
        'Region II (Cagayan Valley)',
        'Region III (Central Luzon)',
        'Region IV-A (CALABARZON)',
        'MIMAROPA',
        'Region V (Bicol)',
        'Region VI (Western Visayas)',
        'Region VII (Central Visayas)',
        'Region VIII (Eastern Visayas)',
        'Region IX (Zamboanga Peninsula)',
        'Region X (Northern Mindanao)',
        'Region XI (Davao Region)',
        'Region XII (SOCCSKSARGEN)',
        'Region XIII (Caraga)',
        'Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)',
    ];

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = Str::lower(trim($validated['email']));
        Password::sendResetLink(['email' => $email]);

        return back()->with('status', 'If an account matches that email address, a password reset link has been sent.');
    }

    public function showResetPasswordForm(string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => request()->query('email')]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', PasswordRule::min(8)->max(128)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PasswordReset) {
            return redirect()->route('login')->with('success', 'Password reset successfully. You can now sign in.');
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    public function login(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $credentials = $request->validate([
            'email' => $this->emailRules(),
            'password' => ['required', 'string'],
        ]);

        $account = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$credentials['email']])->first();
        if (! $account || ! Auth::attempt(['email' => $account->email, 'password' => $credentials['password']], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
        }

        $user = $request->user();

        // 1. Admin accounts bypass OTP verification
        if ($user->administrationDashboardRoute() !== null) {
            if ($user->status !== 'approved') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('error', $this->accountStateMessage($user));
            }

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $request->session()->regenerate();

            return redirect()->intended(route($user->administrationDashboardRoute()));
        }

        // 2. Email Verification Enforcement (unverified accounts must verify their email)
        if ($user->status === 'unverified') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $request->session()->put([
                'verification_user_id' => $user->id,
                'verification_email' => Str::lower(trim($user->email)),
            ]);

            return redirect()->route('verification.notice')
                ->with('error', 'Your email address is not verified. Please enter the verification code sent to your email, or request a new code.');
        }

        // 3. Administrator Approval Enforcement (Sellers and Logistics require admin approval after verification)
        if ($user->status === 'pending') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $roleLabel = match ($user->role) {
                'seller' => 'seller registration',
                'sorting_center' => 'logistics registration',
                default => 'application',
            };

            return redirect()->route('login')
                ->with('error', "Your email has been verified. Your {$roleLabel} is now awaiting admin approval.");
        }

        // 4. Rejected Status (Allow resubmission for seller and logistics applicants)
        if ($user->status === 'rejected') {
            if (in_array($user->role, ['seller', 'sorting_center'], true)) {
                $request->session()->regenerate();

                return redirect()->route('application.resubmit')
                    ->with('error', $this->accountStateMessage($user));
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', $this->accountStateMessage($user));
        }

        // 5. Any other non-approved status (e.g. suspended)
        if ($user->status !== 'approved') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', $this->accountStateMessage($user));
        }

        // For approved accounts, ensure email_verified_at is set
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if (in_array($user->role, ['buyer', 'seller'], true) && ! $this->isSeededDemoOtpExemptAccount($user)) {
            $intended = $request->session()->pull('url.intended');
            $remember = $request->boolean('remember');
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            try {
                $code = (string) random_int(100000, 999999);
                $user->notify(new EmailVerificationCodeNotification($code));
            } catch (\Throwable $exception) {
                report($exception);

                return redirect()->route('login')->with('error', 'We could not send a sign-in code. Check your email delivery settings and try again.');
            }

            $request->session()->put('login_otp', [
                'user_id' => $user->id,
                'email' => Str::lower(trim($user->email)),
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(5)->timestamp,
                'attempts' => 0,
                'sent_at' => now()->timestamp,
                'remember' => $remember,
                'intended' => is_string($intended) ? $intended : null,
            ]);

            return redirect()->route('login.otp.notice')
                ->with('success', 'A sign-in code has been sent to your email address.');
        }

        // 6. Approved Accounts
        $request->session()->regenerate();

        return match ($user->role) {
            'seller' => redirect()->intended(route('seller.dashboard')),
            'admin', 'ecommerce_admin' => redirect()->intended(route('admin.dashboard')),
            'logistics_admin' => redirect()->intended(route('logistics-admin.dashboard')),
            'courier' => redirect()->intended(route('courier.dashboard')),
            'sorting_center' => redirect()->intended(route('logistics.dashboard')),
            default => redirect()->intended(route('buyer.dashboard')),
        };
    }

    public function showLoginOtp(): RedirectResponse|View
    {
        $challenge = session('login_otp');
        if (! is_array($challenge) || empty($challenge['user_id']) || empty($challenge['email'])) {
            return redirect()->route('login')->with('error', 'Sign in first to request a verification code.');
        }

        if (($challenge['expires_at'] ?? 0) < now()->timestamp) {
            session()->forget('login_otp');

            return redirect()->route('login')->with('error', 'Your sign-in code expired. Please sign in again.');
        }

        $cooldownSeconds = max(0, 60 - (now()->timestamp - (int) ($challenge['sent_at'] ?? 0)));

        return view('auth.verify-email', [
            'email' => $challenge['email'],
            'cooldownSeconds' => $cooldownSeconds,
            'verificationMode' => 'login',
        ]);
    }

    public function verifyLoginOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $challenge = $request->session()->get('login_otp');
        if (! is_array($challenge) || empty($challenge['user_id'])) {
            return redirect()->route('login')->with('error', 'Sign in first to request a verification code.');
        }

        if (($challenge['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('login_otp');

            return redirect()->route('login')->with('error', 'Your sign-in code expired. Please sign in again.');
        }

        if (($challenge['attempts'] ?? 0) >= 5) {
            $request->session()->forget('login_otp');

            return redirect()->route('login')->with('error', 'Too many incorrect codes. Please sign in again to request a new code.');
        }

        if (! Hash::check($validated['code'], $challenge['code_hash'] ?? '')) {
            $challenge['attempts'] = (int) ($challenge['attempts'] ?? 0) + 1;
            $request->session()->put('login_otp', $challenge);

            return back()->withErrors(['code' => 'That sign-in code is incorrect. Please try again.']);
        }

        $user = User::query()->find($challenge['user_id']);
        if (! $user || ! in_array($user->role, ['buyer', 'seller'], true) || $user->status !== 'approved') {
            $request->session()->forget('login_otp');

            return redirect()->route('login')->with('error', 'This account is not eligible to sign in.');
        }

        $remember = (bool) ($challenge['remember'] ?? false);
        $intended = $challenge['intended'] ?? null;
        $request->session()->forget('login_otp');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(is_string($intended) ? $intended : $this->dashboardFor($user));
    }

    public function resendLoginOtp(Request $request): RedirectResponse
    {
        $challenge = $request->session()->get('login_otp');
        if (! is_array($challenge) || empty($challenge['user_id'])) {
            return redirect()->route('login')->with('error', 'Sign in first to request a verification code.');
        }

        $elapsed = now()->timestamp - (int) ($challenge['sent_at'] ?? 0);
        if ($elapsed < 60) {
            return back()->withErrors(['code' => 'Please wait '.(60 - $elapsed).' seconds before requesting another code.']);
        }

        $user = User::query()->find($challenge['user_id']);
        if (! $user || ! in_array($user->role, ['buyer', 'seller'], true) || $user->status !== 'approved') {
            $request->session()->forget('login_otp');

            return redirect()->route('login')->with('error', 'This account is not eligible to sign in.');
        }

        try {
            $code = (string) random_int(100000, 999999);
            $user->notify(new EmailVerificationCodeNotification($code));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['code' => 'We could not send a new code. Please try again later.']);
        }

        $challenge['code_hash'] = Hash::make($code);
        $challenge['expires_at'] = now()->addMinutes(5)->timestamp;
        $challenge['attempts'] = 0;
        $challenge['sent_at'] = now()->timestamp;
        $request->session()->put('login_otp', $challenge);

        return back()->with('success', 'A new sign-in code has been sent to your email address.');
    }

    private function dashboardFor(User $user): string
    {
        return $user->role === 'seller' ? route('seller.dashboard') : route('buyer.dashboard');
    }

    private function minimumAgeDate(): string
    {
        return now()->subYearsNoOverflow(18)->toDateString();
    }

    private function isSeededDemoOtpExemptAccount(User $user): bool
    {
        $email = Str::lower(trim($user->email));

        return app()->environment(['local', 'testing'])
            && (self::SEEDED_OTP_EXEMPT_ACCOUNTS[$email] ?? null) === $user->role;
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $this->normalizeIdentity($request);
        $validated = $request->validate($this->identityRules() + $this->psgcAddressRules(false, false) + [
            'birthday' => ['required', 'date', 'before_or_equal:today', 'before_or_equal:'.$this->minimumAgeDate()],
            'password' => ['required', 'string', PasswordRule::min(8)->max(128)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'terms' => ['accepted'],
        ], $this->registrationValidationMessages());
        $user = $this->createVerifiedAccount($validated, $this->resolveAddress($validated) + [
            'birthday' => $validated['birthday'],
            'age' => Carbon::parse($validated['birthday'])->age,
            'house_number' => $validated['house_number'] ?? null,
            'street_address' => $validated['street_address'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
        ], 'buyer');

        return $this->beginVerification($user);
    }

    public function showSellerRegisterForm()
    {
        return view('auth.register-seller', ['regions' => self::PHILIPPINE_REGIONS]);
    }

    public function sellerRegister(Request $request): RedirectResponse
    {
        $this->normalizeIdentity($request);
        $this->normalizeBusinessIdentifiers($request);
        $validated = $request->validate($this->identityRules() + $this->psgcAddressRules(true, true) + [
            'shop_name' => ['required', 'string', 'min:3', 'max:30'],
            'canonical_shop_name' => ['required', 'string', 'max:30', Rule::unique('users', 'canonical_shop_name')],
            'line_of_business' => ['required', 'string', Rule::in(config('registration.seller_categories'))],
            'seller_type' => ['required', 'in:Individual,Sole Proprietorship,Corporation'],
            'tin' => ['required', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/'],
            'canonical_tin' => ['required', 'digits:12', Rule::unique('users', 'canonical_tin')],
            'postal_code' => ['required', 'digits:4'],
            'id_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'business_permit' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'password' => ['required', 'string', PasswordRule::min(8)->max(128)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'terms' => ['accepted'],
        ], $this->registrationValidationMessages(includeTinFormat: true));
        $address = $this->resolveAddress($validated);
        $paths = array_filter([
            'id_path' => $request->file('id_document')->store('documents/ids', 'private'),
            'permit_path' => $request->file('business_permit')->store('documents/permits', 'private'),
        ]);

        try {
            $user = $this->createVerifiedAccount($validated, $paths + [
                'business_name' => $validated['shop_name'],
                'shop_name' => $validated['shop_name'],
                'canonical_shop_name' => $validated['canonical_shop_name'],
                'line_of_business' => $validated['line_of_business'],
                'seller_type' => $validated['seller_type'],
                'tin' => $validated['tin'],
                'canonical_tin' => $validated['canonical_tin'],
                ...$address,
                'house_number' => $validated['house_number'] ?? null,
                'street_address' => $validated['street_address'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
            ], 'seller');
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete(array_values($paths));

            throw $exception;
        }

        return $this->beginVerification($user);
    }

    public function showSortingCenterRegisterForm()
    {
        return view('auth.register-sorting', ['regions' => self::PHILIPPINE_REGIONS]);
    }

    public function sortingCenterRegister(Request $request): RedirectResponse
    {
        $this->normalizeIdentity($request);
        $this->normalizeBusinessIdentifiers($request);
        $validated = $request->validate($this->identityRules() + $this->psgcAddressRules(true, true) + [
            'business_name' => ['required', 'string', 'min:3', 'max:150'],
            'canonical_company_name' => ['required', 'string', 'max:150', Rule::unique('users', 'canonical_company_name')],
            'sec_registration_no' => ['required', 'regex:/^[A-Za-z]{2}\d{9}$/'],
            'canonical_sec_registration_no' => ['required', 'size:11', Rule::unique('users', 'canonical_sec_registration_no')],
            'tin' => ['required', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/'],
            'canonical_tin' => ['required', 'digits:12', Rule::unique('users', 'canonical_tin')],
            'postal_code' => ['required', 'digits:4'],
            'contact_position' => ['required', 'string', 'max:100'],
            'service_areas' => ['required', 'array', 'min:1'],
            'service_areas.*' => ['required', 'string', Rule::in(self::PHILIPPINE_REGIONS)],
            'sec_certificate' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'bir_form_2303' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'business_permit' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'representative_id' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'password' => ['required', 'string', PasswordRule::min(8)->max(128)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'terms' => ['accepted'],
        ], $this->registrationValidationMessages(includeTinFormat: true));
        $address = $this->resolveAddress($validated);
        $paths = array_filter([
            'id_path' => $request->file('representative_id')->store('documents/ids', 'private'),
            'sec_certificate_path' => $request->file('sec_certificate')->store('documents/sec', 'private'),
            'bir_certificate_path' => $request->file('bir_form_2303')->store('documents/bir', 'private'),
            'permit_path' => $request->file('business_permit')->store('documents/permits', 'private'),
            'contact_position' => $validated['contact_position'],
            'service_areas' => array_values($validated['service_areas']),
            'canonical_company_name' => $validated['canonical_company_name'],
            'sec_registration_no' => Str::upper($validated['sec_registration_no']),
            'canonical_sec_registration_no' => $validated['canonical_sec_registration_no'],
            'tin' => $validated['tin'],
            'canonical_tin' => $validated['canonical_tin'],
            ...$address,
            'house_number' => $validated['house_number'] ?? null,
            'street_address' => $validated['street_address'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
        ]);

        try {
            $user = $this->createVerifiedAccount($validated, $paths, 'sorting_center');
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete(array_values($paths));

            throw $exception;
        }

        return $this->beginVerification($user);
    }

    public function showApplicationResubmission(Request $request)
    {
        $applicant = $request->user();
        abort_unless(
            $applicant !== null
                && in_array($applicant->role, ['seller', 'sorting_center'], true)
                && $applicant->status === 'rejected',
            403,
        );

        $view = $applicant->role === 'seller' ? 'auth.register-seller' : 'auth.register-sorting';

        return view($view, [
            'applicant' => $applicant,
            'regions' => self::PHILIPPINE_REGIONS,
            'resubmitting' => true,
        ]);
    }

    public function resubmitApplication(Request $request): RedirectResponse
    {
        $applicant = $request->user();
        abort_unless(
            $applicant !== null
                && in_array($applicant->role, ['seller', 'sorting_center'], true)
                && $applicant->status === 'rejected',
            403,
        );

        $this->normalizeIdentity($request);
        $this->normalizeBusinessIdentifiers($request);
        $commonRules = $this->identityRules($applicant);

        if ($applicant->role === 'seller') {
            $validated = $request->validate($commonRules + $this->psgcAddressRules(true, true) + [
                'shop_name' => ['required', 'string', 'min:3', 'max:30'],
                'canonical_shop_name' => ['required', 'string', 'max:30', Rule::unique('users', 'canonical_shop_name')->ignore($applicant->id)],
                'line_of_business' => ['required', 'string', Rule::in(config('registration.seller_categories'))],
                'seller_type' => ['required', 'in:Individual,Sole Proprietorship,Corporation'],
                'tin' => ['required', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/'],
                'canonical_tin' => ['required', 'digits:12', Rule::unique('users', 'canonical_tin')->ignore($applicant->id)],
                'postal_code' => ['required', 'digits:4'],
                'id_document' => [Rule::requiredIf(! $applicant->id_path), 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'business_permit' => [Rule::requiredIf(! $applicant->permit_path), 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'terms' => ['accepted'],
            ], $this->registrationValidationMessages(includeTinFormat: true));
            $address = $this->resolveAddress($validated);
            $newPaths = array_filter([
                'id_path' => $request->file('id_document')?->store('documents/ids', 'private'),
                'permit_path' => $request->file('business_permit')?->store('documents/permits', 'private'),
            ]);
            $details = [
                'business_name' => $validated['shop_name'],
                'shop_name' => $validated['shop_name'],
                'canonical_shop_name' => $validated['canonical_shop_name'],
                'line_of_business' => $validated['line_of_business'],
                'seller_type' => $validated['seller_type'],
                'tin' => $validated['tin'],
                'canonical_tin' => $validated['canonical_tin'],
                ...$address,
                'house_number' => $validated['house_number'] ?? null,
                'street_address' => $validated['street_address'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
            ];
        } else {
            $validated = $request->validate($commonRules + $this->psgcAddressRules(true, true) + [
                'business_name' => ['required', 'string', 'min:3', 'max:150'],
                'canonical_company_name' => ['required', 'string', 'max:150', Rule::unique('users', 'canonical_company_name')->ignore($applicant->id)],
                'sec_registration_no' => ['required', 'regex:/^[A-Za-z]{2}\d{9}$/'],
                'canonical_sec_registration_no' => ['required', 'size:11', Rule::unique('users', 'canonical_sec_registration_no')->ignore($applicant->id)],
                'tin' => ['required', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/'],
                'canonical_tin' => ['required', 'digits:12', Rule::unique('users', 'canonical_tin')->ignore($applicant->id)],
                'postal_code' => ['required', 'digits:4'],
                'contact_position' => ['required', 'string', 'max:100'],
                'service_areas' => ['required', 'array', 'min:1'],
                'service_areas.*' => ['required', 'string', Rule::in(self::PHILIPPINE_REGIONS)],
                'sec_certificate' => [Rule::requiredIf(! $applicant->sec_certificate_path), 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'bir_form_2303' => [Rule::requiredIf(! $applicant->bir_certificate_path), 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'business_permit' => [Rule::requiredIf(! $applicant->permit_path), 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'representative_id' => [Rule::requiredIf(! $applicant->id_path), 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'terms' => ['accepted'],
            ], $this->registrationValidationMessages(includeTinFormat: true));
            $address = $this->resolveAddress($validated);
            $newPaths = array_filter([
                'id_path' => $request->file('representative_id')?->store('documents/ids', 'private'),
                'sec_certificate_path' => $request->file('sec_certificate')?->store('documents/sec', 'private'),
                'bir_certificate_path' => $request->file('bir_form_2303')?->store('documents/bir', 'private'),
                'permit_path' => $request->file('business_permit')?->store('documents/permits', 'private'),
            ]);
            $details = [
                'business_name' => $validated['business_name'],
                'canonical_company_name' => $validated['canonical_company_name'],
                'sec_registration_no' => Str::upper($validated['sec_registration_no']),
                'canonical_sec_registration_no' => $validated['canonical_sec_registration_no'],
                'tin' => $validated['tin'],
                'canonical_tin' => $validated['canonical_tin'],
                'contact_position' => $validated['contact_position'],
                'service_areas' => array_values($validated['service_areas']),
                ...$address,
                'house_number' => $validated['house_number'] ?? null,
                'street_address' => $validated['street_address'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
            ];
        }

        $oldPaths = array_filter([
            $applicant->id_path,
            $applicant->permit_path,
            $applicant->bir_certificate_path,
            $applicant->sec_certificate_path,
        ]);

        $emailChanged = Str::lower(trim($applicant->email)) !== $validated['email'];
        DB::transaction(function () use ($applicant, $validated, $details, $newPaths, $emailChanged): void {
            $applicant->forceFill(array_merge([
                'first_name' => trim($validated['first_name']),
                'middle_name' => filled($validated['middle_name'] ?? null) ? trim($validated['middle_name']) : null,
                'last_name' => trim($validated['last_name']),
                'suffix' => filled($validated['suffix'] ?? null) ? trim($validated['suffix']) : null,
                'email' => $validated['email'],
                'canonical_email' => $validated['email'],
                'contact_no' => $validated['contact_no'],
                'canonical_mobile' => $validated['contact_no'],
                'terms_accepted_at' => now(),
                'terms_version' => config('registration.policy_version'),
                'privacy_accepted_at' => now(),
                'privacy_version' => config('registration.policy_version'),
                'status' => $emailChanged ? 'unverified' : 'pending',
                'email_verified_at' => $emailChanged ? null : $applicant->email_verified_at,
                'approved_at' => null,
                'approved_by' => null,
                'rejection_reason' => null,
            ], $details, $newPaths))->save();
        });

        $currentPaths = array_filter(array_values($applicant->fresh()->only([
            'id_path', 'permit_path', 'bir_certificate_path', 'sec_certificate_path',
        ])));
        foreach ($oldPaths as $path) {
            if (! in_array($path, $currentPaths, true)) {
                Storage::disk('private')->delete($path);
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($emailChanged) {
            EmailVerificationCode::query()->where('user_id', $applicant->id)->delete();

            return $this->beginVerification($applicant->fresh());
        }

        return redirect()->route('login')->with('success', 'Your corrected application was resubmitted and is waiting for admin review.');
    }

    public function showEmailVerification(Request $request)
    {
        $sessionEmail = $request->session()->get('verification_email');
        $email = Str::lower(trim((string) $sessionEmail));

        $user = null;
        $cooldownSeconds = 0;

        if ($email !== '') {
            $user = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->where('status', 'unverified')->first();
            if ($user) {
                $record = EmailVerificationCode::query()->where('user_id', $user->id)->first();
                if ($record && $record->last_sent_at) {
                    $cooldownEnd = $record->last_sent_at->copy()->addSeconds(60);
                    $cooldownSeconds = now()->lt($cooldownEnd) ? (int) ceil(now()->diffInSeconds($cooldownEnd)) : 0;
                }
            }
        }

        return view('auth.verify-email', [
            'email' => $email,
            'user' => $user,
            'cooldownSeconds' => $cooldownSeconds,
        ]);
    }

    public function verifyEmail(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$validated['email']])->first();
        if (! $user || $user->status !== 'unverified') {
            throw ValidationException::withMessages([
                'code' => 'This verification request is no longer active or the account has already been verified.',
            ]);
        }

        $verified = DB::transaction(function () use ($user, $validated): bool {
            $record = EmailVerificationCode::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $record || $record->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'code' => 'This verification code has expired. Please request a new code.',
                ]);
            }
            if ($record->attempts >= 5) {
                throw ValidationException::withMessages([
                    'code' => 'Too many incorrect attempts. Please request a new verification code.',
                ]);
            }
            if (! Hash::check($validated['code'], $record->code_hash)) {
                $record->increment('attempts');

                return false;
            }

            // Buyer accounts are activated immediately upon email verification.
            // Seller and Logistics accounts transition to 'pending' awaiting administrator approval.
            $nextStatus = $user->role === 'buyer' ? 'approved' : 'pending';

            $user->forceFill([
                'status' => $nextStatus,
                'email_verified_at' => now(),
            ])->save();

            // OTP invalidated immediately after successful verification
            $record->delete();

            return true;
        });

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => 'The verification code you entered is incorrect. Check your email and try again.',
            ]);
        }

        $request->session()->forget(['verification_user_id', 'verification_email']);

        if ($user->role === 'buyer') {
            return redirect()->route('login')->with('success', 'Your email has been verified successfully. You can now sign in.');
        }

        if ($user->role === 'seller') {
            return redirect()->route('login')->with('success', 'Your email has been verified. Your seller registration is now awaiting admin approval.');
        }

        return redirect()->route('login')->with('success', 'Your email has been verified. Your logistics registration is now awaiting admin approval.');
    }

    public function resendVerificationCode(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);
        $email = $validated['email'];
        $user = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->where('status', 'unverified')->first();

        if ($user) {
            $record = EmailVerificationCode::query()->firstOrNew(['user_id' => $user->id]);

            // Enforce 60-second cooldown
            if ($record->exists && $record->last_sent_at) {
                $cooldownEnd = $record->last_sent_at->copy()->addSeconds(60);
                if (now()->lt($cooldownEnd)) {
                    $remaining = (int) ceil(now()->diffInSeconds($cooldownEnd));

                    return back()->withErrors(['email' => "Please wait {$remaining} seconds before requesting another code."])
                        ->withInput(['email' => $email]);
                }
            }

            try {
                $this->issueVerificationCode($user, $record);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['email' => 'We could not deliver a verification code to this email. Please check delivery settings and try again.'])
                    ->withInput(['email' => $email]);
            }

            $request->session()->put([
                'verification_user_id' => $user->id,
                'verification_email' => $user->email,
            ]);

            return back()->with('success', 'A new 6-digit verification code has been sent to your email address.');
        }

        return back()->with('status', 'If an unverified EZiCart account matches that email address, a new code has been sent.');
    }

    private function identityRules(?User $account = null): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:'.$this->registrationIdentity->nameRule()],
            'middle_name' => ['nullable', 'string', 'min:1', 'max:50', 'regex:'.$this->registrationIdentity->nameRule()],
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:'.$this->registrationIdentity->nameRule()],
            'suffix' => ['nullable', 'string', 'max:20', 'regex:/\A[\p{L}\p{M}\p{N} .\x27-]*\z/u'],
            'email' => [...$this->emailRules(), Rule::unique('users', 'email')->ignore($account?->id), Rule::unique('users', 'canonical_email')->ignore($account?->id)],
            'contact_no' => ['required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users', 'canonical_mobile')->ignore($account?->id)],
        ];
    }

    private function registrationValidationMessages(bool $includeTinFormat = false): array
    {
        $messages = [
            'contact_no.regex' => self::MOBILE_FORMAT_ERROR,
            'contact_no.unique' => self::MOBILE_DUPLICATE_ERROR,
        ];

        if ($includeTinFormat) {
            $messages['tin.regex'] = self::TIN_FORMAT_ERROR;
        }

        return $messages;
    }

    private function emailRules(): array
    {
        return [
            'required',
            'email:rfc',
            'max:255',
        ];
    }

    private function psgcAddressRules(bool $postalRequired = false, bool $addressRequired = true): array
    {
        $rules = $this->psgcAddressService->validationRules();

        if (! $addressRequired) {
            $rules['address_mode'] = ['nullable', Rule::in(['psgc', 'manual'])];
            $rules['region_code'] = ['required_if:address_mode,psgc', 'nullable', 'regex:/^\d{10}$/'];
            $rules['province_code'] = ['required_if:address_mode,psgc', 'nullable', 'regex:/^(?:\d{10}|NCR)$/'];
            $rules['municipality_code'] = ['required_if:address_mode,psgc', 'nullable', 'regex:/^\d{10}$/'];
            $rules['barangay_code'] = ['required_if:address_mode,psgc', 'nullable', 'regex:/^\d{10}$/'];
            $rules['manual_region'] = ['required_if:address_mode,manual', 'nullable', 'string', 'max:120'];
            $rules['manual_province'] = ['required_if:address_mode,manual', 'nullable', 'string', 'max:120'];
            $rules['manual_municipality'] = ['required_if:address_mode,manual', 'nullable', 'string', 'max:120'];
            $rules['manual_barangay'] = ['required_if:address_mode,manual', 'nullable', 'string', 'max:120'];
        } else {
            $rules['address_mode'] = ['required', Rule::in(['psgc'])];
            foreach (['manual_region', 'manual_province', 'manual_municipality', 'manual_barangay'] as $field) {
                $rules[$field] = ['prohibited'];
            }
        }

        return array_replace($rules, [
            'street_address' => $addressRequired ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'postal_code' => $postalRequired ? ['required', 'digits:4'] : ['nullable', 'digits:4'],
        ]);
    }

    private function resolveAddress(array $validated): array
    {
        if (empty($validated['address_mode'])) {
            return [
                'region' => $validated['region'] ?? null,
                'province' => $validated['province'] ?? null,
                'municipality' => $validated['municipality'] ?? null,
                'barangay' => $validated['barangay'] ?? null,
            ];
        }

        return $this->psgcAddressService->resolve($validated);
    }

    private function normalizeIdentity(Request $request): void
    {
        foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => trim((string) $request->input($field))]);
            }
        }

        $request->merge([
            'email' => $this->registrationIdentity->normalizeEmail((string) $request->input('email')),
            'contact_no' => $this->registrationIdentity->normalizeMobile((string) $request->input('contact_no')),
        ]);
    }

    private function normalizeBusinessIdentifiers(Request $request): void
    {
        $request->merge([
            'shop_name' => trim((string) $request->input('shop_name')),
            'canonical_shop_name' => $this->registrationIdentity->canonicalText((string) $request->input('shop_name')),
            'business_name' => trim((string) $request->input('business_name')),
            'canonical_company_name' => $this->registrationIdentity->canonicalText((string) $request->input('business_name')),
            'tin' => trim((string) $request->input('tin')),
            'canonical_tin' => $this->registrationIdentity->canonicalDigits((string) $request->input('tin')),
            'sec_registration_no' => $this->registrationIdentity->canonicalCode((string) $request->input('sec_registration_no')),
            'canonical_sec_registration_no' => $this->registrationIdentity->canonicalCode((string) $request->input('sec_registration_no')),
        ]);
    }

    private function createVerifiedAccount(array $validated, array $details, string $role): User
    {
        try {
            $user = DB::transaction(function () use ($validated, $details, $role): User {
                $duplicateEmail = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$validated['email']])->exists();
                if ($duplicateEmail) {
                    throw ValidationException::withMessages(['email' => 'This email address is already linked to an account.']);
                }

                $duplicatePhone = User::query()->where('canonical_mobile', $validated['contact_no'])
                    ->orWhere('contact_no', $validated['contact_no'])
                    ->exists();
                if ($duplicatePhone) {
                    throw ValidationException::withMessages(['contact_no' => self::MOBILE_DUPLICATE_ERROR]);
                }

                $user = User::query()->forceCreate(array_merge([
                    'first_name' => trim($validated['first_name']),
                    'middle_name' => filled($validated['middle_name'] ?? null) ? trim($validated['middle_name']) : null,
                    'last_name' => trim($validated['last_name']),
                    'suffix' => filled($validated['suffix'] ?? null) ? trim($validated['suffix']) : null,
                    'email' => $validated['email'],
                    'canonical_email' => $validated['email'],
                    'contact_no' => $validated['contact_no'],
                    'canonical_mobile' => $validated['contact_no'],
                    'password' => Hash::make($validated['password']),
                    'role' => $role,
                    'status' => 'unverified',
                    'terms_accepted_at' => now(),
                    'terms_version' => config('registration.policy_version'),
                    'privacy_accepted_at' => now(),
                    'privacy_version' => config('registration.policy_version'),
                ], $details));

                return $user;
            });
        } catch (QueryException $exception) {
            if (User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$validated['email']])->exists()) {
                throw ValidationException::withMessages(['email' => 'This email address is already linked to an account.']);
            }

            if (User::query()->where('canonical_mobile', $validated['contact_no'])->orWhere('contact_no', $validated['contact_no'])->exists()) {
                throw ValidationException::withMessages(['contact_no' => self::MOBILE_DUPLICATE_ERROR]);
            }

            foreach (['canonical_shop_name', 'canonical_tin', 'canonical_sec_registration_no', 'canonical_license_number', 'canonical_plate', 'canonical_company_name'] as $field) {
                if (filled($details[$field] ?? null) && User::query()->where($field, $details[$field])->exists()) {
                    throw ValidationException::withMessages([$field => 'This value is already linked to an account.']);
                }
            }

            throw $exception;
        }

        return $user;
    }

    private function beginVerification(User $user): RedirectResponse
    {
        $record = EmailVerificationCode::query()->firstOrNew(['user_id' => $user->id]);

        session([
            'verification_user_id' => $user->id,
            'verification_email' => Str::lower(trim($user->email)),
        ]);

        try {
            $this->issueVerificationCode($user, $record);
        } catch (\Throwable $exception) {
            report($exception);
            $record->delete();

            return redirect()->route('verification.notice')
                ->with('error', 'We could not send the verification code. You can request a new one below.');
        }

        return redirect()->route('verification.notice')
            ->with('success', 'A 6-digit verification code has been sent to your email address.');
    }

    private function issueVerificationCode(User $user, EmailVerificationCode $record): void
    {
        $code = (string) random_int(100000, 999999);
        $record->fill([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'last_sent_at' => now(),
            'channel' => 'email',
        ])->save();

        try {
            $user->notify(new EmailVerificationCodeNotification($code));
        } catch (\Throwable $exception) {
            $record->forceFill(['code_hash' => null, 'expires_at' => null])->save();
            throw $exception;
        }
    }

    private function accountStateMessage(User $user): string
    {
        return match ($user->status) {
            'unverified' => 'Verify your email address before signing in.',
            'pending', 'pending_approval' => 'Your application is under review. EZiCart will notify you after the decision.',
            'pending_activation' => 'Activate your rider account using the latest link sent to your email before signing in.',
            'rejected' => 'Your application was not approved. '.($user->rejection_reason ?: 'Contact support for next steps.'),
            'suspended' => 'Your account is suspended. '.($user->suspension_reason ?: 'Contact support for assistance.'),
            default => 'This account is not eligible to sign in yet. Contact support for assistance.',
        };
    }
}
