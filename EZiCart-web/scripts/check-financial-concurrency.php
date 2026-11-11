<?php

/** Isolated synthetic SQLite check. Never connects to the configured application database. */

use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\FinancialLedgerService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = storage_path('framework/testing');
if (! is_dir($directory)) {
    mkdir($directory, 0755, true);
}
$worker = ($argv[1] ?? '') === '--worker';
$path = $worker ? ($argv[2] ?? '') : $directory.'/finance-race-'.Str::uuid().'.sqlite';
if (! preg_match('/^finance-race-[a-f0-9-]+\.sqlite$/', basename($path)) || realpath(dirname($path)) !== realpath($directory)) {
    throw new RuntimeException('Only isolated generated test databases are allowed.');
}
if (! $worker) {
    touch($path);
}
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path, 'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 10000, 'cache.default' => 'array', 'mail.default' => 'array', 'hashing.driver' => 'bcrypt', 'hashing.bcrypt.rounds' => 4]);
DB::purge('sqlite');
if (DB::connection()->getDriverName() !== 'sqlite') {
    throw new RuntimeException('Isolated SQLite required.');
}
DB::statement('PRAGMA busy_timeout = 10000');
$ledger = app(FinancialLedgerService::class);
if ($worker) {
    $start = (float) $argv[7];
    while (microtime(true) < $start) {
        usleep(1000);
    }
    try {
        $ledger->transfer($argv[6], 'concurrency_test', FinancialAccount::findOrFail((int) $argv[4]), FinancialAccount::findOrFail((int) $argv[5]), 7500, User::findOrFail((int) $argv[3]));
        echo 'POSTED';
        exit(0);
    } catch (ValidationException) {
        echo 'INSUFFICIENT_FUNDS';
        exit(3);
    }
}
if (Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]) !== 0) {
    throw new RuntimeException(Artisan::output());
}
$user = User::forceCreate(['email' => 'race-'.Str::uuid().'@example.test', 'password' => 'TestPassword123!', 'first_name' => 'Synthetic', 'last_name' => 'Concurrency', 'contact_no' => '09123456789', 'role' => 'buyer', 'status' => 'approved']);
$available = $ledger->wallet($user);
$treasury = $ledger->treasury();
$ledger->transfer('seed', 'synthetic_test_funding', $ledger->account('test:clearing', 'test', null, 'available', true), $available, 10000, null);
$start = microtime(true) + 2;
$processes = [];
for ($i = 0; $i < 2; $i++) {
    $command = [PHP_BINARY, __FILE__, '--worker', $path, (string) $user->id, (string) $available->id, (string) $treasury->id, 'concurrent-spend-'.$i, (string) $start];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start isolated test worker.');
    }
    fclose($pipes[0]);
    $processes[] = [$process, $pipes];
}
$outcomes = [];
foreach ($processes as [$process, $pipes]) {
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $outcomes[] = ['exit' => $exit, 'result' => $output, 'error' => $error];
}
$reconciliation = $ledger->reconcile();
$balance = $available->refresh()->balance_cents;
$posted = collect($outcomes)->where('exit', 0)->count();
$rejected = collect($outcomes)->where('exit', 3)->count();
$passed = $posted === 1 && $rejected === 1 && $balance === 2500 && $reconciliation['balanced'];
echo json_encode(['passed' => $passed, 'driver' => 'isolated_file_sqlite', 'initial_cents' => 10000, 'concurrent_debits_cents' => [7500, 7500], 'remaining_cents' => $balance, 'outcomes' => $outcomes, 'reconciliation' => $reconciliation], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
DB::disconnect('sqlite');
unlink($path);
exit($passed ? 0 : 1);
