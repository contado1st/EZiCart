<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\AreaMunicipality;
use App\Models\User;
use App\Services\AdministrativeAuditService;
use App\Services\OrderAreaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoverageController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        $mappings = AreaMunicipality::with('area')->whereHas('area', fn ($q) => $q->where('is_active', true)
            ->when(! $actor->isLogisticsAdmin(), fn ($q) => $q->where('sorting_center_id', $actor->id)))->orderBy('municipality')->get();
        $riders = User::where('role', 'courier')->where('status', 'approved')->whereHas('serviceAreas', fn ($q) => $q->whereIn('areas.id', $mappings->pluck('area_id')))->orderBy('first_name')->get();
        $coverage = DB::table('barangay_rider_assignments as coverage')->join('area_municipalities as mapping', 'mapping.id', '=', 'coverage.area_municipality_id')
            ->join('users as primary_rider', 'primary_rider.id', '=', 'coverage.primary_rider_id')
            ->leftJoin('users as backup', 'backup.id', '=', 'coverage.backup_rider_id')
            ->whereIn('mapping.id', $mappings->pluck('id'))
            ->select('coverage.*', 'mapping.municipality', 'mapping.province', 'primary_rider.first_name as primary_name', 'backup.first_name as backup_name')->orderBy('mapping.municipality')->paginate(30);
        $shipping = DB::table('commerce_settings')->where('key', 'shipping_per_seller_cents')->value('value') ?? config('commerce.shipping_per_seller_cents');

        return view('logistics.coverage', compact('mappings', 'riders', 'coverage', 'shipping'));
    }

    public function store(Request $request, OrderAreaService $areas, AdministrativeAuditService $audits)
    {
        $data = $request->validate([
            'area_municipality_id' => ['required', 'integer', 'exists:area_municipalities,id'], 'barangay' => ['required', 'string', 'max:120'],
            'primary_rider_id' => ['required', 'integer', 'exists:users,id'], 'backup_rider_id' => ['nullable', 'integer', 'different:primary_rider_id', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        DB::transaction(function () use ($request, $data, $areas, $audits) {
            $mapping = AreaMunicipality::whereKey($data['area_municipality_id'])->lockForUpdate()->firstOrFail();
            abort_unless($mapping->area->is_active && ($request->user()->isLogisticsAdmin() || $mapping->area->sorting_center_id === $request->user()->id), 403);
            foreach (array_filter([$data['primary_rider_id'], $data['backup_rider_id'] ?? null]) as $riderId) {
                $rider = User::whereKey($riderId)->lockForUpdate()->firstOrFail();
                abort_unless($rider->role === 'courier' && $rider->status === 'approved' && $rider->serviceAreas()->where('areas.id', $mapping->area_id)->exists(), 422, 'Both primary and backup riders must serve this municipality area.');
            }
            $key = ['area_municipality_id' => $mapping->id, 'barangay_normalized' => $areas->normalizeAddressValue($data['barangay'])];
            $previous = (array) DB::table('barangay_rider_assignments')->where($key)->first();
            $values = ['barangay' => trim($data['barangay']), 'primary_rider_id' => $data['primary_rider_id'], 'backup_rider_id' => $data['backup_rider_id'] ?? null, 'updated_at' => now()];
            DB::table('barangay_rider_assignments')->updateOrInsert($key, $values + ['created_at' => $previous['created_at'] ?? now()]);
            $audits->record($request->user(), $mapping, 'barangay_rider_coverage_saved', $previous, $key + $values, $data['reason']);
        }, 3);

        return back()->with('success', 'Primary and backup barangay coverage saved.');
    }

    public function vehicle(Request $request, User $rider, AdministrativeAuditService $audits)
    {
        $data = $request->validate(['vehicle_category' => ['required', 'in:two_wheel,four_wheel'], 'vehicle_description' => ['required', 'string', 'max:120'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($request, $rider, $data, $audits) {
            $locked = User::whereKey($rider->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->role === 'courier' && ($request->user()->isLogisticsAdmin() || $locked->serviceAreas()->where('sorting_center_id', $request->user()->id)->exists()), 403);
            $previous = $locked->only(['vehicle_category', 'vehicle_description']);
            $locked->forceFill(collect($data)->only(['vehicle_category', 'vehicle_description'])->all())->save();
            $audits->record($request->user(), $locked, 'rider_vehicle_updated', $previous, $locked->only(['vehicle_category', 'vehicle_description']), $data['reason']);
        });

        return back()->with('success', 'Rider vehicle classification updated.');
    }

    public function shipping(Request $request, AdministrativeAuditService $audits)
    {
        abort_unless($request->user()->isLogisticsAdmin(), 403);
        $data = $request->validate(['shipping_fee' => ['required', 'numeric', 'between:50,80', 'decimal:0,2'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($request, $data, $audits) {
            $previous = DB::table('commerce_settings')->where('key', 'shipping_per_seller_cents')->lockForUpdate()->value('value') ?? config('commerce.shipping_per_seller_cents');
            $next = (int) round($data['shipping_fee'] * 100);
            DB::table('commerce_settings')->upsert(['key' => 'shipping_per_seller_cents', 'value' => $next, 'created_at' => now(), 'updated_at' => now()], ['key'], ['value', 'updated_at']);
            $audits->record($request->user(), $request->user(), 'standard_shipping_fee_updated', ['cents' => $previous], ['cents' => $next], $data['reason']);
        });

        return back()->with('success', 'Standard shipping fee updated for new checkout quotes. Existing orders retain their recorded fees.');
    }
}
