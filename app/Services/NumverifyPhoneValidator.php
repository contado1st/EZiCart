<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class NumverifyPhoneValidator
{
    public function validatePhilippineMobile(string $phoneNumber): ?bool
    {
        $accessKey = config('services.numverify.key');

        if (! is_string($accessKey) || trim($accessKey) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phoneNumber);

        if (! is_string($digits) || ! preg_match('/^09\d{9}$/', $digits)) {
            return false;
        }

        // Numverify expects international numbers without a leading plus sign.
        $internationalNumber = '63'.substr($digits, 1);

        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(6)
                ->get(config('services.numverify.endpoint'), [
                    'access_key' => $accessKey,
                    'number' => $internationalNumber,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $result = $response->json();

        if (! is_array($result) || isset($result['error']) || ! array_key_exists('valid', $result)) {
            return null;
        }

        return $result['valid'] === true
            && strtoupper((string) ($result['country_code'] ?? '')) === 'PH';
    }
}
