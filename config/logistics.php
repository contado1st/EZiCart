<?php

return [
    'maximum_delivery_attempts' => (int) env('LOGISTICS_MAX_DELIVERY_ATTEMPTS', 3),
    'delivery_code_ttl_minutes' => (int) env('LOGISTICS_DELIVERY_CODE_TTL_MINUTES', 60),
    'delivery_code_max_attempts' => (int) env('LOGISTICS_DELIVERY_CODE_MAX_ATTEMPTS', 5),
    'maximum_active_deliveries_per_rider' => (int) env('LOGISTICS_MAX_ACTIVE_DELIVERIES_PER_RIDER', 10),
    'dashboard_cache_seconds' => (int) env('LOGISTICS_DASHBOARD_CACHE_SECONDS', 20),
];
