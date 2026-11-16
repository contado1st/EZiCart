# Final module verification — 16 November 2026

This is the **baseline audit before the requested fixes**. See [the follow-up resolution report](module-verification-fixes-2026-11-16.md) for current implementation and test results. The original findings below are retained as evidence.

## Verdict

**Not an all-clear.** The existing automated coverage largely passes, but current catalog data blocks most individually quoted marketplace products, a mixed-weight shipping quote can understate weight, and two existing test expectations disagree with the current UI.

This is a verification report, not an implementation-completion percentage. Scope: Laravel web modules, shared services and existing mobile API tests. Flutter device screens and the deployed domain were not certified by this check.

Baseline: branch `web-revisions-&-updates-w/mobile-integration`, commit `22cde08`; tracked worktree clean at the start. Checks used the installed PHP 8.4 toolchain. Configured application database checks were read-only; synthetic workflow tests used isolated databases and test mail/queue configuration.

## Results

| Check | Result | Evidence under `storage/logs/` |
| --- | --- | --- |
| Complete PHPUnit suite | **953 passed, 2 failed; 955 cases, 8,759 assertions, zero errors/skips** | `final-modules-20261116-junit.xml` |
| Frontend suite | **134 passed, zero failures/skips** | `final-modules-20261116-frontend.txt` |
| PHP syntax in app/routes/config/database | **423 files passed** | `final-modules-20261116-syntax.json` |
| Production assets | **Build passed** | `final-modules-20261116-build.txt` |
| Routing | **634 non-vendor route records loaded, including 259 API records; all 631 application controller actions exist** | `final-modules-20261116-routes.json`, `final-modules-20261116-live-health.json` |
| Migrations | **86 applied, zero pending**; no migration executed | `final-modules-20261116-migrations.txt` |
| Foreign keys / ledger | **No single-column ID-reference orphans detected; ledger reconciliation balanced with no account mismatches** | `final-modules-20261116-live-health.json` |
| Configured MySQL schema/query probes | **Passed** for demo wallet constraints, dispatch/incident invariants, eight empty-owner logistics queue predicates, and compensation SQL | `final-modules-20261116-{wallet-schema,final-schema,queue-sql,compensation-sql}.json` |
| Extended isolated MySQL / concurrency | **Incomplete: exceeded the runner's 600-second timeout**; no pass claimed | `final-modules-20261116-mysql.txt` |
| Smaller populated MySQL compatibility check | **12 passed, 116 assertions**: `CheckoutIntegrityTest` and `HubTransferFlowTest` in a newly created disposable MySQL schema | `final-modules-20261116-mysql-basic.txt` |
| Blade compilation | **Passed**, all templates cached successfully | `final-modules-20261116-views.txt` |
| Visual browser verification | **Blocked**: computer-use access stopped because it could not reliably determine the current Chrome URL | No visual pass claimed |

The first full PHPUnit attempt exhausted the CLI's 128 MB memory limit during an upload test. The completed rerun used `php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --configuration=phpunit.xml --log-junit=storage/logs/final-modules-20261116-junit.xml --no-progress`. Application PHP configuration was not changed.

The successful MySQL invocation was `php tests/Support/run-isolated-mysql.php 'CheckoutIntegrityTest|HubTransferFlowTest'`. Its result was checked from the complete test summary, not just the shell exit status. It proves these 12 cases on MySQL, not MySQL wallet/concurrency certification. The runner's `finally` cleanup removes its own randomly named test schema.

## Coverage by module

These are areas exercised by existing tests, not a claim that every possible input, endpoint or real-device interaction was tested.

| Module | Coverage exercised | Assessment |
| --- | --- | --- |
| Buyer / marketplace | Discovery, address management, product detail/media, base/variation pricing, cart persistence/selection/variant changes, buy-now, checkout integrity, wallet orders, returns/reviews, buyer API | Existing tests pass; current catalog and mixed-weight quote findings below prevent an all-clear |
| Seller | Draft workspace, catalog, variants, compliance, vouchers/funding/limits, orders, handover, enquiries, reports, payout controls | Existing coverage passes except the withdrawal-form expectation described below |
| Local rider / courier | Activation, duty distinction, availability, QR custody, weight/capacity, delivery attempts, returns, earnings presentation | Existing workflow tests pass; legacy credential completeness still needs attention |
| Hub-transfer driver | Dedicated duty, load reservations, hub authorization, release/receipt scans, handover/reassignment and custody protections | Existing flow tests pass; MySQL results recorded separately |
| Sorting center | Intake, sorting, storage, queue/search, dispatch, cross-hub custody, incidents, load planning, riders, compensation/remittance | Existing coverage passes; historical documents/rates and notification delivery remain operational follow-up |
| Commerce administrator | Registration/document review, profile approvals, compliance, disputes/refunds, vouchers, treasury/funding controls, analytics/exports | Existing coverage passes; no real financial operation performed |
| Logistics administrator | Hub/rider approvals, verified capacity, zones, rates, dispatch/incident oversight, transport recovery and remittance | Existing coverage passes; legacy account records need completion |
| Shared services | Tab sessions, account security, chat/attachments/previews/unsend, notification counts, feedback/navigation, media processing and financial ledger | Two UI/test mismatches, historical failed jobs and blocked visual review noted below |

Per-class counts are saved locally in `storage/logs/final-modules-20261116-test-classes.json`.

## Findings requiring follow-up

### F01 — High: visible products lack weight and cannot receive individual shipping quotes

The configured database has **8 products matching `Product::availableForMarketplace()`; 7 lack positive weight**. Calling the active delivery-pricing service for one unit of each, using its seller's own destination address, produced one successful quote and seven weight/destination validation refusals. A real cross-town checkout was not submitted.

Affected product IDs in this snapshot: **1, 4, 5, 7, 8, 9, 13**. Across all approved, nonarchived products, 10 of 12 lack weight; that broader set is not the same as the marketplace-visible set.

Action: obtain accurate seller-entered weights through the existing product workflow. Consider a clear listing-readiness warning or visibility rule until pricing requirements are met. Do not fabricate measurements or delete products as an audit side effect.

### F02 — High: mixed known/unknown product weight is accepted by the tariff quote

`app/Services/DeliveryPricingService.php::quote()` casts each missing weight to zero, sums the lines, then checks only whether the aggregate is positive. A read-only probe using unsaved products reproduced:

| Inputs, quantity one each | Observed result |
| --- | --- |
| Missing weight only | Refused |
| 500 g product plus a product with missing weight | **Accepted as 500 g**, quoted at 7,000 cents under the current local COD tariff |

This is a pricing-service reproduction, not a claim that a real order was placed. It can understate an estimate used by checkout; the separate packed-parcel movement guard does not correct the original shipping quote.

Action: validate every billable line's positive weight before versioned pricing, with regression coverage for mixed known/missing/zero weights in shared web/API checkout. Preserve the intended legacy-pricing compatibility policy explicitly.

### F03 — Medium: two existing regression tests do not match current form/navigation contracts

1. `CommunicationCenterTest::test_unread_badges_count_each_recipients_alerts_and_clear_only_the_opened_thread`, line 123, expects `aria-label="Messages, 1 unread"`. The buyer sidebar now labels that destination **Chat**. The preceding count endpoint assertions pass. Later assertions in this case are not executed after failure.
2. `PartialSellerReportingTest::test_withdrawal_retry_reserves_once_and_rejects_changed_payload_for_same_key`, line 151, expects the original request key after submitting a payload without `wallet_form`. The real form sends `wallet_form=withdrawal`; the view intentionally restores only the matching form. `DemoWalletFundingTest::test_wallet_retry_restores_only_the_matching_form_and_request_key` exercises this real form contract and passes. The later idempotency assertions in the failing case are not reached.

These observations point to outdated test setup/labels, rather than a demonstrated unread-count or withdrawal-retry failure. They remain **failed tests**, not reclassified passes. Align the cases with the intended UI contract while retaining their unread-isolation, single-reservation and changed-payload assertions. No existing tests were deleted or rewritten in this audit.

### F04 — Medium: notification delivery still has historical failed jobs

There are **15 failed `DeliverNotificationChannels` jobs**, dated 21 October–15 November 2026:

- 7 encryption/decryption exceptions.
- 5 route-not-found exceptions.
- 3 relationship-not-found exceptions.

The referenced `chat.show` route and `Order::activeIncident` relationship exist now. This does not prove that those queued payloads can safely be replayed or that SMTP delivery succeeds. No jobs were retried, removed or sent. The configured mail driver is SMTP and queue driver is database; one local queue-worker process and one scheduler-worker process were observed, with zero pending jobs at the data snapshot.

Action: review the failed payloads privately, check their compatibility with current code and encryption configuration, verify controlled mail delivery, and decide which jobs should be retried. Do not rotate encryption keys or replay historical notifications blindly.

### F05 — Medium: legacy credentials and private documents are incomplete

Among approved accounts, **7 couriers have missing or expired license dates**. Private submitted-document paths are unavailable for **3 sellers, 7 couriers and 4 sorting centers**. All approved riders do have capacity values and verification timestamps in this snapshot.

The movement guard rejects known expired licenses but retains compatibility for legacy null expiry dates. Missing dates must not be described as already blocked by that guard. Complete genuine credentials/documents using the existing review process; do not substitute demo papers for real records.

### F06 — Configuration/history: tariff coverage and legacy allocation require an explicit decision

All four effective route-class tariffs have a maximum weight bracket of **20 kg**. Two existing orders exceed 20 kg. One historical shipment-financial snapshot has no rate version. These are not automatically evidence of a faulty historical transaction; old snapshots retain their original policy.

Action: review supported shipment sizes and approved costs before enabling new heavy orders. Review any historical unconfigured allocation separately; do not recalculate settled history or change packed weights to fit a tariff.

### F07 — Verification tooling: old all-module probe cannot currently certify its findings

`scripts/audit-module-revision-probes.php --check` stopped during fixture setup with a rider-availability validation exception. Its current synthetic driver setup does not explicitly satisfy the newer availability rule. The captured invocation returned exit zero but produced an exception report instead of completed JSON results. **It is not counted as a pass**, and the historical audit's past results do not certify this invocation.

Action: update the fixture availability and error/exit handling, then rerun and inspect the complete result structure. Dedicated `ModuleRevisionTest` coverage passed in the full suite. Artifact: `storage/logs/final-modules-20261116-probes.json` (the content is exception output despite the extension).

The extended `tests/Support/run-isolated-mysql.php` invocation also returned exit zero while its output reported a 600-second `ProcessTimedOutException`. Automated consumers must inspect that failure and must not treat the exit status as a successful test result. Add explicit exception-to-failing-exit handling to standalone runners. Its filter also selected `ReturnWorkflowRevisionTest` because the name contains `WorkflowRevisionTest`. Wallet race fixtures explicitly skip MySQL; their passing SQLite cases do not certify MySQL wallet races.

## Operational limits and preserved state

- Financial mode is **sandbox**, and demo funding is enabled. Passing wallet tests does not mean real cash-in/out or bank settlement is available.
- FFmpeg and FFprobe are available. Physical webcam/QR hardware, permission behavior, real SMTP delivery and Flutter device screens remain unverified in this pass.
- No product weights, account credentials, private papers, orders, balances, fee history or queue failures were altered. Read-only configured-database probes confirmed unchanged monitored business row counts. No notifications were intentionally sent to users.
- Build output and verification artifacts are local generated files. This report is the tracked deliverable; no application code, tests, dependencies or environment settings were changed.

Resolve the high-priority pricing/data findings, reconcile the two test expectations, and perform the blocked browser/controlled-notification acceptance checks before declaring all features working.
