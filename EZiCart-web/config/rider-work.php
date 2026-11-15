<?php

return [
    // Approved riders must opt in before they can receive new work.
    'enforce_availability' => env('RIDER_WORK_AVAILABILITY_ENFORCED', true),
];
