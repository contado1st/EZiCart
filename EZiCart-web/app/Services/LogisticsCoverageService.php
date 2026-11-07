<?php

namespace App\Services;

use App\Models\AreaMunicipality;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LogisticsCoverageService
{
    public function __construct(private OrderAreaService $areas) {}

    public function coverage(Order $order): ?object
    {
        $mapping = AreaMunicipality::where('province_normalized', $this->areas->normalizeAddressValue($order->province))
            ->where('municipality_normalized', $this->areas->normalizeAddressValue($order->municipality))->first();

        return $mapping ? DB::table('barangay_rider_assignments')->where('area_municipality_id', $mapping->id)
            ->where('barangay_normalized', $this->areas->normalizeAddressValue((string) $order->barangay))->first() : null;
    }

    public function assertRider(Order $order, User $rider, ?string $reason): void
    {
        $coverage = $this->coverage($order);
        if (! $coverage) {
            return;
        } // Preserve existing municipality coverage until barangays are configured.
        if ((int) $coverage->primary_rider_id === $rider->id) {
            return;
        }
        abort_unless(filled($reason) && mb_strlen(trim($reason)) >= 5, 422, 'A reason is required to use a backup or override the primary barangay rider.');
    }
}
