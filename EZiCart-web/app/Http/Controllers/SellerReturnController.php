<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Services\SellerReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SellerReturnController extends Controller
{
    public function evidence(Request $request, Dispute $dispute)
    {
        $actor = $request->user();
        abort_unless($actor->status === 'approved' && ($actor->isCommerceAdmin() || ($actor->role === 'buyer' && $dispute->buyer_id === $actor->id) || ($actor->role === 'seller' && $dispute->seller_id === $actor->id)), 404);
        abort_unless(is_string($dispute->evidence_path) && Storage::disk('private')->exists($dispute->evidence_path), 404);

        return Storage::disk('private')->download($dispute->evidence_path, basename($dispute->evidence_path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function index(Request $request)
    {
        $data = $request->validate(['stage' => ['nullable', 'in:awaiting_seller,seller_approved,seller_rejected,escalated,deadline_approved,resolved,legacy']]);
        $stage = $data['stage'] ?? null;
        $cases = Dispute::where('seller_id', $request->user()->id)->with(['order', 'buyer', 'sellerResponse'])
            ->when($stage === 'legacy', fn ($q) => $q->whereDoesntHave('sellerResponse'))
            ->when($stage && $stage !== 'legacy', fn ($q) => $q->whereHas('sellerResponse', fn ($r) => $r->where('stage', $stage)))
            ->latest('id')->paginate(25)->withQueryString();

        return response()->view('seller.returns.index', compact('cases', 'stage'))->header('Cache-Control', 'private, no-store');
    }

    public function decide(Request $request, Dispute $dispute, SellerReturnService $returns)
    {
        abort_unless($dispute->seller_id === $request->user()->id, 404);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'version' => ['required', 'integer', 'min:1'], 'reason' => ['nullable', 'string', 'max:300']]);
        $response = $returns->decide($dispute, $request->user(), $data['decision'], $data['version'], $data['reason'] ?? null);

        return back()->with('success', $response->label().'. Request approval does not change custody, stock or send a refund.');
    }

    public function escalate(Request $request, Dispute $dispute, SellerReturnService $returns)
    {
        abort_unless($dispute->buyer_id === $request->user()->id, 404);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'urgent' => ['sometimes', 'boolean']]);
        $response = $returns->escalate($dispute, $request->user(), $data['version'], (bool) ($data['urgent'] ?? false));

        return back()->with('success', $response->label().'. Review the current request stage below.');
    }
}
