<?php

use App\Services\GmailApiOtpSender;
use App\Services\ProductUploadProcessor;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! Schema::hasTable('password_change_challenges')) {
    throw new RuntimeException('Password-change challenge migration is missing.');
}
$versions = [];
foreach (['ffmpeg', 'ffprobe'] as $name) {
    $process = new Process([app(ProductUploadProcessor::class)->binary($name), '-version']);
    $process->setTimeout(10)->mustRun();
    $versions[$name] = strtok($process->getOutput(), "\r\n");
}
$result = ['result' => extension_loaded('gd') ? 'passed' : 'failed', 'scope' => 'Read-only configured runtime and tool checks; no OTP sent, live media processed or dispatch applied',
    'users' => DB::table('users')->count(), 'products' => DB::table('products')->count(), 'orders' => DB::table('orders')->count(),
    'password_challenges' => DB::table('password_change_challenges')->count(), 'dispatch_cycles' => DB::table('dispatch_cycles')->count(),
    'gd_available' => extension_loaded('gd'), 'gmail_api_configured' => app(GmailApiOtpSender::class)->isConfigured(),
    'mail_transport' => config('mail.mailers.'.config('mail.default').'.transport'), 'automatic_dispatch_start' => config('dispatch.automatic_start'), 'tools' => $versions];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($result['result'] === 'passed' ? 0 : 1);
