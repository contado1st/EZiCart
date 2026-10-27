<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
chdir(dirname(__DIR__, 2));
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection('mysql');
$connection = $db->getConfig();
if (! $app->environment(['local', 'testing']) || ! in_array($connection['host'], ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "This runner requires a local MySQL server and a local/testing application environment.\n");
    exit(2);
}
$name = 'ezicart_audit_test_'.bin2hex(random_bytes(6));
if (! preg_match('/^ezicart_audit_test_[a-f0-9]{12}$/', $name)) {
    exit(2);
}
try {
    $db->statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (Throwable $exception) {
    fwrite(STDERR, 'Isolated MySQL test schema creation failed: '.$exception::class.PHP_EOL);
    exit(2);
}
try {
    $environment = [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $name, 'DB_URL' => '',
        'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
        'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        'EZICART_ALLOW_DISPOSABLE_MYSQL' => '1',
    ];
    $filter = $argv[1] ?? 'CheckoutIntegrityTest|CheckoutConcurrencyTest|WorkflowRevisionTest';
    $process = new Process([PHP_BINARY, 'artisan', 'test', '--compact', '--filter='.$filter], getcwd(), $environment, timeout: 600);
    $code = $process->run();
    echo $process->getOutput();
    echo $process->getErrorOutput();
} finally {
    // Only this randomly named schema was created and may be dropped by this runner.
    $db->statement('DROP DATABASE `'.$name.'`');
}
exit($code);
