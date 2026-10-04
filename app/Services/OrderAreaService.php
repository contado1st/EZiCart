<?php

namespace App\Services;

use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderAreaService
{
    public function resolve(Order $order): Area
    {
        $province = trim((string) $order->province);
        $municipality = trim((string) $order->municipality);
        abort_if($municipality === '', 422, 'The order has no destination municipality for area routing.');

        $provinceNormalized = $this->normalize($province);
        $municipalityNormalized = $this->normalize($municipality);
        $mapping = AreaMunicipality::query()
            ->where('province_normalized', $provinceNormalized)
            ->where('municipality_normalized', $municipalityNormalized)
            ->first();

        if ($mapping === null) {
            $mapping = DB::transaction(function () use ($province, $municipality, $provinceNormalized, $municipalityNormalized): AreaMunicipality {
                $existing = AreaMunicipality::query()
                    ->where('province_normalized', $provinceNormalized)
                    ->where('municipality_normalized', $municipalityNormalized)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                $baseCode = Str::slug(($province !== '' ? $province.'-' : '').$municipality);
                $code = $baseCode;
                if (Area::query()->where('code', $code)->exists()) {
                    $code .= '-'.substr(sha1($provinceNormalized.'|'.$municipalityNormalized), 0, 8);
                }

                $area = Area::query()->create([
                    'name' => $municipality,
                    'code' => $code,
                    'is_active' => true,
                ]);

                return AreaMunicipality::query()->create([
                    'area_id' => $area->id,
                    'province' => $province,
                    'municipality' => $municipality,
                    'province_normalized' => $provinceNormalized,
                    'municipality_normalized' => $municipalityNormalized,
                ]);
            });
        }

        $area = $mapping->area;
        abort_unless($area->is_active, 422, 'The destination area is inactive. Contact Logistics to update its routing.');

        return $area;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
    }
}
