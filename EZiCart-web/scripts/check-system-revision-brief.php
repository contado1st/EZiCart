<?php

use App\Models\User;
use App\Notifications\PasswordChangeCodeNotification;
use App\Services\PasswordChangeService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
});
$source = DB::connection();
$original = DB::getDefaultConnection();
if ($source->getDriverName() !== 'mysql' || ! app()->environment(['local', 'testing'])) {
    throw new RuntimeException('Local/testing MySQL only; fixtures must use a disposable schema.');
}
$connect = function (string $name) use ($source): void {
    if (! preg_match('/\Aezicart_brief_check_\d{14}_[a-f0-9]{8}\z/', $name) || $name === $source->getDatabaseName()) {
        throw new RuntimeException('Unsafe schema target.');
    }
    config(['database.connections.brief_copy' => [...$source->getConfig(), 'name' => 'brief_copy', 'database' => $name, 'url' => null], 'mail.default' => 'array', 'session.driver' => 'array']);
    DB::setDefaultConnection('brief_copy');
    Schema::clearResolvedInstance('db.schema');
    if ((new User)->getConnection()->getDatabaseName() !== $name || User::query()->getConnection()->getName() !== 'brief_copy') {
        throw new RuntimeException('Model fixture isolation failed.');
    }
};
if (($argv[1] ?? null) === 'worker') {
    $connect($argv[2]);
    $user = User::findOrFail((int) $argv[3]);
    Auth::guard()->setUser($user);
    while (microtime(true) < (float) $argv[4]) {
        usleep(10000);
    }
    try {
        app(PasswordChangeService::class)->change($user, (string) getenv('BRIEF_TEST_CODE'), 'Fixture-Password123!', 'Updated-Fixture123!');
        echo 'committed';
    } catch (ValidationException) {
        echo 'rejected';
    }
    exit;
}
$name = 'ezicart_brief_check_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
$quote = fn ($value) => '`'.str_replace('`', '``', $value).'`';
$counts = fn () => collect(['users', 'products', 'orders', 'order_items', 'administrative_audits', 'ledger_transactions'])->mapWithKeys(fn ($table) => [$table => $source->table($table)->count()])->all();
$before = $counts();
$created = false;
$workers = [];
$result = [];
try {
    $source->statement('CREATE DATABASE '.$quote($name));
    $created = true;
    $tables = $source->table('information_schema.tables')->where('table_schema', $source->getDatabaseName())->where('table_type', 'BASE TABLE')->selectRaw('TABLE_NAME AS table_name')->pluck('table_name')->all();
    foreach (array_diff($tables, ['password_change_challenges']) as $table) {
        if ($source->table($table)->count() > 10000) {
            throw new RuntimeException('Copy limit exceeded; use a prepared disposable snapshot.');
        }
        $source->statement('CREATE TABLE '.$quote($name).'.'.$quote($table).' LIKE '.$quote($source->getDatabaseName()).'.'.$quote($table));
        $columns = $source->table('information_schema.columns')->where('table_schema', $source->getDatabaseName())->where('table_name', $table)->whereRaw("COALESCE(GENERATION_EXPRESSION, '') = ''")->orderBy('ordinal_position')->selectRaw('COLUMN_NAME AS column_name')->pluck('column_name')->all();
        $list = implode(', ', array_map($quote, $columns));
        $source->statement('INSERT INTO '.$quote($name).'.'.$quote($table).' ('.$list.') SELECT '.$list.' FROM '.$quote($source->getDatabaseName()).'.'.$quote($table));
    }
    $connect($name);
    $userCount = User::count();
    $migration = require dirname(__DIR__).'/database/migrations/2026_11_15_070911_create_password_change_challenges_table.php';
    $migration->up();
    $migration->down();
    $migration->up();
    $result['migration_up_down_reapply'] = User::count() === $userCount && DB::table('password_change_challenges')->count() === 0;
    Notification::fake();
    $user = User::forceCreate(['email' => Str::uuid().'@example.invalid', 'password' => 'Fixture-Password123!', 'first_name' => 'Brief', 'last_name' => 'Isolated fixture', 'role' => 'buyer', 'status' => 'approved', 'contact_no' => '09123456789']);
    app(PasswordChangeService::class)->request($user, 'Fixture-Password123!');
    $code = Notification::sent($user, PasswordChangeCodeNotification::class)->sole()->code;
    $row = DB::table('password_change_challenges')->where('user_id', $user->id)->first();
    $result['only_code_hash_stored'] = Hash::check($code, $row->code_hash) && ! str_contains(json_encode($row), $code);
    $start = (string) (microtime(true) + 2);
    for ($i = 0; $i < 2; $i++) {
        $worker = new Process([PHP_BINARY, __FILE__, 'worker', $name, (string) $user->id, $start], dirname(__DIR__), ['BRIEF_TEST_CODE' => $code]);
        $worker->setTimeout(45)->start();
        $workers[] = $worker;
    }
    $outcomes = [];
    foreach ($workers as $worker) {
        $worker->wait();
        if (! $worker->isSuccessful()) {
            throw new RuntimeException('Password change worker failed.');
        }
        $outcomes[] = trim($worker->getOutput());
    }
    sort($outcomes);
    $result['one_concurrent_password_change'] = $outcomes === ['committed', 'rejected'] && Hash::check('Updated-Fixture123!', $user->fresh()->password)
        && DB::table('password_change_challenges')->where('user_id', $user->id)->count() === 0
        && DB::table('administrative_audits')->where('entity_id', $user->id)->where('action', 'account_password_changed')->count() === 1;
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
    DB::setDefaultConnection($original);
    Schema::clearResolvedInstance('db.schema');
    DB::purge('brief_copy');
    if ($created) {
        if (! preg_match('/\Aezicart_brief_check_\d{14}_[a-f0-9]{8}\z/', $name) || $name === $source->getDatabaseName()) {
            throw new RuntimeException('Unsafe cleanup target.');
        }
        $source->statement('DROP DATABASE '.$quote($name));
    }
}
$result['configured_business_counts_unchanged'] = $before === $counts();
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit(in_array(false, $result, true) ? 1 : 0);
