<?php

namespace App\Services;

use App\Models\RemittanceBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RemittanceReportService
{
    public const STATUSES = ['SUBMITTED', 'DISCREPANCY', 'UNDER_INVESTIGATION', 'VERIFIED', 'SETTLED'];

    public const HEADERS = ['Reference', 'Rider ID', 'Hub ID', 'Expected PHP', 'Submitted PHP', 'Received PHP', 'Discrepancy PHP', 'Status', 'Submitted at'];

    public function filters(array $input): array
    {
        return validator($input, ['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)], 'rider_id' => ['nullable', 'integer', 'min:1'], 'hub_id' => ['nullable', 'integer', 'min:1']])->validate();
    }

    public function query(User $actor, array $filters = []): Builder
    {
        abort_unless($actor->status === 'approved' && in_array($actor->role, ['courier', 'sorting_center', 'logistics_admin'], true), 403);

        return RemittanceBatch::query()->when($actor->role === 'courier', fn ($q) => $q->where('rider_id', $actor->id))
            ->when($actor->role === 'sorting_center', fn ($q) => $q->where('hub_id', $actor->id))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->where('submitted_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->where('submitted_at', '<', Carbon::parse($filters['to'])->startOfDay()->addDay()))
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['rider_id'] ?? null), fn ($q) => $q->where('rider_id', $filters['rider_id']))
            ->when(filled($filters['hub_id'] ?? null), fn ($q) => $q->where('hub_id', $filters['hub_id']));
    }

    public function report(User $actor, array $filters): array
    {
        // Count before rendering/streaming: never truncate a financial statement silently.
        $query = $this->query($actor, $filters);
        if ((clone $query)->count() > 10000) {
            throw ValidationException::withMessages(['from' => 'This statement exceeds 10,000 batches. Narrow the dates, rider, hub or status; no records were omitted.']);
        }
        $batches = $query->select(['id', 'reference', 'rider_id', 'hub_id', 'expected_cents', 'submitted_cents', 'received_cents', 'discrepancy_cents', 'status', 'submitted_at'])->orderBy('id')->limit(10001)->get();
        if ($batches->count() > 10000) {
            throw ValidationException::withMessages(['from' => 'This statement exceeds 10,000 batches. Narrow the dates, rider, hub or status; no records were omitted.']);
        }
        $rows = $batches->map(fn ($batch) => [(string) $batch->reference, (string) $batch->rider_id, (string) $batch->hub_id, $batch->expected_cents / 100.0, $batch->submitted_cents / 100.0,
            $batch->received_cents === null ? null : $batch->received_cents / 100.0, $batch->discrepancy_cents === null ? null : $batch->discrepancy_cents / 100.0,
            $batch->status, $batch->submitted_at?->format('Y-m-d H:i:s')])->all();

        return ['filters' => $filters, 'rows' => $rows, 'count' => $batches->count(), 'generated_at' => now()->toIso8601String(), 'timezone' => config('app.timezone'), 'role' => $actor->role,
            'totals' => ['expected' => $batches->sum('expected_cents'), 'submitted' => $batches->sum('submitted_cents'), 'received' => $batches->sum('received_cents'),
                'discrepancy' => $batches->sum('discrepancy_cents'), 'not_counted' => $batches->whereNull('received_cents')->count()]];
    }

    public function sheets(array $report): array
    {
        $totals = $report['totals'];
        $sheets = ['Summary' => [['Metric', 'Total PHP', 'Count'], ['Expected cash', $totals['expected'] / 100.0, ''], ['Rider-declared cash', $totals['submitted'] / 100.0, ''],
            ['Recorded received cash', $totals['received'] / 100.0, ''], ['Net discrepancy of counted batches', $totals['discrepancy'] / 100.0, ''],
            ['Matching batches', '', $report['count']], ['Awaiting physical cash count', '', $totals['not_counted']]],
            'Batch details' => [self::HEADERS, ...$report['rows']],
            'Definitions' => [['Topic', 'Meaning'], ['Scope', 'Only batches permitted to the signed-in role and selected filters; all matching pages are included.'],
                ['Date basis', 'Batch submitted_at in the displayed application timezone; start inclusive and end next-midnight exclusive.'],
                ['Expected / submitted', 'Cash expected from order records versus rider declaration. Neither proves physical receipt.'],
                ['Received / discrepancy', 'Only recorded physical counts are included. Missing counts remain unavailable, not fabricated zero receipts.'],
                ['Net discrepancy', 'Received minus expected where counted; negative is shortage and positive is excess. Netting can hide separate shortages/excesses; inspect batch details.'],
                ['Settlement', 'Receipt verification is not a seller or logistics payout. Existing funding, eligibility and approval checks still apply.'],
                ['Identifiers and safety', 'XLSX identifiers are text. CSV formula-like strings and numeric identifiers with leading zeros or over 15 digits carry a protective apostrophe.'],
                ['Confidential evidence', 'Private file paths, account credentials, recipient addresses and supporting document contents are not exported.'],
                ['Empty result', 'A valid empty statement retains headings, filters, zero totals and no fabricated batch rows.'],
                ['Sandbox', 'System wallet values are sandbox accounting credits; this report is not a live e-money statement.']]];
        foreach ($sheets as $name => $rows) {
            $sheets[$name] = [['EZiCart | COD remittance reconciliation'], [$name],
                ['Submitted period: '.($report['filters']['from'] ?? 'All earlier dates').' to '.($report['filters']['to'] ?? 'All later dates').' | Workspace: '.$report['role']],
                ['Generated: '.$report['generated_at'].' | Timezone: '.$report['timezone'].' | Currency: PHP'],
                [($report['count'] === 0 ? 'No matching remittance batches. ' : $report['count'].' matching batches. ').'Status: '.($report['filters']['status'] ?? 'All').' | Rider: '.($report['filters']['rider_id'] ?? 'All permitted').' | Hub: '.($report['filters']['hub_id'] ?? 'All permitted')], [], ...$rows];
        }

        return $sheets;
    }

    public function csvRows(array $report): iterable
    {
        $sheets = $this->sheets($report);
        foreach (array_slice($sheets['Batch details'], 0, 7) as $row) {
            yield array_pad($row, 9, '');
        }
        foreach ($report['rows'] as $row) {
            foreach ([0, 1, 2] as $column) {
                if (preg_match('/\A(?:0\d+|\d{16,})\z/', $row[$column])) {
                    $row[$column] = "'".$row[$column];
                }
            }
            yield array_map(fn ($value) => $value === null ? 'Not available' : $value, $row);
        }
        yield array_fill(0, 9, '');
        foreach (['Summary', 'Definitions'] as $name) {
            yield array_pad([$name], 9, '');
            foreach (array_slice($sheets[$name], 6) as $row) {
                yield array_pad($row, 9, '');
            }
        }
    }
}
