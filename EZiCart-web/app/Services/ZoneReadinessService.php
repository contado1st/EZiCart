<?php

namespace App\Services;

use App\Models\Area;
use App\Models\DeliveryRateVersion;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ZoneReadinessService
{
    public function areas(User $actor): Builder
    {
        abort_unless($actor->status === 'approved' && in_array($actor->role, ['sorting_center', 'logistics_admin'], true), 403);

        return Area::query()->when($actor->role === 'sorting_center', fn ($q) => $q->where(fn ($scope) => $scope->where('sorting_center_id', $actor->id)->orWhereNull('sorting_center_id')));
    }

    public function activation(): array
    {
        $classes = ['same_barangay', 'same_municipality', 'inter_municipality', 'inter_province'];
        $rates = DeliveryRateVersion::whereIn('route_class', $classes)->where('status', 'APPROVED')->where('effective_at', '<=', now())->latest('effective_at')->orderByDesc('id')->get()->unique('route_class')->keyBy('route_class');

        return ['enabled' => app(DeliveryPricingService::class)->enabled(), 'ready' => $rates->count() === 4,
            'routes' => collect($classes)->mapWithKeys(fn ($class) => [$class => $rates->get($class)])->all()];
    }

    public function preview(User $actor, array $input): array
    {
        $data = validator($input, ['area_id' => ['required', 'integer', 'min:1'],
            'origin_province' => ['required', 'string', 'max:120'], 'origin_municipality' => ['required', 'string', 'max:120'], 'origin_barangay' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'], 'municipality' => ['required', 'string', 'max:120'], 'barangay' => ['required', 'string', 'max:120'],
            'weight_kg' => ['required', 'numeric', 'min:0.001', 'max:1000', 'decimal:0,3'], 'length_mm' => ['required', 'integer', 'min:1', 'max:5000'],
            'width_mm' => ['required', 'integer', 'min:1', 'max:5000'], 'height_mm' => ['required', 'integer', 'min:1', 'max:5000'], 'payment_method' => ['required', 'in:COD,WALLET']])->validate();
        $area = $this->areas($actor)->whereKey($data['area_id'])->with('sortingCenter')->firstOrFail();
        $mapping = app(OrderAreaService::class)->findForAddress($data['province'], $data['municipality']);
        $origin = app(OrderAreaService::class)->findForAddress($data['origin_province'], $data['origin_municipality']);
        $issues = [];
        if (! $mapping || $mapping->id !== $area->id) {
            $issues[] = 'Map this destination municipality to the selected area before claiming routing readiness.';
        }
        if (! $area->is_active) {
            $issues[] = 'The selected routing area is inactive.';
        }
        if (! $area->sortingCenter || $area->sortingCenter->status !== 'approved' || $area->sortingCenter->role !== 'sorting_center') {
            $issues[] = 'Assign this area to an approved sorting hub.';
        }
        if (! $origin || ! $origin->is_active || ! $origin->sortingCenter || $origin->sortingCenter->status !== 'approved' || $origin->sortingCenter->role !== 'sorting_center') {
            $issues[] = 'Configure an active seller-origin mapping owned by an approved pickup hub.';
        }
        $seller = new User;
        $seller->forceFill(['province' => $data['origin_province'], 'municipality' => $data['origin_municipality'], 'barangay' => $data['origin_barangay']]);
        $product = new Product;
        $weight = ParcelLoadService::grams($data['weight_kg']);
        $product->forceFill(['weight_grams' => $weight, 'length_mm' => $data['length_mm'], 'width_mm' => $data['width_mm'], 'height_mm' => $data['height_mm']]);
        $pricing = app(DeliveryPricingService::class);
        $route = $pricing->route($seller, $data);
        $quote = null;
        try {
            $quote = $pricing->quote($seller, [['product' => $product, 'quantity' => 1]], $data, $data['payment_method']);
            if ($quote['model'] === 'legacy') {
                $issues[] = 'Legacy flat shipping is active. Area mapping does not activate approved compensation tariffs.';
            }
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $issues[] = $message;
                }
            }
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }
            $issues[] = $exception->getMessage();
        }
        $vehicles = User::availableForNewWork()->where('hub_transfer_only', false)->whereNotNull('vehicle_capacity_verified_at')->where('vehicle_capacity_grams', '>=', $weight)
            ->where(fn ($q) => $q->whereNull('license_expires_at')->orWhereDate('license_expires_at', '>=', today()))
            ->whereHas('serviceAreas', fn ($q) => $q->where('areas.id', $area->id));

        return ['area' => $area, 'route' => $route, 'quote' => $quote, 'issues' => array_values(array_unique($issues)),
            'routing_and_tariff_configured' => $issues === [], 'capacity_candidates' => (clone $vehicles)->count(), 'maximum_verified_capacity' => (clone $vehicles)->max('vehicle_capacity_grams'),
            'availability_enforced' => app(RiderWorkAvailabilityService::class)->enforced(), 'weight_grams' => $weight];
    }
}
