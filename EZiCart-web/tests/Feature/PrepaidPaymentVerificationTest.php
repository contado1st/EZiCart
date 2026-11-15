<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\User;
use App\Services\FinancialLedgerService;
use App\Services\OrderWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PrepaidPaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['financial.sandbox_enabled' => true]);
    }

    public function test_valid_payment_remains_verified_after_proceeds_are_staged_without_creating_accounts_on_read(): void
    {
        [$buyer, $seller, $order, $ledger] = $this->crew();
        app(OrderWalletService::class)->pay($order, $buyer);
        $this->assertSame(9000, $ledger->wallet($seller, 'pending')->balance_cents);
        $this->assertSame(6000, $ledger->escrow($order->id)->balance_cents);
        $this->assertTrue(app(OrderWalletService::class)->paymentReady($order));
        $this->assertDatabaseCount('ledger_transactions', 3);
        $this->assertTrue($ledger->reconcile()['balanced']);
    }

    #[TestWith(['missing_entries'])]
    #[TestWith(['wrong_amount'])]
    #[TestWith(['wrong_buyer_account'])]
    #[TestWith(['wrong_escrow'])]
    #[TestWith(['extra_entry'])]
    #[TestWith(['wrong_order_metadata'])]
    #[TestWith(['wrong_buyer_metadata'])]
    #[TestWith(['wrong_actor'])]
    #[TestWith(['wrong_type'])]
    #[TestWith(['reversed'])]
    public function test_valid_looking_order_fields_do_not_verify_an_incorrect_posting(string $defect): void
    {
        [$buyer, $seller, $order, $ledger] = $this->crew();
        $wallet = $ledger->wallet($buyer);
        $escrow = $ledger->escrow($order->id);
        $metadata = ['order_id' => $order->id, 'buyer_id' => $buyer->id];
        $actor = $buyer;
        if ($defect === 'wrong_buyer_account') {
            $wallet = $ledger->account('synthetic:other-buyer', 'buyer', $seller->id, 'available', true);
        } elseif ($defect === 'wrong_escrow') {
            $escrow = $ledger->account('order:other:escrow', 'order_escrow', null, 'held');
        } elseif ($defect === 'wrong_order_metadata') {
            $metadata['order_id'] = $order->id + 100;
        } elseif ($defect === 'wrong_buyer_metadata') {
            $metadata['buyer_id'] = $seller->id;
        } elseif ($defect === 'wrong_actor') {
            $actor = $seller;
        }
        $type = $defect === 'wrong_type' ? 'synthetic_transfer' : 'order_payment';
        if ($defect === 'missing_entries') {
            $transaction = LedgerTransaction::create(['reference' => Str::uuid(), 'idempotency_key' => 'synthetic:empty', 'payload_hash' => str_repeat('0', 64),
                'type' => $type, 'actor_id' => $actor->id, 'metadata' => $metadata, 'created_at' => now()]);
        } elseif ($defect === 'extra_entry') {
            $transaction = $ledger->post('synthetic:payment', $type, [$wallet->id => -15000, $escrow->id => 14999, $ledger->treasury()->id => 1], $actor, $metadata);
        } else {
            $transaction = $ledger->transfer('synthetic:payment', $type, $wallet, $escrow, $defect === 'wrong_amount' ? 14999 : 15000, $actor, $metadata);
        }
        $order->forceFill(['wallet_transaction_id' => $transaction->id, 'wallet_paid_cents' => 15000])->save();
        if ($defect === 'reversed') {
            $ledger->reverse($transaction, $buyer, 'Synthetic payment reversal');
        }
        $before = [LedgerTransaction::count(), LedgerEntry::count(), $ledger->wallet($buyer)->balance_cents];
        $this->assertFalse(app(OrderWalletService::class)->paymentReady($order));
        $this->assertSame($before, [LedgerTransaction::count(), LedgerEntry::count(), $ledger->wallet($buyer)->balance_cents]);
        $this->assertTrue($ledger->reconcile()['balanced']);
    }

    #[TestWith(['frozen_wallet'])]
    #[TestWith(['frozen_escrow'])]
    #[TestWith(['wallet_currency'])]
    #[TestWith(['escrow_currency'])]
    #[TestWith(['wallet_owner'])]
    #[TestWith(['escrow_owner'])]
    #[TestWith(['wallet_bucket'])]
    #[TestWith(['escrow_bucket'])]
    #[TestWith(['escrow_type'])]
    public function test_an_unavailable_or_misidentified_account_holds_a_previously_paid_order(string $defect): void
    {
        [$buyer, $seller, $order, $ledger] = $this->crew();
        app(OrderWalletService::class)->pay($order, $buyer);
        $wallet = $ledger->wallet($buyer);
        $escrow = $ledger->escrow($order->id);
        [$account, $change] = match ($defect) {
            'frozen_wallet' => [$wallet, ['frozen' => true]], 'frozen_escrow' => [$escrow, ['frozen' => true]],
            'wallet_currency' => [$wallet, ['currency' => 'USD']], 'escrow_currency' => [$escrow, ['currency' => 'USD']],
            'wallet_owner' => [$wallet, ['user_id' => $seller->id]], 'escrow_owner' => [$escrow, ['user_id' => $buyer->id]],
            'wallet_bucket' => [$wallet, ['bucket' => 'held']], 'escrow_bucket' => [$escrow, ['bucket' => 'available']],
            'escrow_type' => [$escrow, ['type' => 'buyer']],
        };
        $account->update($change);
        $this->assertFalse(app(OrderWalletService::class)->paymentReady($order));
        $this->assertDatabaseCount('ledger_transactions', 3);
    }

    public function test_seller_cannot_confirm_an_order_whose_posted_payment_amount_is_wrong(): void
    {
        [$buyer, $seller, $order, $ledger] = $this->crew();
        $transaction = $ledger->transfer('synthetic:short-payment', 'order_payment', $ledger->wallet($buyer), $ledger->escrow($order->id), 14999, $buyer,
            ['order_id' => $order->id, 'buyer_id' => $buyer->id]);
        $order->forceFill(['wallet_transaction_id' => $transaction->id, 'wallet_paid_cents' => 15000])->save();
        $this->actingAs($seller)->patch(route('seller.orders.updateStatus', $order), ['status' => 'CONFIRMED'])
            ->assertRedirect(route('seller.orders.show', $order))->assertSessionHasErrors('status');
        $this->assertSame('PLACED', $order->fresh()->status);
        $this->assertDatabaseCount('parcel_tracking_events', 0);
        $this->assertDatabaseCount('ledger_transactions', 2);
    }

    private function crew(): array
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $order = Order::forceCreate(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'order_number' => 'VERIFY-'.Str::upper(Str::random(10)),
            'status' => 'PLACED', 'subtotal' => 100, 'shipping_fee' => 50, 'commission_fee' => 10, 'total_amount' => 150, 'payment_method' => 'WALLET', 'financial_version' => 1,
            'recipient_name' => 'Synthetic buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'street_address' => 'Test street']);
        $ledger = app(FinancialLedgerService::class);
        $ledger->transfer('synthetic:fund', 'synthetic_funding', $ledger->account('synthetic:cash', 'cash_control', null, 'available', true), $ledger->wallet($buyer), 50000, null);

        return [$buyer, $seller, $order, $ledger];
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.test', 'password' => 'Password123!', 'first_name' => 'Synthetic', 'last_name' => 'Tester',
            'role' => $role, 'status' => 'approved', 'email_verified_at' => now(), 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro']);
    }
}
