<?php

namespace App\Http\Controllers;

use App\Services\AdministrativeAuditService;
use App\Services\AnalyticsExportService;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics)
    {
        $report = $analytics->report($request->user(), $analytics->filters($request));
        $source = collect($report['sections'])->firstWhere('key', 'orders')['rows'];
        $page = max(1, (int) $request->input('page', 1));
        $orders = new LengthAwarePaginator(array_slice($source, ($page - 1) * 25, 25), count($source), 25, $page, ['path' => $request->url(), 'query' => $request->except('page')]);

        return response()->view('analytics.index', compact('report', 'orders'))->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, AnalyticsService $analytics, AnalyticsExportService $exports, AdministrativeAuditService $audits)
    {
        $data = $request->validate(['format' => ['required', 'in:csv,xlsx,pdf']]);
        $report = $analytics->report($request->user(), $analytics->filters($request));
        $format = $data['format'];
        $filename = 'ezicart-'.$report['role'].'-analytics-'.$report['filters']['from'].'-'.$report['filters']['to'].'.'.$format;
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        $audits->record($request->user(), $request->user(), 'analytics_report_exported', [], ['format' => $format, 'filters' => $report['filters']], 'Generated a role-scoped analytics report.');
        if ($format === 'csv') {
            return response()->streamDownload(fn () => $exports->csv($report), $filename, ['Content-Type' => 'text/csv; charset=UTF-8', ...$headers]);
        }
        $content = $format === 'xlsx' ? $exports->xlsx($report) : $exports->pdf($report);

        return response($content, 200, ['Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'"', ...$headers]);
    }
}
