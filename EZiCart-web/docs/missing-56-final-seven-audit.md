# Final seven Missing-case revisions: implementation and scope audit

**Date:** 15 November 2026 (Asia/Manila). **Scope:** Laravel web, shared fulfillment safeguards and the existing internal school-demo wallet. **Programme outcome:** 53 case revisions implemented, three policy-sensitive cases reconciled, zero cases left without an implementation or explicit scope outcome. This does not mean every original teacher expectation is Aligned.

## Final case outcomes

| Original case | Outcome | Evidence / behavior |
| --- | --- | --- |
| LOG-DISP-001 | Implemented | Nearest eligible rider offered work using a current device-reported position and confirmed pickup pin |
| RID-JOB-004 | Implemented | Three distinct declined/expired offers stop the cycle in a visible Unassigned queue |
| LOG-TRK-004 | Implemented | Lost incident, affected-party notifications, preserved custody/load and a separate claim-review record |
| LOG-TRK-005 | Implemented | Mandatory private damage photo, Damaged hold, evidence integrity checks and claim review |
| BUY-PAY-004 | Scope reconciled; original gateway expectation remains Different | Leaving internal wallet payment preserves the same To Pay reservation until expiry; explicit cancellation remains a separate action |
| BUY-PAY-005 | Scope reconciled; original gateway expectation remains Different | A committed internal wallet debit remains visible after reopening; retries do not create a second charge/order. No external asynchronous gateway callback exists |
| LOG-TRK-002 | Scope reconciled; original reason-only override remains Different | Existing independent evidence-bound correction review is retained and verified. A reason alone cannot mark a parcel Delivered |

Earlier batches delivered 49 revisions. These four implementations bring the implementation count to **53**. The three scope outcomes close the remaining inventory entries, producing **56 addressed / zero pending**. The progress register separates those totals. BUY-TRK-005 from Batch 4 also retains its already documented earnings-release difference: completion never bypasses funded, independently approved payout controls.

## Automatic dispatch

The new **Automatic dispatch** workspace is available to couriers, sorting hubs and logistics admins. It preserves existing manual assignment, retry/recovery scheduling, physical handoff scans and dedicated hub-transfer work. Its role-specific tabs and independent numbered 25-row lists show ready work, cycles/Unassigned, candidate outcomes, rider offers and pickup-pin setup.

Riders explicitly request a fresh position with a button and browser permission. There is no background tracking or third-party map service. Positions are device-reported, not independently attested GPS. The server validates coordinate bounds, accuracy up to 250 m, a recent capture timestamp and approved rider ownership. Only the latest point is stored; it is eligible for at most five minutes and is pruned after 24 hours by dispatch processing. Raw rider coordinates are not serialized to buyers/sellers or shown in the offer history.

Hubs confirm coordinates for their own hub and sellers in their scoped work; logistics admins can verify operational pickup pins. Pins are tied to the currently recorded approved address. Address or pin changes invalidate an existing automatic cycle rather than silently moving its pickup point. Seller pickups rank against the seller pin; first-attempt Sorted deliveries rank against the physical hub pin. Buyer destinations are not substituted for seller pickup origins.

Candidate rules require approval, explicit Available status, local rider duty, hub membership and active area coverage, valid license and admin-verified vehicle capacity. Pickup coverage matches the seller municipality. Delivery coverage includes the existing primary-barangay rule without silently overriding another rider. Unknown weights, insufficient capacity and three or more existing/reserved jobs exclude a candidate. Dedicated hub-transfer drivers remain outside local pickup/delivery matching.

Eligible candidates rank by approximate straight-line meters, then stable rider ID. An offer lasts 60 seconds. One pending offer exists per cycle; each rider appears at most once in that cycle. Declines and expiries are recorded separately and both count toward the three-distinct-rider maximum. Fewer candidates may exhaust the cycle sooner. After exhaustion the job is Unassigned; the scheduler never silently creates a new cycle. A hub can resolve the reason and explicitly start a new cycle or cancel provisional offers and use existing manual assignment.

Pending offers reserve vehicle weight and job capacity. ParcelLoadService includes them in individual/batched summaries and the capacity checks shared by manual assignments and hub-transfer movement. Acceptance removes the provisional reservation and creates the ordinary assignment once. Pickup acceptance records the rider's claim, not parcel possession. Delivery acceptance uses the existing transition/assignment lifecycle under the initiating hub's authorization and separately records the rider's acceptance. All normal arrival, handover, storage retrieval and parcel scans remain required.

Automatic operations lock the hub, applicable vehicles in stable ID order, then the order/cycle/offer. Manual assignment retains its existing vehicle-before-order sequence. Current locking reads recheck competing holds/reservations. Unique active-order, active-cycle and cycle/rider constraints complement those locks. Repeated start/accept requests retain their original result. Manual assignment cannot compete with a live cycle for the same parcel. Cancellation requests, incident reports and weight changes withdraw provisional offers; accepted physical cargo is not silently released.

`dispatch:process` defaults to preview. The scheduled `dispatch:process --apply` runs every minute with overlap protection, handles expired/unavailable offers and prunes old device positions. A 60-second deadline is enforced on acceptance even before the next scheduler run; it is not a promise that the next offer will appear at the exact second of expiry. Production scheduler execution and actual device GPS accuracy remain deployment/device checks.

## Lost/Damaged incidents and claim review

The new **Parcel incidents & claims** workspace is scoped to involved accounts and appropriate admins. Hubs/logistics admins report an incident with a reason; damage requires a validated private image. Reports have an operation key and one open incident per parcel. The original custody stage and responsible accounts/transfer/weight are snapshotted and retained. Last-known custody accounts for recorded seller handover, hub release, return handover and active transport manifests.

An open incident displays an unambiguous **LOST/DAMAGED hold** in order progress and dashboard/table labels. Stored OrderStatus values retain their existing physical meaning. Buyer API order detail keeps its physical `status` and adds `incident_status` and `fulfillment_on_hold`; no mobile client UI was changed. Status-distribution analytics and sales-report status labels show the operational incident state. Existing order tabs/physical queues and monetary recognition remain based on their established physical/financial records; an incident does not rewrite earned/paid history.

Shared guards block ordinary transitions, manual dispatch, hub-transfer planning/release/receipt/recovery, buyer completion, physical return actions, inventory restoration and seller payout request/approval/settlement. Wallet/refund/logistics-settlement boundaries also check the hold. Unaccepted-assignment expiry and availability recovery do not discard held assignments. Existing physical load and manifest records remain reserved; reporting loss is not a capacity-release event. Diagnostic evidence and legitimate accounting of already collected money remain separate from fulfillment or payout.

Reporting starts a claim review and notifies buyer, seller, involved operational participants and relevant approved admins. Commerce admins can record a version-checked funding assessment and next steps. Assessments distinguish uncollected COD, recorded COD, wallet/verified-fund review, prior refunds and paid-out recovery cases. This action records a review only: it does not create cash collection, seller debt, compensation, a refund authorization/execution or a wallet credit. Irrecoverable loss remains under review/hold; no automatic loss-compensation payment engine is claimed. Existing authorized financial workflows and recovery reconciliation still govern any eventual money movement.

An independent logistics admin can clear a recovered or incorrectly reported custody hold only with the current parcel label, a private supporting photo and a reason. Original photo integrity is checked where present. Clearance records a tracking/audit event and retains the original report and claim assessment. It changes no physical status, stock, money or completed scan. It does not itself mark Delivered, settle a claim or bypass any remaining dispute/refund/funding controls.

Private evidence downloads validate role/order involvement and checksum, use no-store/nosniff headers and force downloads. Unrelated accounts cannot read the incident or files. Claim notes are restricted in the UI to the relevant administrative roles; raw evidence paths and custody snapshots are excluded from model serialization.

## Three policy reconciliations

The internal wallet remains a school-demo ledger, with administrator-reviewed demo funding. The wallet page now explains abandonment/reopening explicitly. Tests exercise leaving and reopening the same unpaid reservation, a server-committed payment whose redirect is not consumed, reopening To Ship and retrying without a duplicate debit/order. Explicit unpaid cancellation and expiry retain their existing independent behavior. This is an internal equivalent, not a simulated live gateway cancel/callback or proof of real-money payment.

Existing correction pages retain independent review, private evidence, a reason, a newer guarded workflow event and verified delivery/cash evidence before resolution. New tests reject empty reasons and requested/forced Delivered fields; a valid review request changes no parcel or money. The existing ParcelCorrectionTest suite also verifies actual guarded delivery and linked evidence review. No direct hub delivery override was added.

## Verification and corrections

| Check | Final result |
| --- | --- |
| Full installed PHP suite | **839 passed, 7,035 assertions; zero failed/error/risky tests** |
| Focused final-feature suites | 30 passed, 206 assertions before the final integration refinements; included in the full-suite result, not added to it |
| Failed-domain rerun | 132 passed, 1,246 assertions across hub transfers, logistics workflows, parcel weights, roadmap features and seller reports; also overlaps the full suite |
| New frontend tests | Four passed: explicit location consent trigger, successful form submission, permission failure/retry and invalid accuracy/unavailable API fallback |
| Production build | Passed; existing optional fontaine warning retained |
| PHP syntax/style | 113 changed/new non-Blade PHP files passed syntax checks; Pint and git diff whitespace checks passed |
| Isolated MySQL | Migration up/down/reapply; duplicate acceptance once; automatic offer versus manual pickup capacity race; one expiry cascade; one incident under concurrent reports; reservations/money preserved; source counts unchanged |
| Configured runtime | Schema/bounds passed; all four new tables empty; 23 users and 21 orders retained |
| Browser/device visual acceptance | Unverified. Discovery retried; native Chrome exists, but the browser-control host is unavailable |

The MySQL manual worker uses the real ParcelLoadService and the manual controller's vehicle/order lock sequence with an isolated pickup assignment write. It is not a full HTTP hub-handoff race. MySQL notifications use the existing database channel and array mail transport; no external delivery is claimed. Fixtures/schema copies were removed. SQLite feature tests exercise role authorization, private upload/download, status/weight/claim holds, offer boundaries, legacy wallet behavior, migration reversal and existing complete order/return flows.

The first full run exhausted the host PHP process's 128 MB memory limit in an existing profile-image test. The complete rerun used a 512 MB limit for that test process only. phpunit.xml now explicitly selects bcrypt for its already configured four-round test hashing, preventing production-default Argon2 from making thousands of synthetic accounts slow; production hashing/configuration was not weakened.

The full run exposed older courier fixtures that omitted explicit availability. Valid-work fixtures now declare Available, preserving explicit unavailable overrides and existing assertions. The hub demo flow test records rider choices through the actual availability service; the seeder still does not reset existing riders' choices. The previous buyer-dashboard redirect after dispute submission was restored for compatibility. A temporary CSV metadata-column addition caused a numeric-formatting error and was removed; status labels now show incidents without changing the established CSV column contract. Early new tests also corrected missing fixture fields and a qualified area/pivot is_active query. Final checks passed after those corrections; no live availability, custody or financial guard was disabled.

Logs: `storage/logs/missing-56-final-` plus `regression.txt`, `junit.xml`, `focused.txt`, `failed-group-rerun.txt`, `frontend.txt`, `build.txt`, `mysql-checks.json`, `runtime.json`, `migration.txt`, `dispatch-preview.txt`, `syntax.json`, `pint.txt` and `pint-check.txt`. Reproducible checks: `scripts/check-final-missing-revisions.php`, `scripts/verify-final-missing-revisions.php` and the three MissingAutomaticDispatch/MissingParcelIncident/MissingScopeReconciliation feature suites.

## Local rollout

The additive migration `2026_11_15_014300_create_dispatch_offers_and_parcel_incidents` creates dispatch_locations, dispatch_cycles, dispatch_offers and parcel_incidents. It was applied locally after the private backup `storage/app/private/backups/before-missing-56-final-20261115-034908.sql` and an isolated MySQL reversal/reapply check. No existing account, parcel stage, stock, order amount or balance was backfilled. No GPS point, pickup pin, offer cycle or incident was activated in the configured local database. No production deployment is claimed.

The configured dispatch preview reported zero cycles. Hubs must confirm real pickup pins and riders must explicitly share fresh positions/availability before automatic dispatch can work. Existing manual work remains available. Do not seed guessed coordinates into real accounts. A populated rollback drops the new history/hold tables and must not be used to erase live claims or reservations; back up and reconcile them first.

## Walkthrough

1. Hub: open Automatic dispatch → Pickup pins. Confirm the actual seller/hub address and coordinates with a verification note.
2. Rider: choose Available on the existing work board, then Automatic dispatch → Location sharing → Share location for offers. Grant permission over HTTPS/localhost and obtain sufficient accuracy.
3. Hub: in Ready work, start a new offer cycle for an unassigned seller pickup or first-attempt Sorted delivery. Read the cycle outcome if no candidate is eligible.
4. Rider: accept or decline within the displayed 60-second deadline. After acceptance, use the existing work board and physical scan/handover actions.
5. Hub: inspect Cycles / Unassigned and its candidate history. After three distinct declines/expiries, resolve the cause and deliberately start a new cycle or cancel pending offers and assign manually. Accepted cargo still requires normal recovery procedures.
6. Hub: report Lost/Damaged in Parcel incidents & claims; attach a damage photo when required. Buyer/seller/involved staff can review the hold and notifications.
7. Commerce admin: record a factual funding/claim assessment and next steps. No funds are issued by this review. Logistics admin: independently reconcile a recovered/incorrectly reported parcel with its current label/photo before clearing a hold.
8. Buyer: leaving wallet payment uses the same To Pay reservation; reopening a committed payment shows the saved result. Continue through existing finance approval and custody controls.

## Acceptance boundary

The original 56 source identities/expectations and baseline fingerprint are preserved. Original teacher execution flags remain false. This audit closes the implementation/scope inventory; it does not claim a fresh literal execution of all 319 workbook cases or all 127 Word-checklist items. Other established Different/Partial checklist findings, browser/device acceptance, actual GPS behavior, external mail and deployed scheduler health remain separate from this implementation count.

Design metadata note: Impeccable reported that its design.json sidecar is older than DESIGN.md. The established dashboard styles were preserved; regenerating that sidecar with the document workflow remains optional and was not mixed into these functional changes.
