# Financial implementation plan

Prepared before financial application changes on 8 November 2026.

## Existing architecture assessment

Reuse Purchase/PlacePurchaseService for transactional stock, voucher and checkout idempotency. Reuse CheckoutPricingService for authoritative totals; OrderFinance for snapshots and COD collection; SellerPayout/RefundExecution for approval/execution; AdministrativeAuditService and private evidence storage for traceability. Existing payout eligibility requires completion, a seven-day delivery hold, verified funds, funded subsidies and no blocking refund/dispute.

Missing: ledger-backed balances, funding intents, funded promotion reservations, batch handover, cost-based tariffs, logistics payable allocations and wallet payment fulfillment. Existing checkout/fulfillment explicitly blocks every non-COD payment; update these guards only for an actually funded wallet purchase. Preserve the single-shop checkout rule and existing module/tab-session work. Do not alter historical migrations or existing dashboard designs.

## Architecture and sequence

1. Add forward-only-compatible tables for financial accounts, immutable balanced transactions/entries, sandbox funding intents/events, withdrawals, voucher budgets/redemptions, remittance batches/items/events, approved delivery rate versions, shipment snapshots/adjustments and logistics allocations. Add nullable integration fields to existing order/voucher/finance/payout records. Historical records remain distinguishable.
2. Build an integer-cent ledger with unique idempotency keys and content hashes, row locks in deterministic order, nonnegative customer accounts and reconciliation. Separate available, pending, reserved and held ownership. Reversals are new transactions; completed entries are never edited/deleted.
3. Implement a signed sandbox payment adapter and stateful funding intents. Never accept browser success as payment. No real provider credentials or real-money disbursement is assumed. Expose sandbox-only operations with explicit labels and access checks.
4. Fund new vouchers from seller available funds or the shared platform treasury. Require a bounded usage count and, for percentage vouchers, a maximum discount. Reserve, consume, restore/release and expire funds transactionally. Keep historical unbudgeted vouchers explicit; never silently backfill cash or retrospectively charge their owners.
5. Integrate full-wallet checkout alongside COD. Recalculate server totals, snapshot pricing, debit atomically with stock/order creation and prevent unsupported mixed-shop/partial payments. Wallet-funded shipments must pass existing custody/proof/delivery-code checks without collecting COD again.
6. Add batch remittance using the same OrderFinance rows. Lock membership, preserve expected/submitted/received cash, reject duplicates/self-verification, record investigations and private evidence, and allow appropriately scoped hub receipt/logistics-admin reconciliation. Batch and single-order submission must be mutually exclusive.
7. Add configurable route/weight/dimensional/cost rules with draft/approval/effective-date history. Keep the existing shipping setting until the operator activates a complete approved tariff. Sandbox examples are labeled and do not invent industry compensation. Snapshot order pricing and support evidenced hub measurements plus approved treasury-funded adjustments.
8. Credit funded seller proceeds to pending accounts. Release only through existing SellerPayout eligibility and approval. Allocate logistics funds only for evidenced work, preserving each beneficiary, component and transport leg. Shared treasury, logistics funds, commissions, seller proceeds and cash custody are separate accounts. Withdrawals reserve available funds and require the appropriate administrator, independent approval and execution records.
9. Expose the functionality through minimal existing-style finance/forms and existing navigation. Add scoped statements/reconciliation exports and operational commands; preserve existing layout/theme structures.
10. Verify the money invariants and security, then run affected workflows and regression checks. Record actual results and external limitations in the implementation audit.

## Money formulas and ownership

- Ledger transaction: sum of signed entries = 0; account cached balance = sum of its entries. Customer available balances cannot be negative.
- Promotion liability = per-redemption cap in cents × bounded redemption count. Reserved funds remain owned obligations until consumed or released.
- Shipping funding = buyer shipping + seller shipping contribution + platform shipping subsidy. Seller and platform shipping vouchers identify the contributing source once.
- Logistics cost = applicable approved component costs for evidenced work. Logistics margin = shipping funding − logistics cost. Merchandise commission is separate.
- Funded seller item promotion settlement = merchandise − commission (the sponsor has already funded the discount); legacy unfunded seller discount settlement = merchandise − seller discount − commission. This avoids double charging.
- Buyer wallet debit = final order amount; COD due = final order amount only for COD. No hybrid tender until explicitly implemented.
- Batch discrepancy = physically received − expected; submitted and received are stored separately. Shortages/excesses never automatically reduce compensation.

## Controls and rollout

Only a sandbox/mock adapter is enabled initially. Provider callbacks require signature, matching amount/currency/reference, freshness and duplicate protection. Production real-money activation is fail-closed until an approved provider implementation exists. New funds start at zero; no fabricated money migration. Financial reference/entry history is retained. Schema migration is additive; test it in isolated SQLite and, where available, MySQL. Do not run destructive reset/fresh/seed commands against the user's database.

Financial dependencies are reused; no new Composer/npm packages are required. All new financial actions validate role, status, ownership and scope in services as well as controllers. Evidence stays private. New examples require an explicit sandbox command and do not overwrite live balances or existing tariffs.

## Verification acceptance

Cover balanced/reconciled entries; replay and idempotency conflict; insufficient/concurrent spending; signed/failed/reversed funding; wallet checkout/cancellation/refund/settlement; voucher maximum liability and lifecycle; exact/short/excess/self/duplicate remittance; scope leaks; approved versus draft/effective tariffs; weight/dimension changes; immutable snapshots; and no duplicate seller/logistics payout. Document anything not verified, especially real provider settlement and production database races.
