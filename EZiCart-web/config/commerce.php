<?php

return [
    // One current seller order is one parcel. Each seller shipment costs PHP 50.
    'shipping_per_seller_cents' => 5000,
    'commission_basis_points' => 1000,
    'seller_response_hours' => 24,
    'seller_preparation_hours' => 48,
    'parcel_alert_hours' => 24,
    'cancellation_response_hours' => 24,
    // Nullable enrollment fields prevent any historical-delivery mass completion on rollout.
    'auto_completion_rollout_at' => env('AUTO_COMPLETION_ROLLOUT_AT', '2026-11-15T00:00:00+08:00'),
    'rider_acceptance_minutes' => 15,
    'low_stock_threshold' => 5,
    'logistics_provider' => 'EZiCart Logistics',
];
