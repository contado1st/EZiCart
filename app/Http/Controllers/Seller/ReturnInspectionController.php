<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AdministrativeAuditService;
use App\Services\InventoryRestorationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnInspectionController extends Controller
{
    public function store(Request $request, Order $order, InventoryRestorationService $inventory, AdministrativeAuditService $audits): RedirectResponse
    {
        abort_unless($order->seller_id === $request->user()->id, 403);
        $data = $request->validate(['restock' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($request, $order, $data, $inventory, $audits): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'RETURNED_TO_SELLER' && $locked->restock_inspected_at === null && $locked->inventory_restored_at === null, 422, 'This return is not awaiting inspection or its stock was already restored.');
            $locked->forceFill(['restock_inspected_at' => now(), 'restock_inspected_by' => $request->user()->id, 'restock_approved' => (bool) $data['restock']])->save();
            if ($data['restock']) {
                $inventory->restore($locked);
            }
            $audits->record($request->user(), $locked, 'return_inspected', [], ['restocked' => (bool) $data['restock']], $data['reason']);
        });

        return back()->with('success', $data['restock'] ? 'Inspected items restocked.' : 'Inspection recorded. Items excluded from stock.');
    }
}
