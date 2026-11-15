<?php

return [
    'automatic_start' => (bool) env('DISPATCH_AUTOMATIC_START', true),
    'position_fresh_seconds' => 300,
    'maximum_accuracy_meters' => 250,
    'position_retention_hours' => 24,
    'offer_seconds' => 60,
    'maximum_candidates' => 3,
    'maximum_active_jobs' => 3,
];
