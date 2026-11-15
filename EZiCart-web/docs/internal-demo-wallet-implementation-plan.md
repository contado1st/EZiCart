# Internal demo wallet: implementation plan

Decision: 14 November 2026. The user replaces the external-provider requirement for the last six revisions with an internal school/demo wallet, and requests both demo-credit requests and recorded demo cash deposits approved by administrators. Laravel web only. This supersedes the provider-access gate for this chosen scope; it does not implement external e-wallet/card processing or change the original teacher expectations.

Execution result, 14 November: the six revised implementations are delivered. Full regression passed 723 tests / 6,205 assertions in two complete batches. The additive configured migration and isolated MySQL up/down/reapply probe passed; demo funding was initially disabled and was subsequently enabled on 14 November at the user's request. See the [final review](final-54-revisions-check.md). See the [audit](partial-features-implementation-audit.md) and [walkthrough](internal-demo-wallet-user-guide.md). Browser/device acceptance and timed MySQL concurrency remain unverified. The sections below preserve the plan written before implementation.

## Intended flow

1. A user opens Wallet, sees available/pending/reserved/held balances and requests either demo credits or a recorded demo cash deposit. A demo deposit needs a demo receipt reference and private supporting evidence. A request alone does not credit funds.
2. An appropriate approved administrator reviews the immutable amount/source/evidence, records a reason and approves or rejects. No self-approval. Commerce administration reviews buyers/sellers/platform treasury; logistics administration reviews riders/hubs/logistics administration. Approval posts a balanced, idempotent demo-issuance-to-wallet entry. This is explicitly simulated; it claims no real cash receipt or banking transfer.
3. A buyer can pay immediately at checkout using the existing wallet flow, or explicitly reserve a wallet order to pay later. The latter reserves current stock/voucher liability once for a configurable 30-minute window and appears in To Pay. Insufficient funds do not mark payment successful. Seller preparation deadlines/notifications start only after payment.
4. An owned payment page displays the stored exact total, available balance, shortfall and expiry. A password-confirmed POST debits available funds and credits order escrow atomically. Replays return the existing payment. Fulfillment still uses the shared verified-payment guard. Frozen/malformed accounts remain on hold.
5. Unpaid reservations can be cancelled immediately by their owner or expire through the transition service/scheduler; inventory and voucher liability release once. Paid orders retain the existing seller/buyer cancellation approval rules. Approved paid cancellation refunds the original full wallet payment and reverses pending proceeds once.
6. Verified prepaid delivery collects zero COD while preserving recipient/code/photo/custody requirements. Undelivered prepaid RTS keeps physical return, seller receipt, inspection, independent refund authorization and separate wallet execution. Refund execution must be replay-safe and notify the buyer truthfully.

## Integration and preservation

- Reuse financial accounts, immutable ledger transactions/entries, funding intents, order/purchase snapshots, voucher funding/restoration and current transition services. No dependency, new role or historical order-status value.
- Add nullable order payment deadline and nullable funding request/review fields. Old orders and signed mock-provider funding keep their current contracts. Internal requests use a distinct provider/source and cannot be processed by the old mock callback.
- Preserve default immediate WALLET/COD calls in shared purchase/API services; deferred payment is a compatible optional web choice. API order lists/counts gain optional To Pay support and exclude those new records from To Ship without rewriting other legacy tab definitions. Flutter UI work remains excluded.
- Reuse existing wallet tabs/paginated history and dashboard components, with accessible explicit forms, confirmation prompts and private downloads. Avoid another long dashboard. Preserve the original wallet and finance URLs.
- Use after-commit notifications and audit each funding/payment/refund decision. Reject role/owner/amount/reference/file/expiry conflicts on the server. No arbitrary mark-paid, direct browser balance credit or demo-to-real mode switch.

## Rollout and migration

Keep demo operations behind existing explicit local/testing sandbox controls; do not activate a live-money feature or silently credit configured accounts. Record a private full backup before configured migration, inspect table/index size and dry-run the additive DDL on a temporary MySQL copy. No guessed historical payment state/backfill. The nullable deadline defaults to absent. An expiry index supports the scheduler. Test rollback on the temporary copy only, and preserve funding/payment history once used. Disable new deferred checkouts during a rollback; finish or cancel outstanding reservations before any destructive schema removal. Retain a compatible release or fix forward.

## Verification and completion

Test both funding kinds, independent role-scoped approval/rejection, evidence privacy/hash, replay/conflicting keys, self-approval, account freeze and disabled/production controls. Test immediate and deferred purchases, actual To Pay ownership/counts/pagination, exact payment, insufficient funds, password/CSRF/ownership, expiry boundaries, cancellation and no duplicate notifications/debits/reservations. Exercise the six named cases against internal credits: seller cancellation, successful wallet payment, buyer tabs, paid voucher cancellation, zero-COD delivery and received RTS authorization/execution. Preserve existing full journey tests and run the relevant suites, full regression, build and formatting. Browser/device and MySQL timed-concurrency results are reported separately.

Completion means the six **revised internal-wallet implementations** are delivered and tested. It does not convert teacher workbook checkboxes, claim external e-wallet/card alignment or represent demo credits as real funds. The audit and 54-case matrix must retain each original source reference and explicitly record this scope substitution.
