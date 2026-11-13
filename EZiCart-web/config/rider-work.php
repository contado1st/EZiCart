<?php

return [
    // Collect choices first; activate deliberately after the readiness audit and operator notice.
    'enforce_availability' => env('RIDER_WORK_AVAILABILITY_ENFORCED', false),
];
