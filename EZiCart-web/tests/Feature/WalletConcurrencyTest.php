<?php

namespace Tests\Feature;

use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\DemoFundingCodeService;
use App\Services\FinancialLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class WalletConcurrencyTest extends TestCase
{
    #[TestWith(['send'])]
    #[TestWith(['code'])]
    public function test_parallel_wallet_operations_never_overspend_or_redeem_a_code_twice(string $mode): void
    {
        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('This race fixture uses a disposable SQLite database; it does not certify MySQL concurrency.');
        }
        $sqlite = config('database.connections.sqlite');
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ezicart-wallet-race-'.Str::uuid().'.sqlite';
        $requestPath = $path.'.json';
        $processes = [];
        try {
            touch($path);
            config(['database.connections.sqlite.database' => $path, 'database.connections.sqlite.url' => null, 'financial.sandbox_enabled' => true]);
            DB::purge('sqlite');
            Artisan::call('migrate', ['--force' => true]);
            $sender = $this->user('buyer');
            $first = $this->user('seller');
            $second = $this->user('courier');
            $admin = $this->user('ecommerce_admin');
            $ledger = app(FinancialLedgerService::class);
            if ($mode === 'send') {
                $ledger->transfer('race-fixture', 'fixture', $ledger->account('demo:fixture', 'demo_issuance', null, 'available', true), $ledger->wallet($sender), 10000, $sender);
            }
            $secret = $mode === 'code' ? app(DemoFundingCodeService::class)->issue($admin, $sender, 10000, (string) Str::uuid(), 60, 'Parallel funding test')[1] : null;
            file_put_contents($requestPath, json_encode(['database' => $path, 'mode' => $mode, 'sender' => $sender->id,
                'recipients' => [$first->id, $second->id], 'keys' => [(string) Str::uuid(), (string) Str::uuid()], 'code' => $secret], JSON_THROW_ON_ERROR));
            foreach ([0, 1] as $index) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/wallet-race-worker.php'), $requestPath, (string) $index], base_path(),
                    ['APP_ENV' => 'testing', 'APP_KEY' => (string) config('app.key')], timeout: 30);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 12;
            while ((! is_file($requestPath.'.ready0') || ! is_file($requestPath.'.ready1')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($requestPath.'.ready0');
            $this->assertFileExists($requestPath.'.ready1');
            file_put_contents($requestPath.'.start', 'start');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            if ($mode === 'send') {
                sort($results);
                $this->assertSame(['posted', 'validation_refused'], $results);
                $this->assertSame(2000, $ledger->wallet($sender)->balance_cents);
                $this->assertSame(8000, $ledger->wallet($first)->balance_cents + $ledger->wallet($second)->balance_cents);
                $this->assertSame(1, LedgerTransaction::where('type', 'wallet_transfer')->count());
            } else {
                $this->assertSame(['posted', 'posted'], $results);
                $this->assertSame(10000, $ledger->wallet($sender)->balance_cents);
                $this->assertSame(1, LedgerTransaction::where('type', 'demo_code_credit')->count());
            }
            $this->assertTrue($ledger->reconcile()['balanced']);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            DB::disconnect('sqlite');
            DB::purge('sqlite');
            config(['database.connections.sqlite' => $sqlite]);
            RefreshDatabaseState::$migrated = false;
            foreach ([$path, $path.'-wal', $path.'-shm', $requestPath, $requestPath.'.ready0', $requestPath.'.ready1', $requestPath.'.start'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function user(string $role): User
    {
        return User::forceCreate(['first_name' => 'Race', 'last_name' => ucfirst($role), 'role' => $role, 'status' => 'approved', 'email' => Str::uuid().'@example.test', 'password' => 'Password123!', 'contact_no' => '09'.fake()->unique()->numerify('#########')]);
    }
}
