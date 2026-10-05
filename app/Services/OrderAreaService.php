<?php

namespace App\Services;

use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\Order;

class OrderAreaService
{
    public function resolve(Order $order): Area
    {
        $province = trim((string) $order->province);
        $municipality = trim((string) $order->municipality);
        abort_if($municipality === '', 422, 'The order has no destination municipality for area routing.');

        $provinceNormalized = $this->normalizeAddressValue($province);
        $municipalityNormalized = $this->normalizeAddressValue($municipality);
        $mapping = AreaMunicipality::query()
            ->where('province_normalized', $provinceNormalized)
            ->where('municipality_normalized', $municipalityNormalized)
            ->first();

        abort_unless($mapping !== null, 422, 'No routing area is configured for this destination. Configure its province and municipality in Logistics area settings.');

        $area = $mapping->area;
        abort_unless($area->is_active, 422, 'The destination area is inactive. Contact Logistics to update its routing.');

        return $area;
    }

    public function normalizeAddressValue(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
    }
}
