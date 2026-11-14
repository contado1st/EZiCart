<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\SellerReturnResponse;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SellerReturnService
{
    public function __construct(private AdministrativeAuditService $audits, private TransactionAwareNotificationSender $notifications) {}

    /** Called inside the new-request transaction with the order locked. Legacy cases are not enrolled. */
    public function start(Dispute $dispute): void
    {
        $response = SellerReturnResponse::create(['dispute_id' => $dispute->id, 'response_due_at' => now()->addHours(72)]);
        $this->record($dispute, $response, $dispute->buyer, 'return_requested', [], 'Return request opened. The seller has 72 elapsed hours to respond. Approval is not a refund or physical handover.');
    }

    public function decide(Dispute $dispute, User $seller, string $decision, int $version, ?string $reason): SellerReturnResponse
    {
        return $this->locked($dispute, function ($order, $case, $response) use ($seller, $decision, $version, $reason) {
            abort_unless($seller->status === 'approved' && $seller->role === 'seller' && $case->seller_id === $seller->id, 404);
            if ($this->expireLocked($case, $response)) {
                return $response->refresh();
            }
            $this->assertPending($case, $response, $version);
            abort_unless($response->stage === 'awaiting_seller' && in_array($order->status, ['DELIVERED', 'COMPLETED'], true), 422, 'This request no longer accepts a seller response.');
            $data = Validator::make(['decision' => $decision, 'reason' => $reason], ['decision' => ['required', 'in:approve,reject'], 'reason' => ['required_if:decision,reject', 'nullable', 'string', 'min:10', 'max:300']])->validate();
            $previous = $response->only(['stage', 'version']);
            $response->forceFill(['stage' => $decision === 'approve' ? 'seller_approved' : 'seller_rejected', 'seller_decision' => $decision === 'approve' ? 'approved' : 'rejected', 'seller_reason' => $decision === 'reject' ? $data['reason'] : null, 'seller_responded_at' => now(), 'escalation_due_at' => $decision === 'reject' ? now()->addHours(72) : null, 'version' => $response->version + 1])->save();
            $this->record($case, $response, $seller, 'return_seller_'.$decision, $previous, $decision === 'reject' ? $data['reason'] : 'Seller approved the request. Physical return and admin financial authorization remain separate.');

            return $response;
        });
    }

    public function escalate(Dispute $dispute, User $buyer, int $version, bool $urgent = false): SellerReturnResponse
    {
        return $this->locked($dispute, function ($order, $case, $response) use ($buyer, $version, $urgent) {
            abort_unless($buyer->status === 'approved' && $buyer->role === 'buyer' && $case->buyer_id === $buyer->id, 404);
            $expired = $this->expireLocked($case, $response);
            // A deadline approval is committed even if an old screen requests escalation.
            if ($response->stage === 'deadline_approved' && $expired) {
                return $response;
            }
            if ($response->stage === 'resolved' && $response->seller_decision === 'rejected') {
                return $response;
            }
            $this->assertPending($case, $response, $version);
            abort_unless($response->escalated_at === null && ($response->stage === 'seller_rejected' || ($urgent && in_array($response->stage, ['awaiting_seller', 'seller_approved', 'deadline_approved'], true))), 422, 'This request cannot be escalated again.');
            $previous = $response->only(['stage', 'version']);
            $response->forceFill(['stage' => 'escalated', 'escalated_at' => now(), 'version' => $response->version + 1])->save();
            $case->forceFill(['status' => 'UNDER_REVIEW'])->save();
            $this->record($case, $response, $buyer, 'return_escalated', $previous, $urgent ? 'Buyer requested urgent administrator assistance.' : 'Buyer escalated the seller rejection within 72 elapsed hours.');

            return $response;
        });
    }

    public function expire(Dispute $dispute): bool
    {
        return $this->locked($dispute, fn ($order, $case, $response) => $this->expireLocked($case, $response));
    }

    /** Caller holds order and dispute locks, in that order, through admin resolution. */
    public function adminUpdated(Dispute $case, User $admin): void
    {
        $response = $case->sellerResponse()->lockForUpdate()->first();
        if (! $response) {
            return;
        }
        $previous = $response->only(['stage', 'version']);
        $response->forceFill(['stage' => $case->status === 'UNDER_REVIEW' ? 'escalated' : 'resolved', 'escalated_at' => $case->status === 'UNDER_REVIEW' ? ($response->escalated_at ?? now()) : $response->escalated_at, 'version' => $response->version + 1])->save();
        $this->record($case, $response, $admin, 'return_admin_updated', $previous, $case->admin_notes);
    }

    private function locked(Dispute $dispute, callable $action): mixed
    {
        return DB::transaction(function () use ($dispute, $action) {
            $order = Order::whereKey($dispute->order_id)->lockForUpdate()->firstOrFail();
            $case = Dispute::whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            $response = $case->sellerResponse()->lockForUpdate()->firstOrFail();

            return $action($order, $case, $response);
        }, 3);
    }

    private function assertPending(Dispute $case, SellerReturnResponse $response, int $version): void
    {
        abort_unless($case->status === 'PENDING', 422, 'An administrator or escalation has already taken over this case.');
        abort_unless($version === $response->version, 409, 'The request changed. Refresh before responding.');
    }

    private function expireLocked(Dispute $case, SellerReturnResponse $response): bool
    {
        if ($case->status !== 'PENDING') {
            return false;
        }
        $previous = $response->only(['stage', 'version']);
        if ($response->stage === 'awaiting_seller' && $response->response_due_at->lte(now())) {
            $response->forceFill(['stage' => 'deadline_approved', 'version' => $response->version + 1])->save();
            $this->record($case, $response, null, 'return_deadline_approved', $previous, '72-hour seller response deadline expired. Request approved for admin physical/financial authorization; no refund, stock or custody change.');

            return true;
        }
        if ($response->stage === 'seller_rejected' && $response->escalation_due_at?->lte(now())) {
            $case->forceFill(['status' => 'REJECTED', 'resolved_at' => now(), 'resolved_by' => null, 'admin_notes' => 'Seller rejection closed after the 72-hour buyer escalation window expired.'])->save();
            $response->forceFill(['stage' => 'resolved', 'version' => $response->version + 1])->save();
            $this->record($case, $response, null, 'return_escalation_expired', $previous, 'Buyer escalation window expired; the exact seller reason is retained.');

            return true;
        }

        return false;
    }

    private function record(Dispute $case, SellerReturnResponse $response, ?User $actor, string $action, array $previous, string $reason): void
    {
        $this->audits->record($actor, $response, $action, $previous, $response->only(['stage', 'version', 'seller_decision', 'seller_reason']), $reason);
        foreach ([$case->buyer, $case->seller] as $recipient) {
            $this->notifications->send($recipient, new WebOperationsNotification(['category' => 'orders', 'title' => $response->label(), 'message' => $reason, 'order_id' => $case->order_id, 'url' => route('returns.workflow', $case->order_id)]));
        }
        if (in_array($action, ['return_escalated', 'return_seller_approve', 'return_deadline_approved'], true)) {
            User::whereIn('role', ['admin', 'ecommerce_admin'])->where('status', 'approved')->chunkById(100, function ($admins) use ($case, $response, $reason) {
                $this->notifications->send($admins, new WebOperationsNotification(['category' => 'orders', 'title' => $response->label(), 'message' => $reason, 'order_id' => $case->order_id, 'url' => route('admin.disputes.show', $case)]));
            });
        }
    }
}
