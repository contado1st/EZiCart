<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewReplyService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SellerReviewController extends Controller
{
    public function reply(Request $request, Review $review, ReviewReplyService $service)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000'], 'version' => ['prohibited']]);
        $service->save($request->user(), $review, $data['body']);

        return back()->with('success', 'Seller response posted.');
    }

    public function updateReply(Request $request, Review $review, ReviewReplyService $service)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000'], 'version' => ['required', 'integer', 'min:1']]);
        $service->save($request->user(), $review, $data['body'], (int) $data['version']);

        return back()->with('success', 'Seller response updated.');
    }

    public function index(Request $request)
    {
        $filters = $request->validate(['rating' => ['nullable', 'integer', 'between:1,5'],
            'text' => ['nullable', 'in:with,without'], 'media' => ['nullable', 'in:with,without'],
            'from_date' => ['nullable', 'date_format:Y-m-d'], 'to_date' => ['nullable', 'date_format:Y-m-d']]);
        if (filled($filters['from_date'] ?? null) && filled($filters['to_date'] ?? null) && $filters['from_date'] > $filters['to_date']) {
            throw ValidationException::withMessages(['to_date' => 'The end date must not precede the start date.']);
        }
        $query = Review::query()->whereHas('order', fn ($orders) => $orders->where('seller_id', $request->user()->id))
            ->with(['buyer:id,first_name,last_name', 'photos', 'product:id,name', 'order.items', 'reply'])
            ->when($filters['rating'] ?? null, fn ($q, $rating) => $q->where('rating', $rating))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', $date.' 00:00:00'))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->where('created_at', '<', CarbonImmutable::parse($date)->addDay()->startOfDay()));
        if (filled($filters['media'] ?? null)) {
            $filters['media'] === 'with' ? $query->whereHas('photos') : $query->whereDoesntHave('photos');
        }
        if (($filters['text'] ?? null) === 'with') {
            $query->whereNotNull('comment')->where('comment', '<>', '');
        } elseif (($filters['text'] ?? null) === 'without') {
            $query->where(fn ($q) => $q->whereNull('comment')->orWhere('comment', ''));
        }
        $reviews = $query->latest('created_at')->latest('id')->paginate(25)->withQueryString();

        return response()->view('seller.reviews.index', compact('reviews', 'filters'))->header('Cache-Control', 'private, no-store');
    }
}
