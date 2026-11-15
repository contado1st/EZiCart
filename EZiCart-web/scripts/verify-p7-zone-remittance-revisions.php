<?php

use App\Models\User;
use App\Services\AnalyticsExportService;
use App\Services\RemittanceReportService;
use App\Services\ZoneReadinessService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$tables = ['users', 'orders', 'areas', 'area_municipalities', 'delivery_rate_versions', 'commerce_settings', 'remittance_batches', 'financial_accounts', 'ledger_transactions', 'administrative_audits', 'notifications'];
$counts = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
$before = $counts();
$hub = new User;
$hub->forceFill(['id' => 0, 'role' => 'sorting_center', 'status' => 'approved']);
$reports = app(RemittanceReportService::class);
$report = $reports->report($hub, ['from' => '2026-11-01', 'to' => '2026-11-14']);
$workbook = app(AnalyticsExportService::class)->xlsx(['filters' => ['from' => '2026-11-01', 'to' => '2026-11-14'], 'timezone' => config('app.timezone')], $reports->sheets($report));
$activation = app(ZoneReadinessService::class)->activation();
$areaQuery = app(ZoneReadinessService::class)->areas($hub)->whereKey(0)->count();
echo json_encode(['driver' => DB::getDriverName(), 'empty_owner_remittance_query' => $report['count'] === 0,
    'empty_workbook_generated' => str_starts_with($workbook, 'PK') && strlen($workbook) > 1000,
    'empty_totals' => $report['totals'], 'empty_scoped_area_query' => $areaQuery === 0,
    'required_tariff_readiness_queries_executed' => count($activation['routes']) === 4,
    'versioned_pricing_enabled' => $activation['enabled'], 'all_four_approvals_present' => $activation['ready'],
    'business_row_counts_unchanged' => $before === $counts(), 'populated_mysql_workflow_tested' => false, 'timed_mysql_race_tested' => false,
], JSON_PRETTY_PRINT).PHP_EOL;
