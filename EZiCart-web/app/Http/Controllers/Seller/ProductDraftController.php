<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\ProductDraft;
use App\Services\ProductDraftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductDraftController extends Controller
{
    public function index(Request $request, ProductDraftService $draftService)
    {
        $draftService->authorize($request);
        $drafts = ProductDraft::where('user_id', $draftService->actor($request)->id)->whereNull('submitted_at')->latest('updated_at')->paginate(25);

        return view('seller.products.drafts', [...$this->workspace($request), 'drafts' => $drafts]);
    }

    public function create(Request $request, ProductDraftService $drafts)
    {
        $drafts->authorize($request);

        return view('seller.products.create', $this->workspace($request));
    }

    public function edit(Request $request, ProductDraft $draft, ProductDraftService $drafts)
    {
        $drafts->authorize($request, $draft);

        return view('seller.products.create', [...$this->workspace($request), 'draft' => $draft, 'draftValues' => $draft->payload]);
    }

    public function store(Request $request, ProductDraftService $drafts)
    {
        $draft = $drafts->save($request);

        return redirect()->route($this->workspace($request)['draftRoutePrefix'].'.edit', $draft)->with('success', 'Private draft saved. It is not listed for buyers.');
    }

    public function update(Request $request, ProductDraft $draft, ProductDraftService $drafts)
    {
        $drafts->save($request, $draft);

        return back()->with('success', 'Private draft updated. Submit when the required details are complete.');
    }

    public function media(Request $request, ProductDraft $draft, string $field, ProductDraftService $drafts)
    {
        $drafts->authorize($request, $draft);
        $upload = $draft->uploads[$field] ?? null;
        abort_unless($upload && Storage::disk('private')->exists($upload['path']), 404);

        return Storage::disk('private')->download($upload['path'], $upload['name'], ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, ProductDraft $draft, ProductDraftService $drafts)
    {
        DB::transaction(function () use ($request, $draft, $drafts): void {
            $drafts->lockApplicant($request);
            $locked = ProductDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $drafts->authorize($request, $locked);
            $paths = array_column($locked->uploads, 'path');
            $locked->delete();
            DB::afterCommit(fn () => $drafts->deleteFiles($paths));
        });

        return redirect()->route($this->workspace($request)['draftRoutePrefix'].'.index')->with('success', 'Private draft removed.');
    }

    private function workspace(Request $request): array
    {
        return ['applicant' => $request->attributes->get('draft_applicant'),
            'draftRoutePrefix' => $request->routeIs('applicant-drafts.*') ? 'applicant-drafts' : 'seller.product-drafts'];
    }
}
