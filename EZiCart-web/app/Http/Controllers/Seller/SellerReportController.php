<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\SalesReportService;
use Illuminate\Http\Request;

class SellerReportController extends Controller
{
    public function index(Request $request, SalesReportService $reports)
    {
        $filters = $reports->filters($request);
        $query = $reports->query($this->authenticatedUser(), $filters);
        $stats = $reports->summarize($query);
        $orders = $query->with('payout')->latest('id')->paginate(25)->withQueryString();
        $rows = $orders->getCollection()->map(fn ($order) => $reports->row($order));

        return view('seller.reports.index', compact('stats', 'orders', 'rows', 'filters'));
    }
}
