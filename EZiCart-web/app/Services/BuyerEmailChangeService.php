<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\BuyerEmailChangeNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BuyerEmailChangeService
{
    public function request(User $buyer, string $email, #[\SensitiveParameter] string $password): void
    {
        $email = Str::lower(trim($email));
        $code = (string) random_int(100000, 999999);
        $reference = (string) Str::uuid();
        DB::transaction(function () use ($buyer, $email, $password, $code, $reference): void {
            $locked = User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            $this->authorize($locked);
            if (! Hash::check($password, $locked->password)) {
                throw ValidationException::withMessages(['email_password' => 'Your current password is incorrect.']);
            }
            if (User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists()) {
                throw ValidationException::withMessages(['new_email' => 'This email address is already in use.']);
            }
            $previous = DB::table('buyer_email_changes')->where('user_id', $locked->id)->first();
            if ($previous && Carbon::parse($previous->created_at)->gt(now()->subMinute())) {
                throw ValidationException::withMessages(['new_email' => 'Wait one minute before requesting another code.']);
            }
            DB::table('buyer_email_changes')->updateOrInsert(['user_id' => $locked->id], [
                'reference' => $reference, 'current_email' => $locked->email, 'proposed_email' => $email,
                'password_fingerprint' => hash('sha256', $locked->password), 'code_hash' => Hash::make($code),
                'attempts' => 0, 'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);

        try {
            Notification::route('mail', $email)->notify(new BuyerEmailChangeNotification($code));
        } catch (\Throwable $exception) {
            DB::table('buyer_email_changes')->where('user_id', $buyer->id)->where('reference', $reference)->delete();
            report($exception);
            throw ValidationException::withMessages(['new_email' => 'The verification email could not be sent. Please try again.']);
        }
    }

    public function confirm(User $buyer, string $code): User
    {
        try {
            // Return errors instead of throwing inside the transaction so attempts persist.
            $result = DB::transaction(function () use ($buyer, $code): User|string {
                $locked = User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
                $this->authorize($locked);
                $challenge = DB::table('buyer_email_changes')->where('user_id', $locked->id)->lockForUpdate()->first();
                if (! $challenge) {
                    return 'Request a verification code first.';
                }
                if (Carbon::parse($challenge->expires_at)->lte(now()) || $challenge->attempts >= 5
                    || $challenge->current_email !== $locked->email
                    || ! hash_equals($challenge->password_fingerprint, hash('sha256', $locked->password))) {
                    DB::table('buyer_email_changes')->where('user_id', $locked->id)->delete();

                    return 'This code expired or is no longer valid. Request a new code.';
                }
                if (! Hash::check($code, $challenge->code_hash)) {
                    DB::table('buyer_email_changes')->where('user_id', $locked->id)->increment('attempts');

                    return 'The verification code is incorrect.';
                }
                if (User::whereKeyNot($locked->id)->whereRaw('LOWER(TRIM(email)) = ?', [$challenge->proposed_email])->exists()) {
                    DB::table('buyer_email_changes')->where('user_id', $locked->id)->delete();

                    return 'This email address is already in use. Request a different address.';
                }
                $oldEmail = $locked->email;
                $locked->forceFill(['email' => $challenge->proposed_email, 'email_verified_at' => now(),
                    'web_auth_version' => (int) $locked->web_auth_version + 1, 'remember_token' => Str::random(60)])->save();
                DB::table('buyer_email_changes')->where('user_id', $locked->id)->delete();
                DB::afterCommit(function () use ($oldEmail): void {
                    try {
                        Notification::route('mail', $oldEmail)->notify(new BuyerEmailChangeNotification);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                });

                return $locked;
            }, 3);
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['email_code' => 'This email address is already in use. Request a different address.']);
        }
        if (is_string($result)) {
            throw ValidationException::withMessages(['email_code' => $result]);
        }

        return $result;
    }

    private function authorize(User $buyer): void
    {
        abort_unless($buyer->role === 'buyer' && $buyer->status === 'approved', 403);
    }
}
