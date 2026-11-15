<?php

namespace App\Http\Controllers;

use App\Models\WalletFundingIntent;
use App\Services\CheckoutPricingService;
use App\Services\DemoWalletFundingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class DemoWalletFundingController extends Controller
{
    public function store(Request $request, DemoWalletFundingService $funding)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'],
            'request_key' => ['required', 'uuid'], 'kind' => ['required', 'in:demo_credit,demo_cash_deposit'], 'reason' => ['required', 'string', 'min:5', 'max:500'],
            'receipt_reference' => ['required_if:kind,demo_cash_deposit', 'nullable', 'string', 'min:3', 'max:120'],
            'evidence' => ['required_if:kind,demo_cash_deposit', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']]);
        $path = $request->file('evidence')?->store('wallet-demo-deposits', 'private');
        try {
            $intent = $funding->request($request->user(), CheckoutPricingService::cents($data['amount']), $data['request_key'], $data['kind'],
                ['reason' => $data['reason'], 'receipt_reference' => $data['receipt_reference'] ?? null, 'evidence_path' => $path,
                    'evidence_sha256' => $path ? hash_file('sha256', $request->file('evidence')->getRealPath()) : null]);
            if ($path && ($intent->request_details['evidence_path'] ?? null) !== $path) {
                Storage::disk('private')->delete($path);
            }
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('private')->delete($path);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['request_key' => 'This funding request or demo receipt was already recorded. Review its original history.']);
            }
            throw $exception;
        }

        return redirect()->route('finance.wallet', ['workspace_tab' => 'wallet-funding'])->with('success', 'Demo funding request recorded. Funds stay unchanged until an independent administrator approves.');
    }

    public function review(Request $request, WalletFundingIntent $intent, DemoWalletFundingService $funding)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $funding->review($intent, $request->user(), $data['decision'], $data['reason']);

        return back()->with('success', 'Demo funding decision recorded. No real money was transferred.');
    }

    public function evidence(Request $request, WalletFundingIntent $intent, DemoWalletFundingService $funding)
    {
        abort_unless($intent->provider === 'internal_demo' && ($intent->user_id === $request->user()->id || $funding->canReview($request->user(), $intent)), 404);
        abort_unless($funding->validEvidence($intent->request_details ?? []), 404);

        return Storage::disk('private')->download($intent->request_details['evidence_path'], 'demo-deposit-evidence.'.pathinfo($intent->request_details['evidence_path'], PATHINFO_EXTENSION),
            ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
