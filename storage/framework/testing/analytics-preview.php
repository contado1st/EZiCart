<?php
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$report = [
    'title' => 'Marketplace analytics', 'role' => 'ecommerce_admin', 'commercial' => true,
    'generated_at' => '2026-10-21T12:00:00+08:00', 'timezone' => 'Asia/Manila', 'currency' => 'PHP',
    'filters' => ['from' => '2026-10-01', 'to' => '2026-10-03', 'interval' => 'day', 'comparison' => 'previous'],
    'comparison' => ['from' => '2026-09-28', 'to' => '2026-09-30'],
    'metrics' => [
        ['key' => 'orders', 'label' => 'Orders placed', 'value' => 15, 'previous' => 10, 'change' => 50, 'unit' => 'count'],
        ['key' => 'gross', 'label' => 'Gross merchandise value', 'value' => 1500.25, 'previous' => 1000.5, 'change' => 49.95, 'unit' => 'money'],
        ['key' => 'net', 'label' => 'Net seller sales', 'value' => 1350.22, 'previous' => 900.45, 'change' => 49.95, 'unit' => 'money'],
        ['key' => 'commission', 'label' => 'Recognized commission', 'value' => 150.03, 'previous' => 100.05, 'change' => 49.95, 'unit' => 'money'],
    ],
    'trend' => [
        ['period' => '2026-10-01', 'orders' => 3, 'completed' => 2, 'delivered' => 3, 'net' => 300.1],
        ['period' => '2026-10-02', 'orders' => 5, 'completed' => 4, 'delivered' => 4, 'net' => 450.12],
        ['period' => '2026-10-03', 'orders' => 7, 'completed' => 5, 'delivered' => 6, 'net' => 600],
    ],
    'statuses' => [['label' => 'Completed', 'value' => 11], ['label' => 'Cancelled', 'value' => 2], ['label' => 'Preparing', 'value' => 2]],
    'sections' => [
        ['key' => 'statuses', 'title' => 'Order status distribution', 'rows' => [['Status' => 'COMPLETED', 'Orders' => 11], ['Status' => 'CANCELLED', 'Orders' => 2]]],
        ['key' => 'products', 'title' => 'Products ranked by gross merchandise value', 'rows' => [['Product' => str_repeat('W', 120), 'Units fulfilled' => 3, 'Gross PHP' => 300.10], ['Product' => '=SAFE TEXT NOT A FORMULA', 'Units fulfilled' => 2, 'Gross PHP' => 200]]],
    ],
    'notes' => ['Synthetic rendering fixture. No customer data. Metrics are illustrative QA inputs.', 'Orders are grouped by placement date; outcomes reflect the latest recorded state.'],
    'insights' => [],
];
$report['sections'][] = ['key' => 'orders', 'title' => 'Source orders', 'rows' => array_map(fn ($index) => [
    'Order' => 'QA-ORDER-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
    'Placed' => '2026-10-02', 'Status' => 'COMPLETED', 'Municipality' => 'Majayjay',
    'Gross PHP' => 100.25, 'Seller discount PHP' => 5, 'Platform discount PHP' => 0,
    'Refund PHP' => 0, 'Platform reversal PHP' => 0, 'Commission PHP' => 9.53,
    'Net seller sales PHP' => 85.72, 'Shipping PHP' => 50,
], range(1, 60))];
$exports = $app->make(App\Services\AnalyticsExportService::class);
file_put_contents(__DIR__.'/analytics-preview.pdf', $exports->pdf($report));
file_put_contents(__DIR__.'/analytics-preview.xlsx', $exports->xlsx($report));
echo "Synthetic report exports generated.\n";
