<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutPricingService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CheckoutConcurrencyTest extends TestCase
{
    #[TestWith([false, false])]
    #[TestWith([true, false])]
    #[TestWith([false, true])]
    public function test_simultaneous_last_unit_duplicate_token_and_last_voucher_requests(bool $duplicate, bool $limitedVoucher): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ezicart-race-'.Str::uuid().'.sqlite';
        touch($path);
        $processes = [];
        $driver = config('database.default') === 'mysql' ? 'mysql' : 'sqlite';
        try {
            if ($driver === 'mysql') {
                $database = config('database.connections.mysql.database');
                if (getenv('EZICART_ALLOW_DISPOSABLE_MYSQL') !== '1' || ! preg_match('/^ezicart_audit_test_[a-f0-9]{12}$/', $database)) {
                    throw new \LogicException('Concurrency tests require a disposable MySQL schema explicitly enabled by the isolated runner.');
                }
                Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]);
            } else {
                $database = $path;
                config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path, 'database.connections.sqlite.url' => null]);
                DB::purge('sqlite');
                Artisan::call('migrate', ['--force' => true]);
            }
            $seller = $this->user('seller');
            $first = $this->user('buyer');
            $second = $duplicate ? $first : $this->user('buyer');
            $product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Last item', 'category' => 'Test', 'price' => 100, 'stock' => $limitedVoucher ? 2 : 1, 'compliance_status' => 'approved']);
            $voucher = $limitedVoucher ? Voucher::create(['seller_id' => $seller->id, 'code' => 'LAST-VOUCHER', 'type' => 'fixed', 'value' => 5, 'usage_limit' => 1, 'is_active' => true]) : null;
            $hash = app(CheckoutPricingService::class)->quote([['product_id' => $product->id, 'quantity' => 1]], $voucher)['hash'];
            $token = (string) Str::uuid();
            foreach ([$first, $second] as $index => $buyer) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/checkout-race-worker.php'), $driver, $database, $path, (string) $buyer->id, (string) $product->id, $duplicate ? $token : (string) Str::uuid(), $hash, (string) $index, (string) ($voucher?->id ?? 0)], base_path(), timeout: 20);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while ((! is_file($path.'.ready0') || ! is_file($path.'.ready1')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($path.'.ready0');
            $this->assertFileExists($path.'.ready1');
            file_put_contents($path.'.start', 'start');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getOutput().' '.$process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            $this->assertSame(1, Order::count());
            $this->assertEquals($limitedVoucher ? 1 : 0, $product->fresh()->stock);
            $this->assertSame($duplicate ? 2 : 1, count(array_filter($results, fn ($result) => $result === 'placed')));
            if ($voucher) {
                $this->assertEquals(1, $voucher->fresh()->used_count);
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            DB::disconnect($driver);
            DB::purge($driver);
            RefreshDatabaseState::$migrated = false;
            foreach (['', '.start', '.ready0', '.ready1', '-journal', '-wal', '-shm'] as $suffix) {
                if (is_file($path.$suffix)) {
                    unlink($path.$suffix);
                }
            }
        }
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => $role, 'status' => 'approved', 'first_name' => 'Race', 'last_name' => 'User', 'contact_no' => '09123456789']);
    }
}
