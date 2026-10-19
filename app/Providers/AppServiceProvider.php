<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request): array {
            $email = Str::lower($request->string('email')->trim()->toString());
            $ip = $request->ip();

            return [
                Limit::perMinute(5)->by('account:'.$email.'|'.$ip),
                Limit::perMinute(100)->by('ip:'.$ip),
            ];
        });

        RateLimiter::for('password-reset-link', function (Request $request): array {
            $email = Str::lower($request->string('email')->trim()->toString());
            $ip = $request->ip();

            return [
                Limit::perMinute(5)->by('account:'.$email.'|'.$ip),
                Limit::perMinute(30)->by('ip:'.$ip),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request): array {
            $email = Str::lower($request->string('email')->trim()->toString());
            $ip = $request->ip();

            return [
                Limit::perMinute(5)->by('account:'.$email.'|'.$ip),
                Limit::perMinute(30)->by('ip:'.$ip),
            ];
        });

        RateLimiter::for('registration', function (Request $request): array {
            $routeName = $request->route()?->getName() ?? $request->path();
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute((int) config('registration.registration_per_email_per_minute', 10))
                    ->by($routeName.'|'.$request->ip().'|'.($email !== '' ? $email : 'missing-email')),
                Limit::perMinute((int) config('registration.registration_per_ip_per_minute', 100))
                    ->by($routeName.'|ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('email-verification', function (Request $request): Limit {
            return Limit::perMinute(10)->by('verification:'.$request->ip().'|'.Str::lower(trim((string) $request->input('email'))));
        });

        RateLimiter::for('email-verification-resend', function (Request $request): Limit {
            return Limit::perMinute(3)->by('verification-resend:'.$request->ip().'|'.Str::lower(trim((string) $request->input('email'))));
        });

        RateLimiter::for('rider-activation', function (Request $request): Limit {
            return Limit::perMinute(10)->by('rider-activation:'.$request->ip());
        });

        RateLimiter::for('operational-action', function (Request $request): Limit {
            $identity = $request->user()?->getAuthIdentifier() ?? $request->ip();
            $routeName = $request->route()?->getName() ?? $request->path();

            return Limit::perMinute(30)->by($identity.'|'.$routeName);
        });

        RateLimiter::for('pickup-schedule', function (Request $request): Limit {
            $identity = $request->user()?->getAuthIdentifier() ?? $request->ip();
            $routeName = $request->route()?->getName() ?? $request->path();

            return Limit::perMinute(20)->by($identity.'|'.$routeName);
        });

        if (app()->isProduction() && str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
