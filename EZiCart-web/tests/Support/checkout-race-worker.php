<?php

// Isolated test worker: accepts only disposable test databases, never the app database.
use App\Models\User;
use App\Services\PlacePurchaseService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
[$script, $driver, $database, $barrier, $buyerId, $productId, $token, $quoteHash, $worker, $voucherId] = $argv;
$resolved = $driver === 'sqlite' ? realpath($database) : $database;
if (! str_starts_with(basename($barrier), 'ezicart-race-') || realpath(dirname($barrier)) !== realpath(sys_get_temp_dir())) {
    exit(2);
}
if ($driver === 'sqlite' && (! $resolved || realpath($database) !== realpath($barrier))) {
    exit(2);
}
if ($driver !== 'sqlite' && ($driver !== 'mysql' || getenv('EZICART_ALLOW_DISPOSABLE_MYSQL') !== '1' || ! preg_match('/^ezicart_audit_test_[a-f0-9]{12}$/', $database))) {
    exit(2);
}
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => $driver, 'DB_DATABASE' => $resolved, 'DB_URL' => '', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => $driver, 'database.connections.'.$driver.'.database' => $resolved, 'database.connections.'.$driver.'.url' => null, 'mail.default' => 'array', 'queue.default' => 'sync']);
$buyer = User::findOrFail($buyerId);
file_put_contents($barrier.'.ready'.$worker, 'ready');
$deadline = microtime(true) + 10;
while (! is_file($barrier.'.start')) {
    if (microtime(true) > $deadline) {
        exit(3);
    }
    usleep(10000);
}
try {
    $purchase = app(PlacePurchaseService::class)->place($buyer, $token, str_repeat('a', 64), [['product_id' => (int) $productId, 'quantity' => 1]], $quoteHash, $voucherId === '0' ? null : (int) $voucherId, [
        'recipient_name' => 'Race Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Test Street',
    ]);
    echo json_encode(['result' => 'placed', 'purchase_id' => $purchase->id]);
} catch (ValidationException) {
    echo json_encode(['result' => 'rejected']);
} catch (Throwable $exception) {
    echo json_encode(['result' => 'error', 'class' => $exception::class]);
    exit(1);
}
