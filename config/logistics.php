<?php

return [
    'maximum_delivery_attempts' => (int) env('LOGISTICS_MAX_DELIVERY_ATTEMPTS', 3),
    'maximum_active_deliveries_per_rider' => (int) env('LOGISTICS_MAX_ACTIVE_DELIVERIES_PER_RIDER', 10),
];
