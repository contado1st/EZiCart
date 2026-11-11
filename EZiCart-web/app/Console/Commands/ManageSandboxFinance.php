<?php

namespace App\Console\Commands;

use App\Models\DeliveryRateVersion;
use App\Models\User;
use App\Models\WalletFundingIntent;
use App\Services\AdministrativeAuditService;
use App\Services\DeliveryRateManagementService;
use App\Services\WalletFundingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManageSandboxFinance extends Command
{
    protected $signature = 'finance:sandbox {action : enable, disable, tariffs, or event} {--actor= : Approved administrator user ID} {--intent= : Funding intent UUID for event} {--result=paid : paid, failed or reversed}';

    protected $description = 'Explicit local/test-only sandbox controls; never performs real-money operations';

    public function handle(WalletFundingService $funding, DeliveryRateManagementService $rates, AdministrativeAuditService $audits): int
    {
        abort_unless(app()->environment(['local', 'testing']) && config('financial.mode') === 'sandbox', 403, 'Sandbox commands are disabled in production.');
        $actor = User::findOrFail($this->option('actor'));
        abort_unless($actor->status === 'approved', 403);
        $action = $this->argument('action');
        if (in_array($action, ['enable', 'disable'], true)) {
            abort_unless($actor->isCommerceAdmin(), 403);
            DB::table('commerce_settings')->updateOrInsert(['key' => 'financial_sandbox_enabled'], ['value' => $action === 'enable' ? '1' : '0']);
            $audits->record($actor, $actor, 'financial_sandbox_'.$action, [], ['enabled' => $action === 'enable'], 'Explicit CLI sandbox configuration. No money or opening balances were created.');
            $this->info('Mock financial operations '.$action.'d. No real-money adapter is enabled.');
        } elseif ($action === 'event') {
            $intent = WalletFundingIntent::where('reference', $this->option('intent'))->firstOrFail();
            $funding->simulate($intent, $actor, $this->option('result'));
            $this->info('Signed sandbox event processed.');
        } elseif ($action === 'tariffs') {
            $funding->assertSandbox();
            abort_unless($actor->isLogisticsAdmin(), 403);
            foreach (['same_barangay', 'same_municipality', 'inter_municipality', 'inter_province'] as $class) {
                $name = 'SANDBOX EXAMPLE — '.$class;
                if (DeliveryRateVersion::where('name', $name)->exists()) {
                    continue;
                }
                $interHub = in_array($class, ['inter_municipality', 'inter_province'], true);
                // Fictional training costs only. These are not carrier tariffs or recommended commercial wages.
                $components = ['pickup' => 2000, 'origin_hub' => 800, 'destination_hub' => $interHub ? 800 : 0, 'linehaul' => $interHub ? 2500 : 0, 'last_mile' => 2500, 'cod' => 400, 'overhead' => 800, 'margin' => 500, 'remote' => 0, 'handling' => 0, 'insurance' => 0, 'return_to_sender' => 2000];
                $rules = ['weight_brackets' => [['max_grams' => 1000, 'components' => $components], ['max_grams' => 5000, 'components' => [...$components, 'last_mile' => 3500]], ['max_grams' => 20000, 'components' => [...$components, 'last_mile' => 6000]]], 'remote_municipalities' => []];
                $rate = $rates->create($actor, ['name' => $name, 'route_class' => $class, 'sandbox' => true, 'volumetric_divisor' => 6000, 'effective_at' => now(), 'rules' => $rules]);
                $this->info('Draft #'.$rate->id.' created; independent approval and explicit activation are still required.');
            }
        } else {
            $this->error('Choose enable, disable, tariffs or event.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
