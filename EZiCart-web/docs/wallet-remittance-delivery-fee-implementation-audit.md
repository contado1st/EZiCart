# Wallet, remittance and delivery-fee implementation audit

Date: 8 November 2026. Scope: Laravel financial and operational functionality, with small forms/navigation links in the existing interface. The user's approved choice is **configurable rates with explicitly fictional sandbox examples**, because no approved compensation schedule or operating-cost records were supplied.

Related documents: [research and official sources](wallet-remittance-shipping-research.md), [implementation plan prepared before coding](wallet-remittance-shipping-implementation-plan.md), and [operations guide](web-operations-user-guide.md).

## A. Implementation summary

| Area | Implemented behavior |
| --- | --- |
| Ledger | Integer-cent, balanced account postings; idempotency/content hashes; deterministic account locks; reversals without overwriting original entries; balance reconciliation. |
| Wallet | Buyer and seller mock cash-in, full-wallet purchases, cancellation/refund credits, pending seller proceeds, eligible seller release, staff earnings, reserved withdrawals and administrator review. |
| Treasury | One shared platform funding account; platform promotions and approved shipping adjustments use treasury funds rather than a particular administrator's personal balance. |
| Promotions | New seller/platform offers reserve their maximum bounded liability before activation. Actual redemption is funded once; unused funds are released on pause, expiration or exhaustion. |
| COD | Existing collection and individual remittance remain. New batches preserve expected, declared and physically received cash, private evidence, personnel, discrepancies and investigation history. |
| Delivery | Independently approved, effective-dated route/weight tariffs; actual versus volumetric weight; immutable order quotes; evidenced hub measurements; approved additional platform funding. |
| Allocation | Verified pickup, transfer-driver, delivery and hub work receive configured amounts. Operating allocations, unassigned work claims, seller proceeds, merchandise commission and logistics margin remain separate. |
| Reporting | Owner/domain-scoped wallet, remittance and shipment pages and CSV statements, reconciliation command and hourly expired-budget release. |
| Integration | COD and verified WALLET orders use existing custody, capacity, scan, proof, delivery-code, completion, dispute and payout controls. Single-shop checkout remains mandatory. |

No dependency was added. Existing dashboard layouts and themes were retained. Dashboard-tab and icon repairs already present in the working tree are separate earlier work, not financial redesign changes.

## B. Wallet and ledger

### Account ownership and funds

- `user:{id}:available`: spendable buyer/seller/staff funds.
- `user:{id}:pending`: seller proceeds awaiting existing completion, funding, dispute and payout checks.
- `user:{id}:held`: withdrawal reservations.
- `voucher:{id}:reserve`: seller/platform promotional commitments; unavailable for ordinary spending.
- `order:{id}:escrow`: order payment and sponsor funding until redistribution/refund.
- `platform:treasury`: shared platform promotional/adjustment funds.
- `platform:logistics-operations`, `platform:logistics-margin`, `platform:unallocated-logistics`: distinct operating allocation, margin and unresolved work accounts.
- Explicit clearing/control accounts represent signed mock funding or externally verified cash movements. Their permitted negative balance is an accounting counterpart, not a customer overdraft.

Every posted transaction has a UUID, unique idempotency key, payload hash, actor, type, metadata and at least two entries whose signed amounts sum to zero. Every entry records the prior and resulting balance. Customer accounts cannot overdraw. Application model guards prohibit editing/deleting posted entries and transactions; a database administrator can still bypass application guards, so production database access controls and backups remain necessary.

### Funding and payments

1. An approved buyer/seller requests their own mock funding intent; an e-commerce administrator requests shared treasury funding.
2. The intent has a PHP amount, owner, account, expiry, provider and unique request key. A browser success message cannot credit it.
3. The sandbox callback verifies raw-body HMAC, timestamp tolerance, intent reference, currency, amount, event identity and provider reference.
4. A successful event posts once. Failure credits nothing. Reversal creates a reversing transaction when funds remain available.
5. A reversal after spending freezes the available account and marks the intent `UNDER_INVESTIGATION`. It does not fabricate recovered cash or create a negative customer balance. Recovery/unfreeze requires a future authorized operational workflow.
6. Full-wallet checkout recalculates server totals, then debits the buyer atomically with stock reservation, order creation and voucher redemption. Failed checkout rolls everything back.

No partial wallet/COD split is provided. Zero-total wallet purchases are rejected with an instruction to use the existing zero-due COD path. Verified wallet delivery never asks the rider to collect COD again; proof and the buyer delivery code still apply.

### Earnings, refunds and withdrawals

Funded proceeds move into seller **pending**, not available. Existing seven-day delivery hold, completion, disputes, verified funds, subsidy funding and payout approval remain prerequisites. Eligible payouts can release to the seller wallet or follow the existing externally referenced settlement path. A unique posting/payout state prevents double release.

Authorized wallet refunds require the existing dispute/refund approval and confirmed return conditions. Buyer payment is returned to the buyer wallet; sponsor funds return to the proper promotion/source. Post-delivery refunds retain campaign usage history. Existing external COD refunds receive matching ledger movements for new financial-version orders.

Withdrawal requests immediately move available funds to held. Appropriate e-commerce or logistics administrators approve/reject by owner role; self-review is prohibited. Rejection releases the reservation. Mock settlement consumes held funds and records the execution reference and actor. **No bank or e-wallet disbursement actually occurs.**

## C. Voucher funding

New offers require a finite `usage_limit`; percentage offers also require a maximum discount. The per-redemption cap multiplied by the usage limit determines the reservation. A PHP 50 cap for 100 uses therefore requires PHP 5,000. With PHP 2,000 available, creation is blocked and reports PHP 3,000 additional funds required. Creation and reservation share one transaction, so a failed reservation leaves no active unfunded campaign.

Seller campaigns debit the seller's available account; platform campaigns debit shared treasury. Discounts can target merchandise or shipping. Reserved funds move into the relevant order escrow at actual redemption, once per order. Cancellation restores the redemption and relevant limits; pausing, expiration and exhausted campaigns release unused funds. Reactivation revalidates and reserves funds again.

For an already prefunded seller item discount, seller payable adds that financing back once; it is not deducted a second time from settlement. Platform discounts never debit seller promotional funds. Historical vouchers without budgets remain visibly identified as legacy/unreserved; existing records were not silently charged or assigned fabricated opening money. Unbounded historical campaigns need a new bounded offer before funded activation.

## D. COD remittance and reconciliation

COD delivery records exact collection only after proof and delivery-code verification. Collected cash is custody liability, not rider earnings or seller available balance.

An approved rider submits up to 100 unique unsubmitted COD collections belonging to one receiving hub. Expected cash comes from stored collections. Declared submission, evidence and reason are stored separately. Individual submission and batch membership are mutually exclusive.

The assigned hub or logistics administrator independently counts cash and uploads private receipt evidence. Self-verification is blocked. Exact declared and received amounts verify the underlying collections. A shortage or excess remains `DISCREPANCY`; a logistics administrator opens `UNDER_INVESTIGATION` and records an evidenced exact cumulative receipt to resolve it. Original submitted values and previous receipt/count details remain in append-only application events and administrative logs. There is no automatic wage deduction or write-off button.

Implemented batch states are `SUBMITTED`, `DISCREPANCY`, `UNDER_INVESTIGATION`, `VERIFIED`. Earlier cash states remain on `OrderFinance`. Seller payout settlement is separate from batch verification; the batch is not relabeled as settled when unrelated payouts complete.

Riders see their collections and batches; hubs see incoming cash for their hub; logistics administrators see cross-hub monitoring and investigations. E-commerce administrators retain their existing seller/refund scope. Evidence is private and authorization is checked on downloads.

## E. Delivery calculation and tariff management

Until an operator explicitly activates complete approved coverage, the existing flat shipping setting remains. Its shipping funds are held as unallocated at settlement rather than distributing guessed rider percentages.

Versioned pricing supports same barangay, same municipality, inter-municipality, inter-province, and explicit long-distance destination zones. Long-distance rules specify `destination_zones` with `province` and optional `municipality`/`origin_province`. Remote municipality names are normalized for matching. These are configured zones, not a geodesic distance calculator.

Tariff drafts contain weight brackets, component amounts in cents, volumetric divisor, effective date and sandbox flag. A different approved logistics administrator must approve the draft. Approved versions are immutable; a new version affects future quotes. Activation requires effective approved coverage for all four ordinary route classes.

For millimetre dimensions and a conventional cubic-centimetre/kg divisor:

```text
Volumetric grams = ceil(length_mm * width_mm * height_mm / divisor)
Chargeable grams = max(actual grams, volumetric grams)
Quoted shipping = pickup + origin hub + linehaul + destination hub
                + last mile + applicable COD/remote/handling/insurance
                + overhead + planned margin
```

Return-to-sender is a contingent component, excluded from a successful-delivery quote. COD fees are removed for wallet shipments. Product dimensions are optional packing estimates; combined quantity uses maximum length/width and stacked heights, not a packing optimizer. Quotes clearly retain estimated-dimension/destination flags. Final versioned checkout requires the destination and product weights. Measurements and size/weight ceilings fail closed when unsupported.

A hub with physical custody can propose one evidenced measurement correction against the order's original tariff. Independent e-commerce treasury review funds a positive difference. The original buyer charge and quote remain unchanged and the involved buyer/seller are notified. Lower measured cost does not automatically create a buyer rebate. Additional actual transport legs can require an evidenced operating-funding request; insufficient treasury funds block approval/settlement. The system never silently bills the buyer extra.

## F. Distribution and profitability

```text
Original shipping funding = buyer net shipping
                          + seller-funded shipping voucher
                          + platform-funded shipping voucher
Total available funding = original funding + approved additional contribution
Logistics cost = evidenced beneficiary allocations + configured operating allocations
Held claims = configured work amounts without verified beneficiaries
Net logistics margin = funding - logistics cost - held claims
Seller proceeds = merchandise - seller item discount - commission
                  + already prefunded seller item discount
```

Pickup is attributed to the recorded pickup rider. Each received hub transfer pays its assigned transport driver. Delivered proof identifies the last-mile rider. Intake events/received transfers identify origin and destination hubs. Unverified work amounts are held rather than counted as profit. Operating allocations are retained in a separate control account; they are **not proof that fuel, insurance or other invoices have been externally paid**.

Each settled shipment retains costs, beneficiaries, legs, held claims, net margin and percentages derived from that shipment's funding. These are outputs of approved peso rates, not universal percentages attributed to Shopee, Lazada or a courier.

For an executed full refund after return, original buyer shipping funding is excluded from logistics compensation funding. Confirmed return work requires an independently approved, treasury-funded return-cost proposal; unused return funding goes back to treasury and does not become margin. Buyer refund remains unchanged.

The sandbox command's first-bracket **fictional inter-municipality COD example** totals PHP 103: pickup 20, origin hub 8, linehaul 25, destination hub 8, last mile 25, COD allocation 4, overhead 8, planned margin 5. The corresponding prepaid example removes COD and totals PHP 99. These values are training fixtures only, not wage advice, recommended commercial prices or external courier contracts. A PHP 20 return component is contingent.

## G. Database and relationships

Applied additive migration: `2026_11_08_024745_create_financial_ledger_and_funding_controls.php`. Historical migrations were retained. The complete schema was first exercised in isolated SQLite, then this pending migration was applied to the configured MySQL database. No reset, fresh, truncate or destructive seed command was run.

| New tables | Relationships and important constraints |
| --- | --- |
| `financial_accounts` | Unique account key, optional owner, bucket/currency, balance/freeze controls. |
| `ledger_transactions`, `ledger_entries` | Unique UUID/idempotency key; unique reversal target; entries reference transaction/account with one entry per account/transaction. |
| `wallet_funding_intents`, `financial_provider_events` | Owner/account/transaction linkage; per-owner request key, unique provider event/reference and raw payload hash. |
| `wallet_withdrawals` | Owner, encrypted destination, per-owner request key, approver/executor and settlement reference. |
| `voucher_budgets`, `voucher_redemptions` | One budget per voucher; source/reserve accounts; one redemption per order. |
| `remittance_batches`, `remittance_batch_items`, `remittance_batch_events` | Rider/hub/actor linkage; unique finance-row membership; original expected snapshot and event history. |
| `delivery_rate_versions` | Creator/checker, route/effective-date status and JSON integer-cent bracket rules. |
| `shipment_financials`, `shipment_price_adjustments` | One original snapshot per order; original tariff/funding, evidenced correction and approval linkage. |
| `logistics_allocations` | Unique allocation key with order, component, beneficiary, leg reference in ledger metadata and transaction. |

Modified tables: `orders` (financial version, wallet posting/payment and sponsor shipping/item funding); `order_finances` (ledger staging timestamp); `seller_payouts` (destination/posting); `vouchers` (discount target); `products` (optional length/width/height in mm). Existing records default to historical financial version 0. Existing `commerce_settings.value` is an integer, so activation/sandbox settings use integer flags, not strings containing JSON or model names.

## H. File manifest

| Files | Purpose |
| --- | --- |
| `app/Services/FinancialLedgerService.php` | Balanced posting, locking, idempotency, reversal and reconciliation. |
| `WalletFundingService.php`, `WalletWithdrawalService.php` | Signed mock events, holds, role-domain approval and sandbox execution. |
| `VoucherFundingService.php` | Campaign maximum exposure, reserve/consume/restore/release. |
| `OrderWalletService.php`, extended `OrderFinancialService.php` | Integrate payment, pending earnings, cancellation/refund and settlement with existing finance records. |
| `DeliveryPricingService.php`, `DeliveryRateManagementService.php` | Configurable route/weight/dimension calculation and independent rate approval. |
| `RemittanceBatchService.php` | Cash batches, private evidence, discrepancies and investigation. |
| `LogisticsSettlementService.php`, `ShipmentAdjustmentService.php` | Work-based compensation, separate control accounts and evidenced treasury-funded corrections/returns. |
| `PlacePurchaseService.php`, `CheckoutPricingService.php`, `WebCheckoutService.php` | Atomic checkout, sponsor allocation, stable financial snapshots and optional wallet tender. |
| `HubOrderScope.php` | Origin hub authorizes READY_FOR_PICKUP work before rider assignment, preserving destination routing for delivery. A full inter-hub prepaid test exposed this earlier scope gap. |
| `OrderTransitionService.php`, fulfillment controllers, `WorkflowAttentionService.php`, `OrderProgressService.php` | Verified wallet shipments pass the existing operational flow without false COD requests. |
| `FinancialOperationsController.php` | Wallet, statement, remittance, tariff, shipping correction and settlement endpoints. |
| Seller/platform voucher controllers | Transactional funding and required campaign limits before activation. |
| Product controller and weight fields | Optional dimensions, validation, persistence, change audit and compliance re-review. |
| New account/ledger/funding/withdrawal/budget/remittance/tariff/shipment models | Casts and relationships; immutable financial history guards. Existing Order/OrderFinance/Voucher/Product models extended. |
| `routes/web.php`, `routes/api.php`, `routes/console.php` | Protected finance actions, signed sandbox webhook and hourly expired-budget release. |
| `config/financial.php`, `.env.example` | Fail-closed sandbox/provider settings. Actual `.env` secrets were not changed or exposed. |
| `app/Console/Commands/{ManageSandboxFinance,ReconcileFinancialLedger,ReleaseExpiredVoucherFunds}.php` | Explicit demo setup, reconciliation and unused-budget release. |
| `resources/views/finance/{wallet,remittances,rates,shipments,index}.blade.php` | Minimal operational forms, scoped records and links using existing components. |
| `components/finance-navigation.blade.php`, `voucher-funding-summary.blade.php`, workspace sidebar | Expose financial actions and funding status without redesign. |
| Existing buyer/seller/courier order, checkout, earnings and voucher views | Correct paid-wallet/no-COD messages and financial actions. |
| Flutter `cart_provider.dart`, `checkout_page.dart` | Preserve checkout token and re-quote destination before COD submission; existing API routes/response keys retained. |
| `tests/Feature/FinancialLedgerWorkflowTest.php` | Ledger, funding, voucher, checkout, withdrawal, remittance, pricing, evidence and end-to-end prepaid/return checks. |
| `scripts/check-financial-concurrency.php` | Two real worker processes competing to spend from an isolated synthetic SQLite account. |
| `scripts/check-financial-mysql.php` | Explicit local-only rollback smoke check against configured MySQL; no committed synthetic users/funds. |

## I. Security and verification

Financial actions validate approved role, ownership and relevant administrator domain in services and protected routes. No browser endpoint accepts arbitrary ledger deltas or account IDs. Account locks and unique constraints protect spending/duplicates; request changes under a reused reference are rejected. Evidence stays on the private disk. Administrative actions record actors/reasons, and withdrawal destinations are encrypted.

Verification results are recorded below after final regression checks. Isolated tests use synthetic accounts, fake notifications/private storage and non-production payments. The MySQL rollback smoke leaves no synthetic users, orders or money postings committed. Concurrent spending is verified on isolated file SQLite; production MySQL contention/load testing remains separate.

| Check | Actual result |
| --- | --- |
| Complete Laravel suite | **380 passed, 3,530 assertions**, 96.364 seconds. Test process used bcrypt with the existing four-round test configuration; application `.env` hashing settings were unchanged. |
| Focused financial workflow suite | **19 passed, 204 assertions** after the last payment-label correction. Includes prepaid HTTP pickup, inter-hub transfer, destination delivery, seven-day payout hold, settlement and refunded-return funding. The final label check confirms prepaid records do not imply that COD cash is collected. |
| Existing frontend tests | **57 passed, 0 failed**. Initial sandbox process-spawn restrictions were resolved through the required escalation; the actual tests then passed. |
| Production assets | **`npm run build` passed** after the same approved process-spawn escalation. No package/dependency changes. |
| PHP source syntax | **346 source files passed** `php -l`. |
| Compiled views | **152 compiled Blade files passed** PHP syntax checks. The last payment-label change was also rendered successfully by the focused HTTP test. |
| PHP style | `pint --dirty` completed successfully; formatter corrections were applied. |
| Financial route registration | **27 finance-path routes** registered successfully; wallet routes were also exercised by HTTP tests. |
| Concurrent wallet spending | Two worker processes each attempted PHP 75 from PHP 100; exactly one posted, the other rejected insufficient funds, PHP 25 remained, and reconciliation balanced. Isolated file SQLite only. |
| Configured MySQL smoke | Signed mock funding, funded voucher, wallet checkout, cancellation, restored stock and ledger reconciliation passed within an outer rollback transaction; **zero synthetic users remained**. |
| Configured database reconciliation / migration status | `finance:reconcile` returned **balanced**, with no mismatched accounts/transactions; the financial migration is **Ran**. |
| External delivery / native mobile / visual browser | Real bank/provider/SMTP delivery, a Flutter build/analyzer run and authenticated visual browser review were **not verified**. Flutter/Dart SDK was unavailable in this workspace environment. |

The first broad run identified three old expectations that no longer matched the requested behavior: unfunded/unbounded voucher fixtures and the formerly unavailable rider earnings page. The fixtures now fund bounded offers and assert the new earnings page, preserving the original ownership/audit checks. A private-file conflict test also failed during overlapping test processes; it passed in the final isolated sequential suite. No production control was weakened to make these tests pass.

## J. Limitations and deployment requirements

1. **No real-money provider is implemented.** Cash-in/cash-out and wallet payment are sandbox-only, explicitly enabled in `local`/`testing`. Production mock operation fails closed. This is not an approved e-money issuer or a claim of BSP compliance.
2. Provider onboarding, KYC/AML, safeguarding, reconciliation with bank/provider statements, tax/employment treatment and regulatory/legal review remain prerequisites for live funds. No provider credentials were requested or inserted.
3. No approved costs were supplied. Sandbox tariffs are fictional and require a different logistics administrator to approve before explicit activation. The legacy flat setting remains until activation. No fictional opening balances were seeded.
4. Spent-provider-reversal recovery/unfreeze, arbitrary shortage write-offs and release of historically unallocated shipping claims need a future separately approved recovery/correction workflow. They are deliberately held rather than guessed.
5. Legacy orders/vouchers and existing no-charge replacement orders preserve their previous contracts. Automatic ledger-funded replacement shipping is not added. New standard purchases are financial version 1; historical data was not retroactively funded or assigned compensation.
6. There is no hybrid tender, multi-shop checkout, automatic lower-measurement buyer rebate, route-distance engine, package-packing optimizer or real invoice payment connector. Fixed operating allocations are a configured cost model, not audited actual expenses.
7. New financial statements are CSV. Existing analytics/report export features are retained; this work does not add financial XLSX/PDF templates or claim chart printing for the new ledger statements.
8. Flutter receives the compatibility quote/token update, not native wallet screens. Flutter build/analyzer status is recorded with verification; HTTP/API tests cannot substitute for a device build.
9. Scheduled campaign release requires the Laravel scheduler; production email/notification delivery still requires the existing worker configuration. Tests fake external delivery and do not certify SMTP/provider arrival.
10. Application immutability does not replace restricted database credentials, backups, operational monitoring, production concurrency/load tests and independent accounting review. Browser visual review is not claimed without an authenticated working browser.

## Sandbox operator walkthrough

1. Use the existing approved account IDs for an e-commerce administrator, **two different** logistics administrators, buyer, seller, pickup/delivery riders, hubs and transfer driver. Do not recreate or reset seeded accounts. Tariff maker/checker separation requires two logistics administrators.
2. Apply pending additive migrations with `php artisan migrate`. This workspace's financial migration is already applied.
3. In local development, run `php artisan finance:sandbox enable --actor=ECOMMERCE_ADMIN_ID`. This enables mock operations only and creates no money. Alternatively set `FINANCIAL_MODE=sandbox`, `FINANCIAL_SANDBOX_ENABLED=true` in your local configuration; do not expose secrets or enable live behavior based on these flags.
4. Buyer/seller open **Wallet and balances** and create a mock funding intent. The e-commerce administrator opens **Platform treasury** and selects the intent's simulated paid event. Treasury funding is requested from that same administrator page and credits the shared account. These are not actual deposits. The CLI equivalent is `php artisan finance:sandbox event --actor=ECOMMERCE_ADMIN_ID --intent=INTENT_UUID --result=paid`.
5. For fictional tariffs, run `php artisan finance:sandbox tariffs --actor=LOGISTICS_MAKER_ID`. Review drafts under **Delivery tariffs**. The other logistics administrator approves each needed effective version, then explicitly activates versioned pricing. Replace examples with independently approved actual cost schedules before any commercial use. Long-distance zones use the optional rules JSON.
6. Seller/platform create a bounded voucher. Observe the required budget, available funds and shortfall warning. A successful creation moves funds into reserved promotions. Pause/expiry returns only unused funds.
7. Buyer selects products from one shop, confirms the destination, selects COD or Wallet, reviews recalculated totals and places the order. A wallet purchase debits once; COD remains uncollected until delivery. The existing seller preparation, rider pickup, hub scan/transfer, dispatch and delivery-proof/code steps remain.
8. For COD, the delivery rider submits eligible collections in **Cash remittance batches** with evidence. The receiving hub counts cash and records the receipt. Exact cash verifies; mismatch goes through logistics-admin investigation and evidenced reconciliation. Never enter a fictitious exact amount to clear a shortage.
9. Check **Shipping breakdowns**. A hub can submit a measured-weight correction with evidence. Treasury independently approves/funds additional cost; the buyer's original payment remains unchanged. A logistics administrator can request additional operational/return funding when verified work would exceed funded shipping.
10. After completion and the seven-day hold, seller requests payout through existing financial records. The e-commerce administrator approves, then releases eligible proceeds to wallet or records the existing external settlement. Logistics work allocations post once at settlement. Returned/refunded shipments require the separate approved return-cost funding and settlement action.
11. Eligible users request withdrawal. The responsible administrator approves/rejects; sandbox execution records a test reference and never sends real money. Use transaction, cash and shipping CSV statements to inspect the result.
12. Run `php artisan finance:reconcile`. Expected result is `balanced: true` and empty mismatch lists. `php artisan finance:release-expired-vouchers` can be run manually; it is also scheduled hourly. Configure the usual scheduler/queue workers for operation.

`php scripts/check-financial-concurrency.php` builds/removes its own isolated SQLite file. `php scripts/check-financial-mysql.php --rollback-only` performs only a local/testing rollback verification on configured MySQL. Neither is a production payment gateway or a substitute for a production load test.
