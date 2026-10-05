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

        RateLimiter::for('registration', function (Request $request): Limit {
            $routeName = $request->route()?->getName() ?? $request->path();

            return Limit::perMinute(5)->by($routeName.'|'.$request->ip());
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

        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
