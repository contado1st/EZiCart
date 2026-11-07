<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class DeliveryCodeService
{
    /** @return array{code: string, delivery_code_hash: string, delivery_code_encrypted: string, delivery_code_expires_at: Carbon, delivery_code_attempts: int, delivery_code_used_at: null} */
    public function issue(): array
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return [
            'code' => $code,
            'delivery_code_hash' => Hash::make($code),
            'delivery_code_encrypted' => Crypt::encryptString($code),
            'delivery_code_expires_at' => now()->addMinutes(max(1, (int) config('logistics.delivery_code_ttl_minutes', 60))),
            'delivery_code_attempts' => 0,
            'delivery_code_used_at' => null,
        ];
    }

    public function reveal(Order $order): ?string
    {
        if (! $this->isUsable($order) || blank($order->delivery_code_encrypted)) {
            return null;
        }

        return Crypt::decryptString($order->delivery_code_encrypted);
    }

    public function isUsable(Order $order): bool
    {
        return filled($order->delivery_code_hash)
            && $order->delivery_code_used_at === null
            && $order->delivery_code_expires_at?->isFuture() === true
            && $order->delivery_code_attempts < max(1, (int) config('logistics.delivery_code_max_attempts', 5));
    }

    public function matches(Order $order, string $candidate): bool
    {
        return $this->isUsable($order) && Hash::check($candidate, $order->delivery_code_hash);
    }
}
