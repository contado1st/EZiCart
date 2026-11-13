<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection()->getConfig();
if ($connection['driver'] !== 'mysql') {
    throw new RuntimeException('This backup helper is scoped to the inspected MySQL database.');
}
$label = $argv[1] ?? 'partial-features';
if (! preg_match('/\A[a-z0-9-]{1,60}\z/', $label)) {
    throw new InvalidArgumentException('Use a simple backup label.');
}
$executable = (new ExecutableFinder)->find('mysqldump');
if (! $executable) {
    throw new RuntimeException('mysqldump is not available. No migration should proceed without the backup.');
}
$directory = storage_path('app/private/backups');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$options = tempnam($directory, '.mysql-options-');
$escape = fn ($value) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $value).'"';
$output = $directory.'/before-'.$label.'-'.now()->format('Ymd-His').'.sql';
try {
    file_put_contents($options, "[client]\nhost=".$escape($connection['host'])."\nport=".(int) $connection['port']."\nuser=".$escape($connection['username'])."\npassword=".$escape($connection['password'])."\n");
    (new Process([$executable, '--defaults-extra-file='.$options, '--single-transaction', '--no-tablespaces', '--set-gtid-purged=OFF', '--result-file='.$output, $connection['database']]))->setTimeout(120)->mustRun();
    if (filesize($output) < 100) {
        throw new RuntimeException('Backup output is unexpectedly empty.');
    }
    echo 'Private database backup saved: '.basename($output).PHP_EOL;
} finally {
    if (is_file($options)) {
        unlink($options);
    }
}
