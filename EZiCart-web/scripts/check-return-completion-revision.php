<?php

use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderCompletionEligibilityService;
use App\Services\OrderFinancialService;
use App\Services\OrderTransitionService;
use App\Services\SellerReturnService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
});
$original = DB::getDefaultConnection();
$source = DB::connection();
if ($source->getDriverName() !== 'mysql' || ! app()->environment(['local', 'testing'])) {
    throw new RuntimeException('Local/testing MySQL only; fixture writes must use an isolated schema.');
}
$connect = function (string $name) use ($source) {
    if (! preg_match('/\Aezicart_b4_check_\d{14}_[a-f0-9]{8}\z/', $name) || $name === $source->getDatabaseName()) {
        throw new RuntimeException('Invalid isolated schema.');
    }
    config(['database.connections.batch4_copy' => [...$source->getConfig(), 'name' => 'batch4_copy', 'database' => $name, 'url' => null], 'mail.default' => 'array', 'queue.default' => 'sync']);
    DB::setDefaultConnection('batch4_copy');
    Schema::clearResolvedInstance('db.schema');
    if (User::query()->getConnection()->getName() !== 'batch4_copy' || (new User)->getConnection()->getDatabaseName() !== $name) {
        throw new RuntimeException('Model isolation failed before fixture creation.');
    }
};
if (($argv[1] ?? null) === 'worker') {
    $connect($argv[2]);
    while (microtime(true) < (float) $argv[4]) {
        usleep(10000);
    }
    try {
        $case = Dispute::find((int) $argv[5]);
        $value = match ($argv[3]) {
            'approve' => app(SellerReturnService::class)->decide($case, $case->seller, 'approve', 1, null)->stage,
            'reject' => app(SellerReturnService::class)->decide($case, $case->seller, 'reject', 1, 'Isolated seller rejection reason.')->stage,
            'expire' => app(SellerReturnService::class)->expire($case),
            'escalate' => app(SellerReturnService::class)->escalate($case, $case->buyer, 2)->stage,
            'complete' => app(OrderTransitionService::class)->completeAutomatically(Order::findOrFail((int) $argv[5])),
            'request' => DB::transaction(function () use ($argv) {
                $order = Order::whereKey((int) $argv[5])->lockForUpdate()->firstOrFail();
                $case = Dispute::create(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id, 'reason' => 'Damaged Item', 'description' => 'A concurrent buyer return request.', 'status' => 'PENDING']);
                app(SellerReturnService::class)->start($case);

                return true;
            }, 3),
            default => throw new RuntimeException('Unknown worker mode.'),
        };
        echo json_encode(['result' => 'processed', 'value' => $value], JSON_THROW_ON_ERROR);
    } catch (HttpExceptionInterface $error) {
        echo json_encode(['result' => 'rejected', 'status' => $error->getStatusCode()], JSON_THROW_ON_ERROR);
    }
    exit;
}
$name = 'ezicart_b4_check_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
$quote = fn ($value) => '`'.str_replace('`', '``', $value).'`';
$business = ['users', 'products', 'orders', 'disputes', 'notifications', 'financial_accounts', 'ledger_transactions', 'ledger_entries', 'refund_executions', 'seller_payouts'];
$counts = fn () => collect($business)->mapWithKeys(fn ($table) => [$table => $source->table($table)->count()])->all();
$before = $counts();
$created = false;
$workers = [];
$proof = null;
$result = [];
try {
    $source->statement('CREATE DATABASE '.$quote($name));
    $created = true;
    $tables = $source->table('information_schema.tables')->where('table_schema', $source->getDatabaseName())->where('table_type', 'BASE TABLE')->selectRaw('TABLE_NAME AS table_name')->get()->pluck('table_name')->all();
    foreach (array_diff($tables, ['seller_return_responses']) as $table) {
        if ($source->table($table)->count() > 10000) {
            throw new RuntimeException('Probe copy limit exceeded; use a prepared disposable database.');
        }
        $source->statement('CREATE TABLE '.$quote($name).'.'.$quote($table).' LIKE '.$quote($source->getDatabaseName()).'.'.$quote($table));
        $columns = $source->table('information_schema.columns')->where('table_schema', $source->getDatabaseName())->where('table_name', $table)
            ->whereRaw("COALESCE(GENERATION_EXPRESSION, '') = ''")->orderBy('ordinal_position')->selectRaw('COLUMN_NAME AS column_name')->get()->pluck('column_name')->all();
        $list = implode(', ', array_map($quote, $columns));
        $source->statement('INSERT INTO '.$quote($name).'.'.$quote($table).' ('.$list.') SELECT '.$list.' FROM '.$quote($source->getDatabaseName()).'.'.$quote($table));
    }
    $connect($name);
    $migration = require dirname(__DIR__).'/database/migrations/2026_11_15_000001_add_seller_return_responses_and_completion_eligibility.php';
    if (Schema::hasColumn('orders', 'auto_completion_due_at')) {
        $migration->down();
    }
    $copyBefore = collect($business)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $migration->up();
    $migration->down();
    $migration->up();
    $result['mysql_up_down_reapply'] = Schema::hasTable('seller_return_responses') && $copyBefore === collect($business)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $user = fn ($role) => User::forceCreate(['email' => Str::uuid().'@example.invalid', 'password' => 'IsolatedFixture123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Isolated', 'last_name' => 'ReturnFixture', 'contact_no' => '09123456789']);
    $buyer = $user('buyer');
    $seller = $user('seller');
    $rider = $user('courier');
    $order = fn () => Order::forceCreate(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'delivery_courier_id' => $rider->id, 'order_number' => 'B4-'.Str::random(12), 'recipient_name' => 'Isolated fixture', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Isolated fixture', 'status' => 'DELIVERED', 'payment_method' => 'COD', 'subtotal' => 100, 'shipping_fee' => 50, 'total_amount' => 150, 'commission_fee' => 10]);
    $request = function () use ($order, $buyer, $seller) {
        $parcel = $order();
        $case = Dispute::create(['order_id' => $parcel->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'reason' => 'Damaged Item', 'description' => 'Isolated return fixture.', 'status' => 'PENDING']);
        DB::transaction(function () use ($parcel, $case) {
            Order::whereKey($parcel->id)->lockForUpdate()->firstOrFail();
            app(SellerReturnService::class)->start($case);
        });

        return $case;
    };
    $race = function (array $modes, int $id) use ($name, &$workers) {
        $workers = [];
        $start = (string) (microtime(true) + 2);
        foreach ($modes as $mode) {
            $worker = new Process([PHP_BINARY, __FILE__, 'worker', $name, $mode, $start, (string) $id], dirname(__DIR__));
            $worker->setTimeout(40)->start();
            $workers[] = $worker;
        }
        $outcomes = [];
        foreach ($workers as $worker) {
            $worker->wait();
            if (! $worker->isSuccessful()) {
                throw new RuntimeException('Isolated worker failed: '.$worker->getErrorOutput());
            }
            $outcomes[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $outcomes;
    };
    $case = $request();
    $result['seller_workers'] = $race(['approve', 'reject'], $case->id);
    $result['one_seller_decision'] = count(array_filter($result['seller_workers'], fn ($row) => $row['result'] === 'processed')) === 1 && $case->fresh()->sellerResponse->version === 2;
    $deadline = $request();
    $deadline->sellerResponse->update(['response_due_at' => now()->subSecond()]);
    $result['deadline_workers'] = $race(['approve', 'expire'], $deadline->id);
    $result['deadline_approval_once'] = $deadline->fresh()->sellerResponse->stage === 'deadline_approved'
        && DB::table('administrative_audits')->where('entity_id', $deadline->sellerResponse->id)->where('action', 'return_deadline_approved')->count() === 1;
    $rejected = $request();
    app(SellerReturnService::class)->decide($rejected, $seller, 'reject', 1, 'Isolated rejection before timed escalation.');
    $rejected->sellerResponse()->update(['escalation_due_at' => now()->subSecond()]);
    $result['escalation_workers'] = $race(['escalate', 'expire'], $rejected->id);
    $result['expired_escalation_cannot_win'] = $rejected->fresh()->status === 'REJECTED' && $rejected->fresh()->sellerResponse->escalated_at === null;
    $parcel = $order();
    $delivery = now()->startOfSecond()->subHours(169);
    $parcel->forceFill(['delivered_at' => $delivery, 'delivery_code_used_at' => $delivery, 'cod_collected_amount' => 150, 'completion_enrolled_at' => $delivery, 'auto_completion_due_at' => $delivery->copy()->addHours(168)])->save();
    $proof = 'return-completion-probes/'.$name.'.jpg';
    Storage::disk('private')->put($proof, 'Isolated synthetic evidence for file-presence checks.');
    $parcel->deliveryAttempts()->create(['rider_id' => $rider->id, 'attempt_no' => 1, 'outcome' => 'delivered', 'proof_path' => $proof, 'attempted_at' => $delivery]);
    app(OrderFinancialService::class)->initialize($parcel)->update(['collected_cents' => 15000, 'collected_at' => $delivery, 'remittance_status' => 'COLLECTED']);
    $moneyBefore = DB::table('ledger_transactions')->count();
    $result['completion_workers'] = $race(['complete', 'complete'], $parcel->id);
    $result['completion_once'] = $parcel->fresh()->status === 'COMPLETED' && count(array_filter($result['completion_workers'], fn ($row) => $row['value'] === true)) === 1
        && $parcel->trackingEvents()->where('event_type', 'order_auto_completed')->whereNull('actor_id')->count() === 1;
    $result['persisted_completion_notifications_once'] = DB::table('notifications')->where('data->event_type', 'order_auto_completed')->where('data->order_id', $parcel->id)->count() === 2;
    $result['completion_does_not_release_money'] = DB::table('ledger_transactions')->count() === $moneyBefore && ! $parcel->payout()->exists() && ! $parcel->refundExecutions()->exists();
    $contested = $order();
    $contested->forceFill(['delivered_at' => $delivery, 'delivery_code_used_at' => $delivery, 'cod_collected_amount' => 150, 'completion_enrolled_at' => $delivery, 'auto_completion_due_at' => $delivery->copy()->addHours(168)])->save();
    $contested->deliveryAttempts()->create(['rider_id' => $rider->id, 'attempt_no' => 1, 'outcome' => 'delivered', 'proof_path' => $proof, 'attempted_at' => $delivery]);
    app(OrderFinancialService::class)->initialize($contested)->update(['collected_cents' => 15000, 'collected_at' => $delivery, 'remittance_status' => 'COLLECTED']);
    $result['request_completion_workers'] = $race(['request', 'complete'], $contested->id);
    $result['concurrent_return_preserves_hold'] = $contested->dispute()->where('status', 'PENDING')->exists()
        && app(OrderCompletionEligibilityService::class)->holdReason($contested->fresh()) === 'An active return, dispute or refund prevents completion.'
        && ! app(OrderTransitionService::class)->completeAutomatically($contested->fresh())
        && DB::table('ledger_transactions')->count() === $moneyBefore;
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
    if ($proof) {
        Storage::disk('private')->delete($proof);
    }
    DB::setDefaultConnection($original);
    Schema::clearResolvedInstance('db.schema');
    DB::purge('batch4_copy');
    if ($created) {
        if (! preg_match('/\Aezicart_b4_check_\d{14}_[a-f0-9]{8}\z/', $name) || $name === $source->getDatabaseName()) {
            throw new RuntimeException('Unsafe cleanup target.');
        }
        $source->statement('DROP DATABASE '.$quote($name));
    }
}
$result['configured_business_counts_unchanged'] = $before === $counts();
$result['scope'] = 'Isolated local MySQL seller/version, deadline/escalation and automatic-completion service races with database notifications. Mail uses array transport; no production acceptance or payout execution claimed.';
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
foreach (['mysql_up_down_reapply', 'one_seller_decision', 'deadline_approval_once', 'expired_escalation_cannot_win', 'completion_once', 'persisted_completion_notifications_once', 'completion_does_not_release_money', 'concurrent_return_preserves_hold', 'configured_business_counts_unchanged'] as $check) {
    if (! ($result[$check] ?? false)) {
        exit(1);
    }
}
