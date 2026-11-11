<?php

namespace App\Services;

use App\Models\DeliveryRateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryPricingService
{
    public const COMPONENTS = ['pickup', 'origin_hub', 'linehaul', 'destination_hub', 'last_mile', 'cod', 'remote', 'handling', 'insurance', 'overhead', 'margin', 'return_to_sender'];

    public function enabled(): bool
    {
        return (int) DB::table('commerce_settings')->where('key', 'shipping_model')->value('value') === 1;
    }

    public function route(User $seller, array $address): string
    {
        $normalize = fn ($value) => mb_strtolower(trim((string) $value));
        // A long-distance zone must be explicitly configured on an effective,
        // independently approved tariff. Province boundaries alone imply no distance.
        $longDistance = DeliveryRateVersion::where('route_class', 'long_distance')
            ->where('status', 'APPROVED')->where('effective_at', '<=', now())
            ->latest('effective_at')->orderByDesc('id')->first();
        foreach ($longDistance?->rules['destination_zones'] ?? [] as $zone) {
            if ($normalize($zone['province']) === $normalize($address['province'] ?? '')
                && (! filled($zone['municipality'] ?? null) || $normalize($zone['municipality']) === $normalize($address['municipality'] ?? ''))
                && (! filled($zone['origin_province'] ?? null) || $normalize($zone['origin_province']) === $normalize($seller->province))) {
                return 'long_distance';
            }
        }
        if ($normalize($seller->province) !== $normalize($address['province'] ?? '')) {
            return 'inter_province';
        }
        if ($normalize($seller->municipality) !== $normalize($address['municipality'] ?? '')) {
            return 'inter_municipality';
        }
        if ($normalize($seller->barangay) !== $normalize($address['barangay'] ?? '')) {
            return 'same_municipality';
        }

        return 'same_barangay';
    }

    public function quote(User $seller, array $lines, array $address = [], string $payment = 'COD'): array
    {
        $weight = 0;
        $length = $width = $height = 0;
        foreach ($lines as $line) {
            $product = $line['product'];
            $weight += (int) $product->weight_grams * $line['quantity'];
            $length = max($length, (int) $product->length_mm);
            $width = max($width, (int) $product->width_mm);
            $height += (int) $product->height_mm * $line['quantity'];
        }
        if (! $this->enabled()) {
            $legacy = (int) (DB::table('commerce_settings')->where('key', 'shipping_per_seller_cents')->value('value') ?? config('commerce.shipping_per_seller_cents'));

            return ['model' => 'legacy', 'rate_version_id' => null, 'total_cents' => $legacy, 'actual_grams' => $weight, 'components' => [], 'allocation_status' => 'UNCONFIGURED'];
        }
        $estimatedAddress = ! filled($address['province'] ?? null) || ! filled($address['municipality'] ?? null);
        if ($estimatedAddress) {
            $address = $seller->only(['province', 'municipality', 'barangay']);
        }
        if (! filled($address['province'] ?? null) || ! filled($address['municipality'] ?? null) || $weight < 1) {
            throw ValidationException::withMessages(['shipping' => 'A complete destination and product weights are required to quote the approved delivery tariff.']);
        }
        $route = $this->route($seller, $address);
        $rate = DeliveryRateVersion::where('route_class', $route)->where('status', 'APPROVED')->where('effective_at', '<=', now())->latest('effective_at')->orderByDesc('id')->first();
        if (! $rate) {
            throw ValidationException::withMessages(['shipping' => 'No approved delivery rate is effective for this route.']);
        }
        if ($rate->sandbox) {
            app(WalletFundingService::class)->assertSandbox();
        }

        return [...$this->calculate($rate, $weight, [$length, $width, $height], $payment, $address), 'dimensions_estimated' => true, 'destination_estimated' => $estimatedAddress];
    }

    public function calculate(DeliveryRateVersion $rate, int $weight, array $dimensions, string $payment, array $address = []): array
    {
        abort_unless($weight > 0 && $weight <= 1000000 && count($dimensions) === 3 && $rate->volumetric_divisor > 0, 422, 'Unsupported shipping measurements.');
        foreach ($dimensions as $dimension) {
            abort_unless(is_int($dimension) && $dimension >= 0 && $dimension <= 5000, 422, 'Unsupported parcel dimensions.');
        }
        // millimetres cubed / divisor yields grams. Use integer ceiling throughout.
        $volume = $dimensions[0] * $dimensions[1] * $dimensions[2];
        $volumetric = intdiv($volume + $rate->volumetric_divisor - 1, $rate->volumetric_divisor);
        $chargeable = max($weight, $volumetric);
        $brackets = $rate->rules['weight_brackets'] ?? [];
        usort($brackets, fn ($a, $b) => $a['max_grams'] <=> $b['max_grams']);
        $bracket = collect($brackets)->first(fn ($bracket) => $chargeable <= $bracket['max_grams']);
        if (! $bracket) {
            throw ValidationException::withMessages(['shipping' => 'The parcel exceeds this approved tariff’s weight brackets.']);
        }
        $components = array_fill_keys(self::COMPONENTS, 0);
        foreach (self::COMPONENTS as $name) {
            $components[$name] = (int) ($bracket['components'][$name] ?? 0);
        }
        if ($payment !== 'COD') {
            $components['cod'] = 0;
        }
        $remote = in_array(mb_strtolower(trim($address['municipality'] ?? '')), $rate->rules['remote_municipalities'] ?? [], true);
        if (! $remote) {
            $components['remote'] = 0;
        }
        // RTS is a separate contingent cost, not charged for every successful delivery.
        $contingentRts = $components['return_to_sender'];
        $components['return_to_sender'] = 0;
        $total = array_sum($components);
        abort_unless($total > 0 && $total <= config('financial.maximum_transaction_cents'), 422, 'Invalid approved shipping total.');

        return ['model' => 'versioned', 'rate_version_id' => $rate->id, 'rate_name' => $rate->name, 'sandbox' => $rate->sandbox, 'route_class' => $rate->route_class, 'actual_grams' => $weight, 'dimensions_mm' => $dimensions, 'dimensions_estimated' => in_array(0, $dimensions, true), 'volumetric_divisor' => $rate->volumetric_divisor, 'volumetric_grams' => $volumetric, 'chargeable_grams' => $chargeable, 'components' => $components, 'total_cents' => $total, 'contingent_rts_cents' => $contingentRts, 'remote' => $remote];
    }
}
