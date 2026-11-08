<?php

namespace App\Services;

use App\Models\DeliveryRateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeliveryRateManagementService
{
    public function __construct(private AdministrativeAuditService $audits) {}

    public function create(User $actor, array $data): DeliveryRateVersion
    {
        abort_unless($actor->status === 'approved' && $actor->isLogisticsAdmin(), 403);
        validator($data, ['name' => ['required', 'string', 'max:120'], 'route_class' => ['required', 'in:same_barangay,same_municipality,inter_municipality,inter_province,long_distance'], 'sandbox' => ['required', 'boolean'], 'volumetric_divisor' => ['required', 'integer', 'min:1000', 'max:10000'], 'effective_at' => ['required', 'date'], 'rules' => ['required', 'array'], 'rules.weight_brackets' => ['required', 'array', 'min:1', 'max:30'], 'rules.weight_brackets.*.max_grams' => ['required', 'integer', 'min:1', 'max:1000000'], 'rules.weight_brackets.*.components' => ['required', 'array'], 'rules.weight_brackets.*.components.*' => ['integer', 'min:0', 'max:100000000'], 'rules.remote_municipalities' => ['nullable', 'array', 'max:200'], 'rules.remote_municipalities.*' => ['string', 'max:120']])->validate();
        foreach ($data['rules']['weight_brackets'] as $bracket) {
            abort_unless(array_diff(array_keys($bracket['components']), DeliveryPricingService::COMPONENTS) === [] && array_sum($bracket['components']) > 0, 422, 'Use only the defined delivery cost components.');
        }
        validator($data, [
            'rules.destination_zones' => ['nullable', 'array', 'max:200'],
            'rules.destination_zones.*.province' => ['required', 'string', 'max:120'],
            'rules.destination_zones.*.municipality' => ['nullable', 'string', 'max:120'],
            'rules.destination_zones.*.origin_province' => ['nullable', 'string', 'max:120'],
        ])->validate();
        if ($data['route_class'] === 'long_distance') {
            abort_unless(! empty($data['rules']['destination_zones']), 422, 'Long-distance rates require explicit destination zones.');
        }
        $data['rules']['remote_municipalities'] = array_map(fn ($name) => mb_strtolower(trim($name)), $data['rules']['remote_municipalities'] ?? []);
        if ($data['sandbox']) {
            app(WalletFundingService::class)->assertSandbox();
        }
        $rate = DeliveryRateVersion::create(['name' => $data['name'], 'route_class' => $data['route_class'], 'sandbox' => $data['sandbox'], 'volumetric_divisor' => $data['volumetric_divisor'], 'effective_at' => $data['effective_at'], 'rules' => $data['rules'], 'created_by' => $actor->id]);
        $this->audits->record($actor, $rate, 'delivery_rate_drafted', [], $data, 'New tariff version; existing order prices are unchanged.');

        return $rate;
    }

    public function approve(DeliveryRateVersion $rate, User $actor, string $reason): void
    {
        DB::transaction(function () use ($rate, $actor, $reason): void {
            $locked = DeliveryRateVersion::whereKey($rate->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->status === 'approved' && $actor->isLogisticsAdmin() && $actor->id !== $locked->created_by, 403, 'A different logistics administrator must approve the tariff.');
            abort_unless($locked->status === 'DRAFT' && strlen(trim($reason)) >= 5, 422);
            $locked->update(['status' => 'APPROVED', 'approved_by' => $actor->id, 'approved_at' => now(), 'approval_reason' => $reason]);
            $this->audits->record($actor, $locked, 'delivery_rate_approved', ['status' => 'DRAFT'], ['status' => 'APPROVED'], $reason);
        });
    }

    public function activate(User $actor, string $reason): void
    {
        abort_unless($actor->status === 'approved' && $actor->isLogisticsAdmin() && strlen(trim($reason)) >= 5, 403);
        foreach (['same_barangay', 'same_municipality', 'inter_municipality', 'inter_province'] as $route) {
            abort_unless(DeliveryRateVersion::where('route_class', $route)->where('status', 'APPROVED')->where('effective_at', '<=', now())->exists(), 422, 'Approve effective rates for all four route classes before activation.');
        }
        DB::table('commerce_settings')->updateOrInsert(['key' => 'shipping_model'], ['value' => 1]);
        $this->audits->record($actor, $actor, 'versioned_delivery_tariffs_activated', [], ['shipping_model' => 'versioned'], $reason);
    }
}
