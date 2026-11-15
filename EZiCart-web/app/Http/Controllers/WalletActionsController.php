<?php

namespace App\Http\Controllers;

use App\Models\DemoFundingCode;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\CheckoutPricingService;
use App\Services\DemoFundingCodeService;
use App\Services\FinancialLedgerService;
use App\Services\QrCodeService;
use App\Services\WalletFundingService;
use App\Services\WalletTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class WalletActionsController extends Controller
{
    private function details(Request $request): array
    {
        return $request->validate(['recipient_reference' => ['required', 'string', 'max:100'], 'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000', 'decimal:0,2'],
            'request_key' => ['required', 'uuid'], 'note' => ['nullable', 'string', 'max:200']]);
    }

    public function summary(Request $request, WalletTransferService $transfers, WalletFundingService $funding)
    {
        $actor = $request->user();
        $account = $transfers->source($actor);

        return response()->json(['success' => true, 'data' => ['mode' => 'demo', 'enabled' => $funding->enabled(),
            'available_cents' => $account->balance_cents, 'currency' => 'PHP', 'frozen' => $account->frozen,
            'receive_reference' => in_array($actor->role, WalletTransferService::USER_ROLES, true) ? $transfers->reference($actor) : null]])->header('Cache-Control', 'private, no-store');
    }

    public function preview(Request $request, WalletTransferService $transfers)
    {
        $data = $this->details($request);
        $actor = $request->user();
        $review = $transfers->review($actor, $data['recipient_reference'], CheckoutPricingService::cents($data['amount']), $data['request_key'], $data['note'] ?? '');
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $this->reviewData($review),
                'html' => $request->is('api/*') ? null : view('finance.partials.transfer-confirmation', compact('actor', 'review'))->render()])->header('Cache-Control', 'private, no-store');
        }

        return view('finance.transfer-review', compact('actor', 'review'));
    }

    private function reviewData(array $review): array
    {
        return ['recipient_name' => trim($review['recipient']->first_name.' '.$review['recipient']->last_name), 'recipient_role' => $review['recipient']->role,
            'recipient_reference' => $review['reference'], 'amount_cents' => $review['amount_cents'], 'request_key' => $review['request_key'],
            'note' => $review['note'], 'review_expires' => $review['review_expires'], 'review_hash' => $review['review_hash']];
    }

    public function send(Request $request, WalletTransferService $transfers)
    {
        $data = $this->details($request);
        $proof = $request->validate(['review_expires' => ['required', 'integer'], 'review_hash' => ['required', 'string', 'size:64'], 'password' => ['required', 'string', 'max:500']]);
        if (! Hash::check($proof['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Your current password is incorrect. No funds were sent.']);
        }
        $transaction = $transfers->send($request->user(), $data['recipient_reference'], CheckoutPricingService::cents($data['amount']), $data['request_key'], $data['note'] ?? '', (int) $proof['review_expires'], $proof['review_hash']);
        if ($request->expectsJson()) {
            $receipt = $this->receiptData($transaction);

            return response()->json(['success' => true, 'message' => 'Demo funds posted once. Keep this transaction reference.', 'data' => $receipt,
                'available_cents' => $transfers->source($request->user())->fresh()->balance_cents,
                'html' => $request->is('api/*') ? null : view('finance.partials.transfer-receipt', $receipt + ['transaction' => $transaction])->render()])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('wallet.transfer.receipt', $transaction->reference)->with('success', 'Demo funds posted once. No real money was transferred.');
    }

    private function receiptData(LedgerTransaction $transaction): array
    {
        $metadata = $transaction->metadata;
        $sender = User::find($metadata['sender_id']);
        $recipient = User::find($metadata['recipient_id']);

        return ['reference' => $transaction->reference, 'amount_cents' => $metadata['amount_cents'], 'type' => $transaction->type,
            'sender_name' => $metadata['sender_name'] ?? ($sender ? trim($sender->first_name.' '.$sender->last_name) : 'Recorded sender'),
            'recipient_name' => $metadata['recipient_name'] ?? ($recipient ? trim($recipient->first_name.' '.$recipient->last_name) : 'Recorded recipient'),
            'note' => $metadata['note'] ?? '', 'posted_at' => $transaction->created_at->toIso8601String(), 'demo' => true];
    }

    public function receipt(Request $request, string $reference)
    {
        $transaction = LedgerTransaction::where('reference', $reference)->whereIn('type', ['wallet_transfer', 'admin_wallet_allocation'])->firstOrFail();
        abort_unless(in_array($request->user()->id, [(int) ($transaction->metadata['sender_id'] ?? 0), (int) ($transaction->metadata['recipient_id'] ?? 0)], true), 404);
        $receipt = $this->receiptData($transaction);

        return $request->expectsJson() ? response()->json(['success' => true, 'data' => $receipt])->header('Cache-Control', 'private, no-store')
            : response()->view('finance.transfer-receipt', $receipt + ['transaction' => $transaction])->header('Cache-Control', 'private, no-store');
    }

    public function issue(Request $request, DemoFundingCodeService $codes, WalletTransferService $transfers)
    {
        $data = $this->details($request);
        $extra = $request->validate(['expires_minutes' => ['required', 'integer', 'min:15', 'max:10080'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $recipient = $transfers->recipient($request->user(), $data['recipient_reference']);
        [$code, $secret] = $codes->issue($request->user(), $recipient, CheckoutPricingService::cents($data['amount']), $data['request_key'], (int) $extra['expires_minutes'], $extra['reason']);
        if ($request->expectsJson()) {
            $code->load(['issuer', 'recipient', 'transaction']);
            $secretQrSvg = $secret ? app(QrCodeService::class)->svg($secret) : null;

            return response()->json(['success' => true, 'message' => 'Demo code receipt ready. Funds are added only when the named recipient redeems it.', 'data' => ['reference' => $code->reference, 'status' => $code->state()],
                'html' => view('finance.partials.code-receipt', compact('code', 'secret', 'secretQrSvg'))->render()])->header('Cache-Control', 'private, no-store');
        }
        if ($secret) {
            $request->session()->flash('demo_code_once', ['reference' => $code->reference, 'secret' => $secret]);
        }

        return redirect()->route('wallet.codes.receipt', $code->reference)->with('success', 'Demo code issued. The recipient balance changes only on redemption.');
    }

    public function redeem(Request $request, DemoFundingCodeService $codes)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100']]);
        // A failed redemption must not flash a real redeemable secret back into old input.
        $request->merge(['code' => '']);
        $code = $codes->redeem($request->user(), $data['code']);
        if ($request->expectsJson()) {
            $code->load(['issuer', 'recipient', 'transaction']);

            return response()->json(['success' => true, 'message' => 'Demo code redeemed. This receipt can credit funds only once.', 'data' => ['reference' => $code->reference, 'amount_cents' => $code->amount_cents, 'status' => $code->state()],
                'available_cents' => app(FinancialLedgerService::class)->wallet($request->user())->balance_cents,
                'html' => $request->is('api/*') ? null : view('finance.partials.code-receipt', ['code' => $code, 'secret' => null, 'secretQrSvg' => null])->render()])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('wallet.codes.receipt', $code->reference)->with('success', 'Demo code redeemed. This receipt can credit funds only once.');
    }

    public function codeReceipt(Request $request, string $reference)
    {
        abort_unless(Schema::hasTable('demo_funding_codes'), 503, 'Demo funding codes are awaiting the database update.');
        $code = DemoFundingCode::where('reference', $reference)->firstOrFail();
        abort_unless(in_array($request->user()->id, [(int) $code->issuer_id, (int) $code->recipient_id], true), 404);
        $code->load(['issuer', 'recipient', 'transaction']);
        $secret = null;
        if ($request->hasSession() && $request->user()->id === (int) $code->issuer_id) {
            $once = $request->session()->pull('demo_code_once');
            if (($once['reference'] ?? null) === $code->reference) {
                $secret = $once['secret'];
            }
        }
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['reference' => $code->reference, 'amount_cents' => $code->amount_cents,
                'status' => $code->state(), 'expires_at' => $code->expires_at->toIso8601String(), 'transaction_reference' => $code->transaction?->reference]])->header('Cache-Control', 'private, no-store');
        }
        $secretQrSvg = $secret ? app(QrCodeService::class)->svg($secret) : null;

        return response()->view('finance.code-receipt', compact('code', 'secret', 'secretQrSvg'))->header('Cache-Control', 'private, no-store');
    }

    public function revoke(Request $request, string $reference, DemoFundingCodeService $codes)
    {
        abort_unless(Schema::hasTable('demo_funding_codes'), 503, 'Demo funding codes are awaiting the database update.');
        $codes->revoke($request->user(), DemoFundingCode::where('reference', $reference)->firstOrFail());

        return back()->with('success', 'Unused demo code revoked. No wallet balance changed.');
    }
}
