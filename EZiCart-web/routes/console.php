<?php

use App\Services\SellerPromotionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('accounts:prune-unverified')->daily()->withoutOverlapping();
Schedule::command('orders:expire-seller-deadlines')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('logistics:alert-aging-parcels')->hourly()->withoutOverlapping();
Schedule::command('commerce:process-automation')->everyMinute()->withoutOverlapping();
Schedule::command('finance:release-expired-vouchers')->hourly()->withoutOverlapping();

Artisan::command('commerce:deliver-promotions', function (SellerPromotionService $promotions) {
    $this->info($promotions->deliver().' in-app promotional notifications delivered.');
})->purpose('Deliver active seller promotions to opted-in followers within notification limits');
Schedule::command('commerce:deliver-promotions')->everyFiveMinutes()->withoutOverlapping();
