<?php

return [
    'driver' => env('HASH_DRIVER', 'argon2id'),
    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => false,
        'limit' => 72,
    ],
    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'time' => env('ARGON_TIME', 4),
        'threads' => env('ARGON_THREADS', 1),
        'verify' => false,
    ],
    'argon2id' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'time' => env('ARGON_TIME', 4),
        'threads' => env('ARGON_THREADS', 1),
        'verify' => false,
    ],
    'rehash_on_login' => true,
];
