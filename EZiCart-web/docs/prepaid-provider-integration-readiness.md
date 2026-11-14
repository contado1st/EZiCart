# Prepaid payment readiness and scope decision

**Scope changed by the user on 14 November 2026:** complete the last six revisions using an internal school/demo wallet, including demo credits and recorded demo cash deposits approved by administrators. Follow the [internal demo-wallet plan](internal-demo-wallet-implementation-plan.md) and the latest [implementation audit](partial-features-implementation-audit.md). An external provider account is no longer required for this revised scope. External card/e-wallet processing remains an explicit difference from the original teacher expectation; demo credits are not real cash.

## Earlier provider-only plan (superseded for the chosen demo scope)

Reviewed 14 November 2026. Scope: Laravel web. This is an execution plan and readiness record, not a completed gateway integration or teacher acceptance result.

## Current boundary

The approved programme has 48 delivered case revisions and six incomplete cases. COD and the existing custom-wallet sandbox remain supported. `WalletFundingService` generates mock funding under local/testing controls; its callback cannot establish a payment at an external provider.

Provider selection and sandbox account availability were requested again on 14 November. No answer or provider access was available when this record was written. PayMongo is a candidate, not an activated or selected connector. Its [Hosted Checkout quick start](https://docs.paymongo.com/docs/payment-channels-hosted-checkout-quick-start) requires an account with completed KYC and a test secret key. Its [webhook documentation](https://docs.paymongo.com/docs/developer-tools-webhooks-key-concepts) distinguishes test/live endpoints and describes signed, retried events. These sources support the access and delivery requirements; they do not establish that this application's merchant account or callback works.

Keep credentials in the server's secret configuration, outside source control and chat. No real payment, provider-account creation, KYC submission or live activation is authorized by this sandbox work.

## Shared fix delivered before connector work

`OrderWalletService::paymentReady` previously accepted an `order_payment` transaction with matching order metadata and an unfrozen buyer-wallet record, without validating the actual posting. It now requires:

- A positive recorded amount equal to the server-owned order total.
- Matching transaction type, order, buyer and actor; no linked reversal.
- Existing PHP accounts: the buyer's available wallet and the order's held escrow, with correct ownership and no freeze.
- Exactly two original entries: the full buyer debit and matching order-escrow credit.

Verification does not create accounts or repair financial records. It checks original entries rather than today's escrow balance because seller proceeds can already be staged. A malformed payment prevents the existing transition service from confirming or fulfilling the order. Account/payment exceptions require reconciliation, not a cash request or automatic re-payment.

`PrepaidPaymentVerificationTest` covers genuine staged funds, missing entries, short payment, wrong source/destination, extra entries, incorrect metadata/actor/type, a reversed payment, held/misidentified accounts and rejection through the seller's confirmation endpoint. Existing financial, voucher, delivery and RTS suites cover supported custom-wallet behavior. They do not prove external-provider behavior.

## Ordered implementation after the provider/access decision

1. **Disabled test connector and readiness checks.** Use the installed Laravel HTTP client; do not add a payment SDK without dependency approval. Separate the provider's keys/signature format from the mock wallet. Enforce test keys, test responses and PHP currency. Review exact checkout/refund resources and account-enabled methods against current primary documentation before implementation. An unavailable connector must leave COD/custom wallet usable.
2. **Durable reservation and attempt records.** Expand schema additively, with a recorded backup/restore procedure, copy dry-run and rollback/lock assessment. Associate attempts with the buyer, existing purchase/order, immutable quoted amount/currency and unique operation references. Preserve single-shop checkout, selected cart items, Buy Now isolation, saved-address ownership and tab-session binding. Record stock/voucher reservations exactly once through existing purchase services. Persist network-operation intent before sending requests; resolve ambiguous outcomes before creating another session.
3. **Unpaid order boundary.** Preserve historical order-status strings. Separate payment state from fulfillment state. Pending payment belongs in a genuine mutually exclusive To Pay group; it must not start seller response deadlines, send a paid-order preparation notification or permit pickup/dispatch. A payment-method label or browser return cannot mark funds paid. Keep current COD and verified custom-wallet grouping unchanged.
4. **Verified payment and reconciliation.** Validate signature, mode, provider event/session/payment identity, buyer/order binding, exact cents and currency. Match against server-retrieved provider state where needed. Use durable unique event/payment references and transaction locks so duplicate/out-of-order callbacks cannot spend a voucher, reduce stock, fund escrow, stage seller proceeds or send a paid notification twice. Network calls must not hold database row locks. Never treat provider funds as a mock wallet top-up.
5. **Cancel, expiry and retry.** Align reservation deadlines with the selected provider's session lifecycle. A browser cancel is not proof that the provider can no longer charge. Confirm expiry/cancellation or reconcile an uncertain outcome before releasing a reservation. Restore stock and voucher/funding once through current services; preserve scheduled/expired voucher rules and cancellation approvals. A payment arriving after cancellation must not resurrect fulfillment: record a reconciliation exception and initiate the supported compensating original-tender refund. A new checkout attempt must not double-reserve the original order.
6. **Original-tender refunds.** Preserve independent RTS refund authorization and execution, physical seller receipt and inspection, and settled-fund recovery controls. Record durable refund-request intent and provider references before asynchronous confirmation. Requested/pending/failed/succeeded are distinct; do not tell a buyer money has arrived merely because a refund was requested. Never both credit the custom wallet and refund the provider for the same money. Release/reverse seller proceeds using existing financial services exactly once, with a reconcilable provider control account.
7. **User experience and operations.** Add owned To Pay/resume/cancel/status screens with existing Blade styles, visible payment holds and truthful next-action messages. Prepaid riders collect PHP 0.00 while retaining proof/code/recipient requirements. Admins need reconciliation and retry guidance without a generic mark-paid override. Inspect narrow/mobile, tablet and desktop layouts, keyboard behavior and separate-account tabs once the browser host is available.

## Completion evidence for each remaining case

| Case | Required external-provider evidence in addition to current wallet tests |
| --- | --- |
| SEL-ORD-005 | Seller cancels a verified prepaid order for a reason; one original-tender full refund starts, stock/voucher reservations restore once, and seller available earnings do not increase. |
| BUY-PAY-002 | Real test-mode hosted checkout succeeds; verified provider reference and exact amount persist; fulfillment starts once after verification. Failed/abandoned/replayed returns cannot mark paid. |
| BUY-TRK-001 | Genuine reserved unpaid orders appear only in To Pay; successful payment moves them to Preparing to ship; cancellation/refund/receipt/completion counts stay mutually exclusive and owned. |
| BUY-CAN-003 | Approved buyer cancellation of a paid voucher order starts the correct full refund, restores eligible voucher entitlement once and keeps expired/ended offers unusable. |
| RID-DLV-006 | A verified provider-paid parcel completes with code/photo/recipient proof, zero COD collection, no collector/cash balance change and no duplicate financial posting. |
| LOG-RTS-004 | Undelivered prepaid RTS follows physical return/receipt and authorized full-refund execution; original-tender refund status is reconciled and seller proceeds remain unavailable. |

Across the six, test invalid signatures, wrong mode/currency/amount, unknown or reused references, concurrent/duplicate/out-of-order callbacks, timeouts, expiry/retry, late success after reservation release, refund failure/replay and reconciliation. HTTP fakes provide contract/failure-path coverage; a real provider-issued test checkout and signed event are separate required evidence. SQLite tests do not establish MySQL payment races. Teacher workbook execution and browser/device acceptance remain separately recorded.

## Rollout and rollback

Do not expose the new payment option until configuration, callback reachability, schema and provider tests pass. No live activation or rate change is included. Keep historical attempts, events, refunds and ledger entries after disabling the connector. For a partial deployment, pause new provider checkouts while reconciliation/refund processing remains available; do not drop financial history or restore code that can release reservations incorrectly. Follow the parent integration plan's migration gates before any schema deployment.
