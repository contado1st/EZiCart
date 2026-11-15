<?php

use App\Models\Voucher;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo json_encode(['database_driver' => DB::getDriverName(),
    'starts_at_column' => Schema::hasColumn('vouchers', 'starts_at'),
    'disabled_reason_column' => Schema::hasColumn('vouchers', 'disabled_reason'),
    'voucher_count' => Voucher::count(), 'available_now_count' => Voucher::availableNow()->count(),
    'explicitly_scheduled_count' => Voucher::whereNotNull('starts_at')->count(),
    'explicit_disable_reason_count' => Voucher::whereNotNull('disabled_reason')->count(),
], JSON_PRETTY_PRINT).PHP_EOL;
