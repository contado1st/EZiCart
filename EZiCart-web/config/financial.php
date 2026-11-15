<?php

return [
    // Mock credits only. A real-money adapter is intentionally not implemented/enabled.
    'mode' => env('FINANCIAL_MODE', 'sandbox'),
    'sandbox_enabled' => env('FINANCIAL_SANDBOX_ENABLED', false),
    'webhook_secret' => env('FINANCIAL_WEBHOOK_SECRET'),
    'maximum_transaction_cents' => 100000000,
    'callback_tolerance_seconds' => 300,
    // New web-only deferred wallet reservations; existing immediate wallet callers are unchanged.
    'wallet_reservation_minutes' => 30,
];
