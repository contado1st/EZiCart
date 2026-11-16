<?php

namespace App\Console\Commands;

use App\Jobs\DeliverNotificationChannels;
use App\Models\DeliveryRateVersion;
use App\Models\Product;
use App\Models\User;
use App\Services\DeliveryPricingService;
use App\Services\FinancialLedgerService;
use App\Services\UserDocumentService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class CheckOperationsReadiness extends Command
{
    protected $signature = 'operations:readiness {--json : Output counts and internal IDs without personal data}';

    protected $description = 'Read-only checks for catalog, rider credentials, documents, notification failures and tariff readiness';

    public function handle(UserDocumentService $documents, DeliveryPricingService $pricing, FinancialLedgerService $ledger): int
    {
        $issues = [];
        $unweighed = Product::availableForMarketplace()->where(fn ($q) => $q->whereNull('weight_grams')->orWhere('weight_grams', '<', 1))->pluck('id')->all();
        if ($pricing->enabled() && $unweighed) {
            $issues['product_weights'] = ['product_ids' => $unweighed, 'next_step' => 'Seller inventory: record each product unit weight. Mixed selections cannot bypass this requirement.'];
        }
        $riders = User::where('role', 'courier')->where('status', 'approved');
        $licenseIds = (clone $riders)->where(fn ($q) => $q->whereNull('license_expires_at')->orWhereDate('license_expires_at', '<', today()))->pluck('id')->all();
        if ($licenseIds) {
            $issues['rider_licenses'] = ['user_ids' => $licenseIds, 'next_step' => 'Riders submit current license details through profile approval; logistics admin reviews. Missing legacy dates are warnings; known expired dates block outgoing work.'];
        }
        $missing = [];
        User::where('status', 'approved')->whereIn('role', ['seller', 'courier', 'sorting_center'])->chunkById(100, function ($users) use ($documents, &$missing): void {
            foreach ($users as $user) {
                $types = collect($documents->submittedForReview($user))->where('available', false)->pluck('type')->all();
                if ($types) {
                    $missing[] = ['user_id' => $user->id, 'types' => $types];
                }
            }
        });
        if ($missing) {
            $issues['private_documents'] = ['accounts' => $missing, 'next_step' => 'Review submitted papers on staff profiles; restore originals or request resubmission. Do not substitute fabricated documents.'];
        }
        $failed = [];
        DB::table('failed_jobs')->orderBy('id')->chunkById(100, function ($jobs) use (&$failed): void {
            foreach ($jobs as $job) {
                $payload = json_decode($job->payload, true);
                if (($payload['displayName'] ?? null) !== DeliverNotificationChannels::class) {
                    continue;
                }
                $state = 'invalid_payload';
                $command = $payload['data']['command'] ?? null;
                if (is_string($command)) {
                    try {
                        // Authenticate/decrypt only. Never unserialize or execute a historical job here.
                        Crypt::decryptString($command);
                        $state = 'decryptable_requires_review';
                    } catch (DecryptException) {
                        $state = 'unreadable_or_legacy_payload';
                    }
                }
                $failed[] = ['id' => $job->id, 'payload_state' => $state];
            }
        });
        if ($failed) {
            $issues['failed_notifications'] = ['jobs' => $failed, 'next_step' => 'Privately review job compatibility and encryption history before an explicitly authorized retry. This command sends, deletes and requeues nothing.'];
        }
        $rates = DeliveryRateVersion::where('status', 'APPROVED')->where('effective_at', '<=', now())->orderByDesc('effective_at')->orderByDesc('id')->get()->unique('route_class');
        $limits = $rates->mapWithKeys(fn ($r) => [$r->route_class => max(array_column($r->rules['weight_brackets'] ?? [], 'max_grams') ?: [0])])->all();
        $legacy = DB::table('shipment_financials')->whereNull('rate_version_id')->count();
        $reconciliation = $ledger->reconcile();
        if (! $reconciliation['balanced']) {
            $issues['ledger'] = ['next_step' => 'Investigate ledger reconciliation before financial operations; never rewrite posted entries.'];
        }
        $report = ['ready' => $issues === [], 'read_only' => true, 'issues' => $issues,
            'tariff_maximum_grams' => $limits, 'legacy_shipment_snapshots' => $legacy,
            'historical_policy' => 'Existing rate and allocation snapshots are preserved. New quotes above their effective tariff limits are refused; approved heavy-load rates require a new reviewed version.',
        ];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->info($report['ready'] ? 'Operations readiness checks passed.' : 'Operations readiness needs attention.');
            foreach ($issues as $key => $issue) {
                $this->warn($key.': '.$issue['next_step']);
            }
            $this->line('Use --json for internal IDs and configured tariff limits. No business data was changed.');
        }

        return $report['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
