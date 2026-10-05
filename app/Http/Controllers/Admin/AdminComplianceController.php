<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductComplianceEvent;
use App\Notifications\ProductComplianceNotification;
use App\Notifications\SellerComplianceWarningNotification;
use App\Services\TransactionAwareNotificationSender;
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
            ->withCount(['complianceEvents as warning_count' => fn (Builder $query) => $query->whereIn('action', ['flagged', 'warning_issued'])])
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

    public function review(Request $request, Product $product, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        $validated = $request->validate([
            'compliance_status' => ['required', 'in:approved,flagged'],
            'compliance_note' => ['nullable', 'string', 'max:1000', 'required_if:compliance_status,flagged'],
        ]);
        $admin = $this->authenticatedUser();

        DB::transaction(function () use ($product, $validated, $admin, $notifications): void {
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

            $notifications->send($lockedProduct->seller, new ProductComplianceNotification($lockedProduct, $newStatus, $note));
        });

        return back()->with('success', "Compliance status updated for {$product->name}.");
    }

    public function warn(Request $request, Product $product, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        $validated = $request->validate([
            'warning_note' => ['required', 'string', 'max:1000'],
        ]);
        $admin = $this->authenticatedUser();

        DB::transaction(function () use ($product, $validated, $admin, $notifications): void {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $seller = $lockedProduct->seller;
            abort_unless($seller?->role === 'seller', 422, 'This product has no valid seller account.');

            ProductComplianceEvent::query()->create([
                'product_id' => $lockedProduct->id,
                'actor_id' => $admin->id,
                'action' => 'warning_issued',
                'previous_status' => $lockedProduct->compliance_status,
                'new_status' => $lockedProduct->compliance_status,
                'note' => $validated['warning_note'],
            ]);

            $notifications->send($seller, new SellerComplianceWarningNotification($lockedProduct, $validated['warning_note']));
        });

        return back()->with('success', "A compliance warning was sent to the seller of {$product->name}.");
    }
}
