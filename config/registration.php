<?php

return [
    'policy_version' => env('REGISTRATION_POLICY_VERSION', '2026-10'),
    'unverified_retention_hours' => (int) env('UNVERIFIED_RETENTION_HOURS', 24),
    'registration_per_email_per_minute' => (int) env('REGISTRATION_PER_EMAIL_PER_MINUTE', 10),
    'registration_per_ip_per_minute' => (int) env('REGISTRATION_PER_IP_PER_MINUTE', 100),
    'demo_password' => env('DEMO_USER_PASSWORD'),
    'seller_categories' => [
        'Beauty & Personal Care',
        'Home & Living',
        'Mobiles & Gadgets',
        'Fashion',
        'Electronics',
        'Groceries',
        'Food & Beverages',
        'Health & Wellness',
        'Toys & Hobbies',
        'Sports & Outdoors',
        'Other',
    ],
];
