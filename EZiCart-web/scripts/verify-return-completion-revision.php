<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
foreach (['completion_enrolled_at', 'auto_completion_due_at', 'auto_completed_at'] as $column) {
    if (! Schema::hasColumn('orders', $column)) {
        throw new RuntimeException('Missing completion schema.');
    }
}
if (! Schema::hasTable('seller_return_responses')) {
    throw new RuntimeException('Missing seller response schema.');
}
$invalidResponses = DB::table('seller_return_responses')->whereNotIn('stage', ['awaiting_seller', 'seller_approved', 'seller_rejected', 'escalated', 'deadline_approved', 'resolved'])->count();
$invalidEnrollments = DB::table('orders')->whereNotNull('auto_completion_due_at')->where(fn ($q) => $q->whereNull('delivered_at')->orWhereNull('completion_enrolled_at')->orWhereRaw('auto_completion_due_at <> DATE_ADD(delivered_at, INTERVAL 168 HOUR)'))->count();
$result = ['result' => $invalidResponses + $invalidEnrollments === 0 ? 'passed' : 'failed', 'scope' => 'Read-only configured MySQL schema and enrollment bounds; no deadline/completion command applied', 'users' => DB::table('users')->count(), 'orders' => DB::table('orders')->count(), 'disputes' => DB::table('disputes')->count(), 'seller_responses' => DB::table('seller_return_responses')->count(), 'enrolled_orders' => DB::table('orders')->whereNotNull('completion_enrolled_at')->count(), 'auto_completed_orders' => DB::table('orders')->whereNotNull('auto_completed_at')->count(), 'invalid_responses' => $invalidResponses, 'invalid_enrollments' => $invalidEnrollments];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($result['result'] === 'passed' ? 0 : 1);
