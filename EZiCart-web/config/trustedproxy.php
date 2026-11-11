<?php

return [
    // Read after Laravel loads .env; Cloudflare Tunnel reaches the local server over loopback.
    'proxies' => env('TRUSTED_PROXIES') ?? (env('APP_ENV') === 'local' ? ['127.0.0.1', '::1'] : null),
];
