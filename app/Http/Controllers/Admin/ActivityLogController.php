<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor->isCommerceAdmin() || $actor->isLogisticsAdmin(), 403);
        $filters = $request->validate([
            'actor_id' => ['nullable', 'integer'], 'action' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'in:commerce,logistics,system'], 'record' => ['nullable', 'integer', 'min:1'],
            'entity_type' => ['nullable', 'string', 'max:150'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $logs = DB::table('administrative_audits as audits')->leftJoin('users as actor', 'actor.id', '=', 'audits.actor_id')
            ->select('audits.*', 'actor.first_name', 'actor.last_name', 'actor.role')
            ->where(function ($query) use ($actor) {
                $query->whereIn('actor.role', $actor->isCommerceAdmin() ? ['admin', 'ecommerce_admin', 'seller', 'buyer'] : ['logistics_admin', 'sorting_center', 'courier'])
                    ->orWhereNull('audits.actor_id');
            })
            ->when(filled($filters['actor_id'] ?? null), fn ($q) => $q->where('audits.actor_id', $filters['actor_id']))
            ->when(filled($filters['action'] ?? null), fn ($q) => $q->where('audits.action', 'like', '%'.$filters['action'].'%'))
            ->when(filled($filters['record'] ?? null), fn ($q) => $q->where('audits.entity_id', $filters['record']))
            ->when(filled($filters['entity_type'] ?? null), fn ($q) => $q->where('audits.entity_type', $filters['entity_type']))
            ->when(filled($filters['module'] ?? null), function ($q) use ($filters) {
                if ($filters['module'] === 'system') {
                    $q->whereNull('audits.actor_id');
                } else {
                    $q->whereIn('actor.role', $filters['module'] === 'commerce' ? ['admin', 'ecommerce_admin', 'seller', 'buyer'] : ['logistics_admin', 'sorting_center', 'courier']);
                }
            })
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->where('audits.created_at', '>=', $filters['from'].' 00:00:00'))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->where('audits.created_at', '<=', $filters['to'].' 23:59:59'))
            ->orderByDesc('audits.id')->paginate(30)->withQueryString();

        return view('admin.activity', compact('logs', 'filters'));
    }
}
