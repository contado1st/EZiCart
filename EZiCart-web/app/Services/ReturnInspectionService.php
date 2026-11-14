<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\ReturnInspectionItem;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnInspectionService
{
    public function __construct(private InventoryRestorationService $inventory, private AdministrativeAuditService $audits, private TransactionAwareNotificationSender $notifications) {}

    public function inspect(Order $order, User $actor, array $data): void
    {
        $reason = trim($data['reason'] ?? '');
        validator(['reason' => $reason], ['reason' => ['required', 'string', 'min:5', 'max:500']])->validate();
        validator($data, ['restock' => ['sometimes', 'boolean'], 'quantities' => ['sometimes', 'array']])->validate();
        if (array_key_exists('quantities', $data) === array_key_exists('restock', $data)) {
            throw ValidationException::withMessages(['quantities' => 'Submit per-item sellable quantities or the existing all-or-none result, not both.']);
        }
        DB::transaction(function () use ($order, $actor, $data, $reason): void {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->role === 'seller' && $actor->status === 'approved' && (int) $locked->seller_id === $actor->id, 403);
            abort_unless($locked->status === 'RETURNED_TO_SELLER' && $locked->restock_inspected_at === null && $locked->inventory_restored_at === null, 422, 'This return is not awaiting inspection or its stock was already restored.');
            $items = $locked->items()->orderBy('id')->lockForUpdate()->get();
            abort_if($items->isEmpty(), 422, 'Historical item quantities are missing. Ask administration to review the return before inspecting.');
            $quantities = $data['quantities'] ?? $items->mapWithKeys(fn ($item) => [$item->id => $data['restock'] ? $item->quantity : 0])->all();
            validator(['quantities' => $quantities], ['quantities' => ['required', 'array'], 'quantities.*' => ['required', 'integer', 'min:0']])->validate();
            $keys = array_map('strval', array_keys($quantities));
            $expected = array_map('strval', $items->modelKeys());
            sort($keys);
            sort($expected);
            if ($keys !== $expected) {
                throw ValidationException::withMessages(['quantities' => 'Provide one sellable quantity for every item in this order; other order items cannot be inspected here.']);
            }
            $products = Product::whereIn('id', $items->pluck('product_id')->filter())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $variations = ProductVariation::whereIn('id', $items->pluck('variation_id')->filter())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $rows = [];
            foreach ($items as $item) {
                $quantity = (int) $quantities[$item->id];
                if ($quantity > $item->quantity || $item->quantity < 1) {
                    throw ValidationException::withMessages(['quantities.'.$item->id => 'Sellable quantity must be between zero and the purchased quantity.']);
                }
                if ($quantity > 0 && (! $products->has($item->product_id) || ($item->variation_id !== null && (int) $variations->get($item->variation_id)?->product_id !== (int) $item->product_id))) {
                    throw ValidationException::withMessages(['quantities.'.$item->id => 'The original catalog item or option is unavailable. Exclude it and ask administration to review; no replacement item is inferred.']);
                }
                $rows[] = ['order_id' => $locked->id, 'order_item_id' => $item->id, 'expected_quantity' => $item->quantity,
                    'restock_quantity' => $quantity, 'excluded_quantity' => $item->quantity - $quantity, 'inspected_by' => $actor->id, 'created_at' => now()];
            }
            foreach ($rows as $row) {
                ReturnInspectionItem::create($row);
            }
            $restocked = array_sum(array_column($rows, 'restock_quantity'));
            $locked->forceFill(['restock_inspected_at' => now(), 'restock_inspected_by' => $actor->id, 'restock_approved' => $restocked > 0])->save();
            if ($restocked > 0) {
                $this->inventory->restore($locked);
            }
            $this->audits->record($actor, $locked, 'return_inspected', [], ['restocked' => $restocked > 0, 'restocked_quantity' => $restocked, 'excluded_quantity' => array_sum(array_column($rows, 'excluded_quantity')), 'items' => $rows], $reason);
            $this->notifications->send(collect([$locked->buyer, $locked->seller, $locked->sortingCenter])->filter()->unique('id'),
                new OrderWorkflowNotification($locked, 'return_inspected', 'Seller inspection recorded. Only approved sellable units were restored; inspection does not issue a refund or pay earnings.'));
        }, 3);
    }
}
