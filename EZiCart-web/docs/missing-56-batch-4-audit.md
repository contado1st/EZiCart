# Missing-case revisions: Batch 4 implementation audit

**Date:** 15 November 2026 (Asia/Manila). **Scope:** Laravel web and shared order/dispute safeguards. **This batch:** six case revisions. **Programme:** 49 of 56 implemented; seven remain. These are implementation counts, not a fresh teacher-checklist classification.

## Case coverage

| Group | Original case IDs | Delivered behavior |
| --- | --- | --- |
| M07 | SEL-RET-003, SEL-RET-004, SEL-RET-005, SEL-RET-006, BUY-RET-005 | Seller rejection validation/reason display, one-time buyer escalation, seller response deadline and request tracking |
| M10 | BUY-TRK-005 | Retry-safe completion eligibility at 168 elapsed hours after verified delivery, with historical opt-in and shared holds |

**BUY-TRK-005 retains a policy difference:** completion does not automatically pay earnings. Verified funds, the existing delivery hold, refund/dispute checks and separate payout authorization/execution still govern payment. No external payment provider was introduced.

## Seller-first request handling

New web requests default to seller-first review. A separate `seller_return_responses` row tracks Awaiting seller, Seller approved, Seller rejected, In Dispute, Deadline approved and Closed without replacing the existing administrator dispute status. The seller workspace has stage filters and numbered 25-row pagination, linked from the seller sidebar. Buyer/seller order pages, return progress and admin case details display the same request stage and exact rejection reason. Output is escaped.

Sellers can approve a request or reject it with a 10–300 character reason. A rejection opens a 72-elapsed-hour escalation window. Buyers see the exact stored reason and an Escalate action while that window is open. Escalation changes the admin case to UNDER_REVIEW and closes seller actions. Version checks reject stale screens; order, dispute and response locks serialize seller, escalation, admin and timer actions.

The seller response deadline is 72 elapsed hours from request creation. At the exact deadline the request is eligible for automatic approval; valid late seller submissions apply that approval rather than accepting a late decision. A separate command processes unattended requests. Approval is of the request for further authorization: it does not invent pickup, seller receipt, stock restoration, refund payment or released proceeds.

At the exact end of the buyer's 72-hour escalation window, an un-escalated seller rejection closes as REJECTED. The original seller reason remains recorded. Late escalation requests record closure rather than opening a dispute after expiry. Existing final admin decisions cannot be changed through these seller actions.

Buyers may select direct administrator assistance when submitting, or request urgent admin takeover while a seller-first case is open. Administrators retain their original intervention and financial authorization tools. Taking over or resolving a new case closes its seller-response controls. Legacy admin-led cases, including API-created cases, are not silently enrolled in seller deadlines.

Notifications go to the buyer and seller for request-stage changes, and relevant approved commerce admins receive escalation/approval-review notifications. Existing workflow-attention counts include seller responses and buyer rejection review. The return panel links involved buyers, sellers and commerce admins to authorized private evidence downloads; unrelated users cannot download them. Logistics viewers retain operational workflow access without gaining this private request-evidence endpoint.

The existing physical return chain remains required: admin refund/replacement authorization, assigned rider acceptance, buyer handover, parcel scan/collection, seller receipt, stock inspection and separate funded refund execution. The previous return/finance regression tests continue to exercise that chain.

## Automatic completion and rollout

Only a new validated Delivered transition at/after `AUTO_COMPLETION_ROLLOUT_AT` enrolls an order automatically. The default cutoff is **15 November 2026, 00:00 Philippine time**. Enrollment stores the due time as verified delivery plus 168 hours. Existing Delivered records keep null enrollment fields. Setting a cutoff does not backfill them.

`OrderTransitionService::completeAutomatically` is a non-routable system entry. It locks/reloads the order, checks Delivered state, enrollment/deadline consistency and the shared completion holds, then records COMPLETED, a system tracking event and an audit with no impersonated buyer. Repeated workers/runs cannot record a second completion. Buyer/seller notifications are persisted transactionally. Manual buyer receipt remains available before seven days for a verified, unheld delivery; its authority remains restricted to the owning buyer.

Shared completion checks cover pending cancellation/physical transfer, active seller responses, pending/reviewed/approved returns, any refund execution, open parcel corrections, open logistics recovery exceptions and payout holds. They also require valid payment evidence, a non-future delivery time, used delivery code, a matching rider/delivery attempt and an existing private proof file. COD recorded collection and order amounts must match. The buyer page explains a hold and hides the unavailable receipt action.

Unverified COD remittance or a normal funding/payout waiting period is not converted into a money transfer by completion. The financial service continues to decide payout eligibility. Completion never restores inventory, settles a ledger entry, authorizes a refund or creates a payout.

The completion scheduler runs every 15 minutes, so eligible orders are processed on a subsequent run rather than by a guaranteed timer at the exact second. Seller deadlines run every minute. Both commands have overlap protection and default to read-only preview when called without `--apply`. Production scheduler operation and external mail delivery were not verified.

The shared API dispute writer previously lacked the order lock and stored new uploads publicly. It now locks/rechecks the order and duplicate case, stores new evidence privately, and cleans uploads after failure. Its existing administrator-led case semantics are preserved; no mobile client screens or mobile seller-response UI were added. Historical public uploads were not moved by this batch; the existing sensitive-document migration procedure remains a separate operation.

## Verification

| Check | Result |
| --- | --- |
| Related domain regression | 197 passed, 1,739 assertions across MissingReturnCompletionTest, ReturnWorkflowRevisionTest, FinancialLedgerWorkflowTest, WorkflowRevisionTest, WorkflowAttentionTest, ModuleRevisionTest, ParcelCorrectionTest, DeliveryRetryGuidanceTest and WebOperationsTest |
| Latest focused checks after evidence/receipt refinements | 33 passed, 239 assertions across MissingReturnCompletionTest and WorkflowAttentionTest; overlaps the regression suites and is not added to their count |
| Deadline behavior | Before/at 72-hour seller deadline; before/at rejection escalation expiry; request-time enforcement, preview-only behavior, scheduler replay and admin/urgent takeover |
| Completion behavior | Before/at 168 hours; repeat runs, early manual buyer receipt, missing payment/code/proof/amount checks, active-return and recovery-exception holds, legacy preview/opt-in and no earnings release |
| HTTP/UI rendering | Seller/buyer/role isolation, escaped exact reason, private evidence ownership, seller 25-row pagination, guest rejection and held receipt feedback |
| MySQL copy/migration | Up/down/reapply preserved copied business-row counts |
| MySQL concurrent services | One seller/version decision; one deadline approval; expired escalation cannot win; automatic completion and persisted buyer/seller notifications occur once; no payout/ledger release |
| Concurrent return and completion | Both operations serialize on the order; an accepted return retains its hold and blocks subsequent completion. A return may open after completion has committed, as the existing Completed-order return policy permits |
| Production build | Passed with the existing optional fontaine warning |
| Syntax / style | Changed/new non-Blade PHP files passed syntax and Pint checks; git diff whitespace check passed |
| Browser visual acceptance | Not performed. Discovery retried: native Chrome is present, but the browser-control host is unavailable |

The final focused suite includes private API upload/duplicate handling and early manual receipt changes made after the broader regression run. No full-project or all-device acceptance is claimed. Automated tests use isolated SQLite. MySQL workers use the explicitly verified copy connection, private synthetic proof bytes and array mail transport; they do not send external mail. Delivery validation itself remains exercised by the delivery regression suites.

Evidence lives under `storage/logs/missing-56-batch-4-`: `focused.txt`, `regression.txt`, `mysql-checks.json`, `runtime.json`, `migration.txt`, `completion-preview.txt`, `deadline-preview.txt`, `build.txt`, `syntax.json`, `pint.txt` and `pint-check.txt`. Reproducible probes: `scripts/check-return-completion-revision.php` and `scripts/verify-return-completion-revision.php`.

Initial checks found test-fixture defects: a pagination fixture reused an order despite the existing unique dispute/order constraint; deadline expectations compared fractional seconds with second-precision persistence; fake notifications counted separate database and mail channel dispatches together. These were corrected without changing the intended time or notification behavior. Four older ModuleRevisionTest paths seeded riders without declaring availability under the already-enabled availability policy. Their courier fixture now explicitly sets Available, retaining all assertions and live availability enforcement. The corrected relevant suites passed.

## Configured local database

Before migration, a private backup was saved at `storage/app/private/backups/before-missing-56-batch-4-20261115-005950.sql`. The additive `2026_11_15_000001_add_seller_return_responses_and_completion_eligibility` migration then applied successfully. It creates the response table and adds nullable enrollment/due/completed timestamps to orders. It does not backfill any historical workflow.

The configured read-only probe reports **23 users, 21 orders, zero disputes, zero response rows, zero enrolled orders and zero auto-completed orders**, with no invalid enrollment/response rows. The completion preview identified the existing delivered demo parcel as unenrolled and reported zero eligible orders. Deadline preview reported zero eligible decisions. No configured completion/deadline `--apply` command or historical opt-in was run. MySQL concurrency fixtures and their proof file were cleaned up; source business counts remained unchanged.

Rollback removes the new table and timestamps. Back up populated response history, due dates and audit context before any rollback; it does not undo a completed status or reconstruct deleted response stages. No production rollout is claimed.

## Operator walkthrough

1. Buyer: open a Delivered/Completed order → File a dispute. Select Seller first or Administrator assistance now, describe the issue and attach valid evidence.
2. Seller: open Return requests → filter Awaiting seller → Respond to request. Review the complaint/private evidence, then approve the request or reject with 10–300 characters.
3. Buyer: open the order or Return progress. Review the exact rejection and choose Escalate before the displayed deadline. Use urgent admin assistance while an open seller-first request needs immediate intervention.
4. Commerce admin: open Disputes. Review the seller stage, original evidence and reason. Record the existing verdict. Approving a request alone does not collect the parcel or send a refund; follow the authorized physical/finance workflow.
5. Operator: preview `php artisan returns:process-deadlines`; use `--apply` only when reviewing/applying due request decisions. Normal scheduler processing uses that flag.
6. Operator: preview `php artisan orders:complete-delivered`. Review each eligible/held reason before applying. The registered scheduler uses `--apply`; actual server scheduling still needs operational verification.
7. For an individually reviewed historical delivery, preview `php artisan orders:complete-delivered --enroll-order=123 --reason="Reviewed delivery proof and collection records"`. Repeat with `--apply` to record that one opt-in. Enrollment does not itself complete the order. Preview completions again before applying them.

**Next:** four Batch 5 dispatch/parcel-claim cases, then three Batch 6 payment/delivery policy reconciliations. Original teacher source rows and execution flags remain preserved in the progress register.
