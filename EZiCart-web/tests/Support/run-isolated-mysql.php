<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
chdir(dirname(__DIR__, 2));
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, json_encode(['result' => 'incomplete', 'runner_error' => $exception::class], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(2);
});
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
    $filter = $argv[1] ?? '/^Tests\\\\Feature\\\\(?:CheckoutIntegrityTest|CheckoutConcurrencyTest|WorkflowRevisionTest)::/';
    // Invoke PHPUnit directly: preserve the test memory limit and avoid an extra child shell.
    $process = new Process([PHP_BINARY, '-d', 'memory_limit=512M', 'vendor/phpunit/phpunit/phpunit', '--configuration=phpunit.xml', '--no-progress', '--filter='.$filter], getcwd(), $environment, timeout: 1200);
    $code = $process->run(static function (string $type, string $output): void {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
    });
} finally {
    // Only this randomly named schema was created and may be dropped by this runner.
    $db->statement('DROP DATABASE `'.$name.'`');
}
exit($code);
