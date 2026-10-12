<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PsgcAddressService
{
    private const BASE_URL = 'https://psgc.cloud/api/v2';

    /** @return array<string, array<int, mixed>> */
    public function validationRules(): array
    {
        $manualLocationRules = ['required_if:address_mode,manual', 'nullable', 'string', 'max:120'];

        return [
            'address_mode' => ['required', Rule::in(['psgc', 'manual'])],
            'region_code' => ['required_if:address_mode,psgc', 'nullable', 'regex:/^\d{10}$/'],
            'province_code' => ['required_if:address_mode,psgc', 'nullable', 'regex:/^(?:\d{10}|NCR)$/'],
            'municipality_code' => ['required_if:address_mode,psgc', 'nullable', 'regex:/^\d{10}$/'],
            'barangay_code' => ['required_if:address_mode,psgc', 'nullable', 'regex:/^\d{10}$/'],
            'manual_region' => $manualLocationRules,
            'manual_province' => $manualLocationRules,
            'manual_municipality' => $manualLocationRules,
            'manual_barangay' => $manualLocationRules,
            'house_number' => ['nullable', 'string', 'max:100'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'digits:4'],
        ];
    }

    /** @return array<int, array{code: string, name: string}> */
    public function options(string $level, ?string $parentCode = null, ?string $parentLevel = null): array
    {
        $path = match ($level) {
            'regions' => '/regions',
            'provinces' => '/regions/'.rawurlencode((string) $parentCode).'/provinces',
            'localities' => '/'.($parentLevel === 'province' ? 'provinces' : 'regions').'/'.rawurlencode((string) $parentCode).'/cities-municipalities',
            'barangays' => '/cities-municipalities/'.rawurlencode((string) $parentCode).'/barangays',
            default => throw new \InvalidArgumentException('Unknown PSGC level.'),
        };

        $key = 'psgc.v2.'.sha1($path);

        return Cache::remember($key, now()->addHours(24), function () use ($path): array {
            try {
                $payload = Http::connectTimeout(3)
                    ->timeout(8)
                    ->retry([100, 300], 0)
                    ->acceptJson()
                    ->get(self::BASE_URL.$path)
                    ->throw()
                    ->json();
            } catch (ConnectionException|RequestException $exception) {
                report($exception);
                throw ValidationException::withMessages([
                    'region_code' => 'Location data is temporarily unavailable. Please try again shortly.',
                ]);
            }

            $records = $payload['data'] ?? $payload;
            if (! is_array($records)) {
                throw new \UnexpectedValueException('Unexpected PSGC response.');
            }

            return collect($records)
                ->filter(fn ($item) => is_array($item) && isset($item['code'], $item['name']))
                ->map(fn (array $item) => ['code' => (string) $item['code'], 'name' => (string) $item['name']])
                ->values()
                ->all();
        });
    }

    /** @return array{region: string, province: string, municipality: string, barangay: string} */
    public function resolve(array $input): array
    {
        if (($input['address_mode'] ?? 'psgc') === 'manual') {
            return [
                'region' => trim($input['manual_region']),
                'province' => trim($input['manual_province']),
                'municipality' => trim($input['manual_municipality']),
                'barangay' => trim($input['manual_barangay']),
            ];
        }

        $region = $this->findOption('regions', null, null, (string) $input['region_code']);
        $provinces = $this->options('provinces', $region['code'], 'region');

        // NCR has no province level in PSGC; the persisted province label is retained for existing order routing.
        if ($region['code'] === '1300000000' && $input['province_code'] === 'NCR') {
            $provinceName = 'Metro Manila';
            $provinceLevel = 'region';
            $provinceCode = $region['code'];
        } else {
            $province = collect($provinces)->firstWhere('code', (string) $input['province_code']);
            if (! $province) {
                throw ValidationException::withMessages(['province_code' => 'Choose a province in the selected region.']);
            }
            $provinceName = $province['name'];
            $provinceLevel = 'province';
            $provinceCode = $province['code'];
        }

        $locality = $this->findOption('localities', $provinceCode, $provinceLevel, (string) $input['municipality_code']);
        $barangay = $this->findOption('barangays', $locality['code'], 'locality', (string) $input['barangay_code']);

        return [
            'region' => $region['name'],
            'province' => $provinceName,
            'municipality' => $locality['name'],
            'barangay' => $barangay['name'],
        ];
    }

    private function findOption(string $level, ?string $parent, ?string $parentLevel, string $code): array
    {
        $option = collect($this->options($level, $parent, $parentLevel))->firstWhere('code', $code);
        if (! $option) {
            $field = match ($level) {
                'regions' => 'region_code',
                'provinces' => 'province_code',
                'localities' => 'municipality_code',
                default => 'barangay_code',
            };
            throw ValidationException::withMessages([$field => 'Choose a valid location from the list.']);
        }

        return $option;
    }
}
