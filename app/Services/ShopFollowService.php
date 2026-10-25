<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ShopFollowService
{
    public function set(User $buyer, User $seller, bool $following): void
    {
        DB::transaction(function () use ($buyer, $seller, $following): void {
            $users = User::query()->whereKey([$buyer->id, $seller->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($buyer->id);
            $shop = $users->get($seller->id);
            abort_unless($actor?->role === 'buyer' && $actor->status === 'approved', 403);
            abort_unless($shop?->role === 'seller' && $shop->status === 'approved', 404);
            if ($following) {
                $actor->followedShops()->syncWithoutDetaching([$shop->id]);
            } else {
                $actor->followedShops()->detach($shop->id);
            }
        }, 3);
    }
}
