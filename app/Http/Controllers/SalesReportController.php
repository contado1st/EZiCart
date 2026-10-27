<?php

namespace App\Http\Controllers;

use App\Services\SalesReportService;
use App\Services\TabularExportService;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    public function __invoke(Request $request, SalesReportService $reports, TabularExportService $exports)
    {
        $filters = $reports->filters($request);
        $query = $reports->query($request->user(), $filters);
        if ($request->filled('format')) {
            $headers = ['Order', 'Placed', 'Seller', 'Municipality', 'Status', 'Gross PHP', 'Seller discount PHP', 'Platform discount PHP', 'Shipping PHP', 'Refund PHP', 'Merchandise refund PHP', 'Platform subsidy reversal PHP', 'Recognized commission PHP', 'Net seller sales PHP'];
            if ($filters['format'] === 'csv') {
                return response()->streamDownload(function () use ($query, $reports, $exports, $headers) {
                    $rows = (function () use ($query, $reports, $headers) {
                        yield $headers;
                        foreach ($query->orderBy('id')->lazyById(200) as $order) {
                            $row = $reports->row($order);
                            foreach (array_slice(array_keys($row), 5) as $key) {
                                $row[$key] = number_format($row[$key] / 100, 2, '.', '');
                            }
                            yield array_values($row);
                        }
                    })();
                    $exports->csv($rows);
                }, 'ezicart-sales.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
            }
            abort_if((clone $query)->count() > 2000, 422, 'Narrow the filters to 2,000 orders or fewer for PDF export. CSV supports larger reports.');
            $lines = ['EZiCart Sales Report (PHP)', $filters['from_date'].' to '.$filters['to_date'], 'Sales recognized on completed orders; executed refunds shown separately.', ''];
            foreach ($query->orderBy('id')->lazyById(200) as $order) {
                $row = $reports->row($order);
                $lines[] = $row['order'].' | '.$row['placed'].' | '.$row['status'];
                $lines[] = mb_strimwidth($row['seller'].' | '.$row['municipality'], 0, 88, '...');
                $lines[] = 'Gross '.number_format($row['gross'] / 100, 2).' | Seller discount '.number_format($row['seller_discount'] / 100, 2).' | Platform discount '.number_format($row['platform_discount'] / 100, 2);
                $lines[] = 'Shipping '.number_format($row['shipping'] / 100, 2).' | Refund '.number_format($row['refund'] / 100, 2).' | Merchandise refund '.number_format($row['merchandise_refund'] / 100, 2);
                $lines[] = 'Platform subsidy reversal '.number_format($row['platform_reversal'] / 100, 2);
                $lines[] = 'Recognized commission '.number_format($row['commission'] / 100, 2).' | Net seller sales '.number_format($row['net'] / 100, 2);
                $lines[] = '';
            }

            return response($exports->pdf($lines), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="ezicart-sales.pdf"', 'Cache-Control' => 'private, no-store']);
        }
        $orders = $query->latest('id')->paginate(25)->withQueryString();
        $rows = $orders->getCollection()->map(fn ($order) => $reports->row($order));

        return view('reports.sales', compact('orders', 'rows', 'filters'));
    }
}
