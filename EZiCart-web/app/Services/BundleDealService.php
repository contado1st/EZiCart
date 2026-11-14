<?php

namespace App\Services;

use App\Models\BundleDeal;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BundleDealService
{
    public function __construct(private AdministrativeAuditService $audits) {}

    public function create(User $seller, array $data): BundleDeal
    {
        return DB::transaction(function () use ($seller, $data) {
            $seller = User::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            abort_unless($seller->role === 'seller' && $seller->status === 'approved', 403);
            $ids = array_map('intval', $data['product_ids']);
            sort($ids);
            $gift = $data['kind'] === 'gift' ? (int) ($data['gift_product_id'] ?? 0) : null;
            $all = array_values(array_unique([...$ids, ...($gift ? [$gift] : [])]));
            $products = Product::whereIn('id', $all)->where('user_id', $seller->id)->where('is_archived', false)->orderBy('id')->lockForUpdate()->get();
            if (count($ids) < 1 || count($ids) > 100 || count($ids) !== count(array_unique($ids)) || $products->count() !== count($all) || ($data['kind'] === 'gift' && (! $gift || in_array($gift, $ids, true)))) {
                throw ValidationException::withMessages(['product_ids' => 'Choose your own products. The gift must be a different product from the qualifying items.']);
            }
            $attributes = ['seller_id' => $seller->id, 'name' => trim($data['name']), 'kind' => $data['kind'], 'threshold' => (int) $data['threshold'],
                'percent' => $data['kind'] === 'percent' ? (int) $data['percent'] : null, 'gift_product_id' => $gift,
                'gift_quantity' => $data['kind'] === 'gift' ? (int) ($data['gift_quantity'] ?? 1) : 1,
                'starts_at' => Carbon::parse($data['starts_at'], 'Asia/Manila')->setTimezone(config('app.timezone'))->startOfSecond(),
                'ends_at' => Carbon::parse($data['ends_at'], 'Asia/Manila')->setTimezone(config('app.timezone'))->startOfSecond()];
            $hash = hash('sha256', json_encode([$attributes, $ids], JSON_THROW_ON_ERROR));
            if ($old = BundleDeal::where('seller_id', $seller->id)->where('operation_key', $data['operation_key'])->first()) {
                abort_unless(hash_equals($old->request_hash, $hash), 409, 'This bundle action was already submitted with different details.');

                return $old;
            }
            if ($attributes['starts_at']->lt(now()) || $attributes['ends_at']->lte($attributes['starts_at'])) {
                throw ValidationException::withMessages(['ends_at' => 'Use a future start and an end after the start.']);
            }
            $overlap = BundleDeal::where('seller_id', $seller->id)->whereNull('ended_at')->where('starts_at', '<', $attributes['ends_at'])->where('ends_at', '>', $attributes['starts_at'])
                ->where(fn ($q) => $q->whereIn('gift_product_id', $all)->orWhereHas('products', fn ($p) => $p->whereIn('products.id', $all)))->exists();
            $flash = FlashSale::whereIn('product_id', $all)->whereNull('ended_at')->where('starts_at', '<', $attributes['ends_at'])->where('ends_at', '>', $attributes['starts_at'])->exists();
            if ($overlap || $flash) {
                throw ValidationException::withMessages(['product_ids' => 'A selected product already participates in an overlapping bundle or flash slot. End that offer first.']);
            }
            $deal = BundleDeal::create([...$attributes, 'operation_key' => $data['operation_key'], 'request_hash' => $hash]);
            $deal->products()->sync($ids);
            $this->audits->record($seller, $deal, 'bundle_created', [], [...$deal->only(['name', 'kind', 'threshold', 'percent', 'gift_product_id', 'gift_quantity']), 'products' => $ids], 'Seller-funded bundle; gift units must be selected in the cart and retain stock/weight requirements.');

            return $deal;
        }, 3);
    }

    public function end(User $seller, BundleDeal $deal): void
    {
        DB::transaction(function () use ($seller, $deal) {
            abort_unless($seller->role === 'seller' && $seller->status === 'approved' && (int) $deal->seller_id === $seller->id, 403);
            $ids = [...$deal->products()->pluck('products.id')->all(), $deal->gift_product_id];
            Product::whereIn('id', array_filter($ids))->orderBy('id')->lockForUpdate()->get();
            $locked = BundleDeal::whereKey($deal->id)->lockForUpdate()->firstOrFail();
            if (! $locked->ended_at) {
                $locked->update(['ended_at' => now()]);
                $this->audits->record($seller, $locked, 'bundle_ended', [], ['ended_at' => $locked->ended_at], 'Ended future bundle pricing. Existing orders retain their snapshot.');
            }
        });
    }
}
