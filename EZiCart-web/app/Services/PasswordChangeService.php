<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordChangeCodeNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordChangeService
{
    public function request(User $user, #[\SensitiveParameter] string $password): void
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');
        if (! app(GmailApiOtpSender::class)->isConfigured() && ! in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend'], true) && ! Notification::isFake()) {
            throw ValidationException::withMessages(['password_code' => 'Email delivery is unavailable. Please contact administration.']);
        }
        $code = (string) random_int(100000, 999999);
        $reference = (string) Str::uuid();
        $recipient = DB::transaction(function () use ($user, $password, $code, $reference) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($password, $locked->password)) {
                throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
            }
            $old = DB::table('password_change_challenges')->where('user_id', $locked->id)->first();
            if ($old && Carbon::parse($old->created_at)->gt(now()->subSeconds(config('account-security.password_otp_cooldown_seconds')))) {
                throw ValidationException::withMessages(['password_code' => 'Wait one minute before requesting another code.']);
            }
            DB::table('password_change_challenges')->updateOrInsert(['user_id' => $locked->id], ['reference' => $reference, 'email' => $locked->email,
                'password_fingerprint' => hash('sha256', $locked->password), 'code_hash' => Hash::make($code), 'attempts' => 0,
                'expires_at' => now()->addMinutes(max(1, config('account-security.password_otp_minutes'))), 'created_at' => now(), 'updated_at' => now()]);

            return $locked;
        }, 3);
        try {
            $recipient->notify(new PasswordChangeCodeNotification($code));
        } catch (\Throwable) {
            DB::table('password_change_challenges')->where('user_id', $user->id)->where('reference', $reference)->delete();
            // Sender exceptions can contain an email request body, including the code.
            report(new \RuntimeException('Password-change verification email delivery failed.'));
            throw ValidationException::withMessages(['password_code' => 'The verification email could not be sent. Please try again.']);
        }
    }

    public function change(User $user, #[\SensitiveParameter] string $code, #[\SensitiveParameter] string $currentPassword, #[\SensitiveParameter] string $password): User
    {
        $result = DB::transaction(function () use ($user, $code, $currentPassword, $password): User|string {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $challenge = DB::table('password_change_challenges')->where('user_id', $locked->id)->lockForUpdate()->first();
            if (! Hash::check($currentPassword, $locked->password)) {
                return 'Your current password is incorrect.';
            }
            if (! $challenge || $challenge->email !== $locked->email || ! hash_equals($challenge->password_fingerprint, hash('sha256', $locked->password))
                || Carbon::parse($challenge->expires_at)->lte(now()) || $challenge->attempts >= config('account-security.password_otp_attempts')) {
                return 'Request a new password verification code; this one is missing, expired or unavailable.';
            }
            if (! Hash::check($code, $challenge->code_hash)) {
                DB::table('password_change_challenges')->where('user_id', $locked->id)->increment('attempts');

                return 'The verification code is incorrect.';
            }
            Auth::logoutOtherDevices($currentPassword);
            $locked->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            DB::table('password_change_challenges')->where('user_id', $locked->id)->delete();
            app(AdministrativeAuditService::class)->record($locked, $locked, 'account_password_changed', [], ['email_verified' => true], 'Password changed after registered-email verification.');

            return $locked;
        }, 3);
        if (is_string($result)) {
            throw ValidationException::withMessages(['password_code' => $result]);
        }

        return $result;
    }
}
