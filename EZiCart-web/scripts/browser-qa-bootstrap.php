<?php

use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Vite;

// Dedicated loopback QA only. Does not change .env or configured business storage.
$qaRoot = dirname(__DIR__).'/storage/framework/testing/browser-qa-20261113';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->afterBootstrapping(LoadConfiguration::class, function () use ($qaRoot): void {
    config(['app.env' => 'testing', 'app.url' => 'http://127.0.0.1:8017', 'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $qaRoot.'/database.sqlite', 'database.connections.sqlite.url' => null,
        'cache.default' => 'array', 'session.driver' => 'database', 'session.cookie' => 'ezi_browser_qa', 'session.domain' => null,
        'session.secure' => false, 'mail.default' => 'array', 'queue.default' => 'sync', 'hashing.driver' => 'bcrypt',
        'filesystems.disks.private.root' => $qaRoot.'/private', 'filesystems.disks.public.root' => $qaRoot.'/public']);
});
$app->afterBootstrapping(BootProviders::class, function () use ($app, $qaRoot): void {
    $app->make(Vite::class)->useHotFile($qaRoot.'/unused.hot');
});

return $app;
