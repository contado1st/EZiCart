<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductComplianceEvent;
use App\Notifications\ProductComplianceNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminComplianceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending_review,approved,flagged'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $products = Product::query()
            ->with(['seller', 'complianceEvents.actor'])
            ->withCount(['complianceEvents as warning_count' => fn (Builder $query) => $query->where('action', 'flagged')])
            ->when(
                $validated['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('compliance_status', $status),
                fn (Builder $query) => $query->whereIn('compliance_status', ['pending_review', 'flagged']),
            )
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(fn (Builder $productQuery) => $productQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%')
                    ->orWhereHas('seller', fn (Builder $sellerQuery) => $sellerQuery->where('business_name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.compliance.index', compact('products'));
    }

    public function review(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'compliance_status' => ['required', 'in:approved,flagged'],
            'compliance_note' => ['nullable', 'string', 'max:1000', 'required_if:compliance_status,flagged'],
        ]);
        $admin = $this->authenticatedUser();

        DB::transaction(function () use ($product, $validated, $admin): void {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $previousStatus = $lockedProduct->compliance_status;
            $note = $validated['compliance_note'] ?? null;
            $newStatus = $validated['compliance_status'];

            $lockedProduct->forceFill([
                'compliance_status' => $newStatus,
                'compliance_note' => $note,
                'compliance_reviewed_by' => $admin->id,
                'compliance_reviewed_at' => now(),
            ])->save();

            ProductComplianceEvent::query()->create([
                'product_id' => $lockedProduct->id,
                'actor_id' => $admin->id,
                'action' => $newStatus,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'note' => $note,
            ]);

            $lockedProduct->seller->notify(new ProductComplianceNotification($lockedProduct, $newStatus, $note));
        });

        return back()->with('success', "Compliance status updated for {$product->name}.");
    }
}
