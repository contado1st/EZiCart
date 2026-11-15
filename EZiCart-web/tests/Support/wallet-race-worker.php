<?php

use App\Models\User;
use App\Services\DemoFundingCodeService;
use App\Services\WalletTransferService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$payload = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$index = (int) $argv[2];
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $payload['database'], 'database.connections.sqlite.url' => null, 'financial.sandbox_enabled' => true]);
DB::purge('sqlite');
DB::statement('PRAGMA busy_timeout = 10000');
file_put_contents($argv[1].'.ready'.$index, 'ready');
$deadline = microtime(true) + 10;
while (! is_file($argv[1].'.start') && microtime(true) < $deadline) {
    usleep(10000);
}
try {
    $actor = User::findOrFail($payload['sender']);
    if ($payload['mode'] === 'code') {
        app(DemoFundingCodeService::class)->redeem($actor, $payload['code']);
    } else {
        $service = app(WalletTransferService::class);
        $recipient = User::findOrFail($payload['recipients'][$index]);
        $review = $service->review($actor, $service->reference($recipient), 8000, $payload['keys'][$index], 'Concurrent demo test');
        $service->send($actor, $review['reference'], 8000, $review['request_key'], $review['note'], $review['review_expires'], $review['review_hash']);
    }
    echo json_encode(['result' => 'posted']);
} catch (ValidationException $exception) {
    echo json_encode(['result' => 'validation_refused']);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage());
    exit(1);
}
