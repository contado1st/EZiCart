# Teacher requirements: Laravel web re-audit

**14 November 2026 — commit `a841440a3116b4409bee9f50f7300c767c67b1d5`, branch `web-revisions-&-updates-w/mobile-integration`.**

> **Follow-up:** the two remaining Partial cases from the original 54 have been reassessed after the fixes. Current counts below include those changes. Targeted post-fix tests passed for rider availability, seller payout/finance, return workflows and related seller reports; the literal teacher cases and all-device acceptance were not replayed.

The **current code-alignment count for the original 54** is 37 Aligned, 17 Different and 0 Partial. The corrected overall workbook count is 164 Aligned, 4 Partial, 95 Different and 56 Missing; the checklist count is 70 Aligned, 32 Partial, 15 Different, 6 Missing, 2 Unverified and 2 Optional. The case-by-case findings have been updated for the follow-up fixes; earlier full-suite evidence is labeled historical.

The recent revisions are present and substantially improve alignment. **The system still does not meet every exact teacher requirement. Of the original 54 Partial cases, 37 now align, 17 implement different rules, and none remain Partial.** This does not mean all 54 teacher expectations match: 17 remain Different because their rules or boundaries differ.

This audit covers the **Laravel web application only**. Flutter/mobile behavior is excluded. Teacher attachments were treated as reference criteria, not instructions to modify business rules or reset their example fixtures. Application code, live database contents, configuration and original teacher documents were not changed. Only audit artifacts and isolated audit tests were added; the prior audit remains preserved.

## What was checked

All five source files still match the SHA256 hashes of the prior extraction: the role-functionality Word checklist and the seller, buyer, rider and logistics workbooks. They contain **127 numbered checklist items and 319 workbook cases**, respectively. Each now has a dated assessment with current source anchors. [Source verification](audits/2026-11-14/source-verification.json) records the matches.

The original 54 Partial rows were independently reassessed against their full expected results. Another 21 case assessments were refreshed where related functionality changed. Remaining assessments were retained after source-delta review and the earlier broad regression run; the later availability/payout follow-up received focused tests. Exact teacher scenarios were not individually replayed. Broad checklist capabilities can align while a more specific workbook limit differs.

| Classification | Meaning |
| --- | --- |
| Aligned | Current implementation supports the expected behavior with equivalent configured fixtures; this does not certify a literal browser test pass. |
| Partial | A material portion remains incomplete, disabled, or only supported through a different adjacent action. |
| Different | Functionality exists but its rule, timing, amount, authority or workflow differs from the reference. This is not automatically an application defect. |
| Missing | The requested capability itself is absent. A related alternative does not satisfy it. |
| Unverified | Evidence is insufficient for acceptance, particularly browser/device/performance checks. |
| Optional | The checklist explicitly makes the capability optional. |

**These are implementation-alignment judgments, not the teachers' executed Pass/Fail results or a completion percentage.** No teacher workbook checkbox/result was changed.

## The original 54 Partial cases

| Current finding | Cases |
| --- | ---: |
| Aligned | 37 |
| Different | 17 |
| Partial | 0 |
| Missing | 0 |
| Total reassessed | **54** |

The [complete 54-case recheck](audits/2026-11-14/previous-partials-recheck.md) gives every original ID, teacher expected result, current behavior, residual difference and code evidence.

Verified improvements include private seller drafts, restricted pending-applicant drafts, product pause/resume, purchased-image retention, store-wide SKU uniqueness, stocked-variant price ranges, wishlist, typo suggestions, true best-selling sorting, staged verified buyer email changes, stronger contact/address/password checks, review photos and editing, product-context messaging, fresh hub queues, richer tracking search, and matching remittance CSV/XLSX exports. There is also a real unpaid reservation/pay/retry/expiry flow using **internal simulated wallet credits**.

The two cases resolved in the follow-up are:

| Case | What is still incomplete |
| --- | --- |
| **LOG-DISP-003** — exclude offline/suspended riders | Availability enforcement is enabled by default. Unavailable, unchosen and suspended riders cannot be selected or assigned new work. Existing accepted parcels remain with their rider. Existing riders must choose Available before taking new work. |
| **SEL-EARN-007** — double-click Request Payout lowers Available once | Each eligible order payout request reserves its proceeds exactly once in the ledger. Repeated requests reuse the hold; held proceeds are unavailable for another request and can be released for an authorized refund. |

## Important differences that remain

| Area / case examples | Teacher reference | Current web behavior |
| --- | --- | --- |
| Checkout — BUY-CHK-001, checklist 3.3.1 | Multi-store parent checkout split into seller orders | **One shop per checkout**, with multiple selected items/variants permitted from that shop. This follows the earlier project decision. |
| Payments — BUY-PAY-002, 004–007 | Provider e-wallet/card gateway; unpaid expiry after 1 hour | COD plus **internal demo wallet**, exact ledger payment and **30-minute** unpaid reservation. No real external card/e-wallet gateway or provider refund. |
| Buyer cancellation — SEL-INV-003, BUY-CAN-003, BUY-VOU-012 | Immediate cancellation/refund initiation and stock/voucher restoration before the cutoff | Paid cancellation waits for seller decision or the **24-hour** timeout. Restoration/reversal is exactly once when actual cancellation occurs. Unpaid demo reservations cancel immediately. |
| Buyer order tabs — BUY-TRK-001 | To Receive starts after physical pickup | **READY_FOR_PICKUP already appears in To receive**. To Pay now genuinely exists for unpaid demo reservations. |
| Refunded sales — SEL-RET-007, SEL-EARN-003 | Refunded merchandise excluded from gross sales as well as earnings | Screen/export/analytics now reconcile, but retain original **gross with a separate refund deduction**. Net/commission are adjusted and full refunds do not count as completed sales. |
| Vouchers — SEL-VOU-004, 006, 007; BUY-VOU-003 | 4–12 alphanumeric code; reject 100%; stacking by voucher type | Codes permit punctuation/spaces and up to 50 characters; **capped 100% vouchers are accepted**; one voucher per checkout. New schedule/usage/date validation does not change these retained limits. |
| Product fields — SEL-PROD-002–007, 017 | Required images/description, 5–120 name, PHP1 minimum, stock ceiling 99999, duplicate-submit protection | Normal product POST still accepts a one-character name, PHP0.99, stock 100000, optional description/image, and repeated identical POSTs create two pending-review products. Draft submission has separate replay protection. |
| Buyer identity/address — BUY-ACCT-002, 007, 009; BUY-ADDR-005, 007 | Alphabetic names, verified phone changes, 5-minute email code, max10 addresses, choose replacement before deleting default | Phone format and minimum name length are fixed, but digits in names remain allowed; phone change is immediate without OTP; email challenge lasts **10 minutes**; **11 addresses are accepted** and default deletion promotes a replacement. |
| Reviews — BUY-RATE-004, 005; SEL-REV-002, 003 | Max 500 characters, 30-day window, one edit, seller reply | Photos, masked names, variants and edit history now exist; comment max remains **1000**, repeated edits are allowed after 30 days, and seller replies remain absent. |
| Rider dispatch/pay — LOG-RIDER-006, RID-JOB-003, 006; RID-PICK-002, RID-DLV-001 | 3 active jobs, 60-second nearest-rider offer, immediate PHP30 pickup/PHP50 delivery pay | Manually chosen Available/Unavailable status is now enforced for new work. Dispatch still uses **10 active deliveries plus verified cargo capacity**, a 15-minute acceptance period and manual reassignment, with no nearest-rider cascade. Tariff-based compensation shows estimated/pending/verified states; no invented immediate fixed wages. |
| Return/RTS — LOG-RTS-001, 003, 004; BUY-RET-001–005 | Automatic RTS after third failure/refusal; receipt restores stock; seller-first return deadlines | Visible Return required context exists, but physical recovery/return, separate per-item seller restock inspection and explicit refund authorization/execution remain required. The dispute flow remains admin-led. |
| Corrections — LOG-TRK-002, 003 | Logistics can override to Delivered with reason/audit | Evidence-bound correction requests require a separate admin review and legitimate physical/proof events. There is **no arbitrary delivery override**. |
| Fees/serviceability — SEL-PROD-008, LOG-RATE-004; BUY-CHK-002–004 | Whole-kg zone table; adding a zone immediately enables priced checkout | Current shipping remains **PHP50 flat**, with **0 approved tariffs**. Zone preview is planning/readiness, not tariff activation. Unmapped destination checkout blocking remains incomplete when tariff mode is off. |
| Other reference policies | 5% commission; 30-minute idle session; receipt releases earnings | Runtime commission is **10%**, idle lifetime **120 minutes**, and seller settlement requires completion, a seven-day delivery hold, verified funding and approval. |

Some differences preserve earlier instructions: one-shop checkout, reviewed seller/rider profile edits, separate administrator responsibilities, physical custody checks and configurable compensation. The teacher files describe marketplace-style assumptions; resolve policy differences explicitly with the teacher before treating them as defects or silently changing them.

## Complete current coverage

| Workbook | Aligned | Partial | Different | Missing | Total |
| --- | ---: | ---: | ---: | ---: | ---: |
| [Seller](audits/2026-11-14/seller-test-case-audit.md) | 46 | 0 | 36 | 42 | 124 |
| [Buyer](audits/2026-11-14/buyer-test-case-audit.md) | 73 | 1 | 27 | 9 | 110 |
| [Rider](audits/2026-11-14/rider-test-case-audit.md) | 22 | 3 | 15 | 1 | 41 |
| [Logistics](audits/2026-11-14/logistics-test-case-audit.md) | 23 | 0 | 17 | 4 | 44 |
| **Total** | **164** | **4** | **95** | **56** | **319** |

The four current Partial workbook cases are **BUY-ACCT-002** and **RID-AVAIL-001/002/003**. The original 54 Partial cases are all now either Aligned or Different. This differs from the original 54 because newly implemented alternatives can now be classified clearly as Different rather than Partial or Missing.

The [full Word checklist comparison](audits/2026-11-14/role-checklist-audit.md) covers **70 Aligned, 32 Partial, 15 Different, 6 Missing, 2 Unverified and 2 Optional** items. The [37-assumption comparison](audits/2026-11-14/assumption-comparison.md) preserves the source rule text and explains the implementation differences. The [evidence index](audits/2026-11-14/evidence-index.md) provides current source locations and related regression classes.

The 56 Missing workbook cases include these capabilities; they are not 56 independent features:

- Seller-owned store categories/hierarchy and related rules (13 cases); shared platform labels are an alternative, not seller category CRUD.
- Scheduled product-price sales (10) and flash-sale functionality (8). Advertising notifications are not a price-sale engine.
- Product duplication and bulk operations; hidden/code-only and product-targeted vouchers; constrained editing of claimed vouchers.
- Seller-first return rejection/escalation/three-day timeout and seller review replies.
- Lifetime per-buyer purchase limits, seven-day automatic buyer completion, real hosted gateway cancellation/callback flows and flash-sale reminders.
- Nearest-rider automatic assignment/decline cascade, arbitrary delivery override, dedicated Lost/Damaged shipment claim states. The broad checklist also lacks rider map/navigation and exclusive seller-category administration.

## Configured-system readiness

The [read-only runtime snapshot](audits/2026-11-14/runtime-settings.json) confirms the new feature tables/columns exist, including private drafts, wishlist, email challenges, review media/revisions, retry requests, return inspection, corrections, wallet funding requests and order payment deadline. No migration was run by this audit.

- **Demo wallet is effectively enabled in the current local environment.** Its database enable flag is on even though the configuration default is off. Credits remain simulated and the service is restricted to local/testing environments; this does not prove production payment readiness.
- Availability enforcement is on by default. The seven existing approved riders had not chosen a state in the audited database snapshot; each must select Available before new work. The check does not reassign accepted parcels.
- There are **0 approved tariffs**. Tariff estimates cannot be accepted as configured wages or operational delivery rates.
- Only **one approved commerce administrator** is present. An administrator cannot approve their own demo treasury-funding request; that flow needs a different eligible approver. No account was created or altered by the audit.
- Scheduled expiry/cancellation operations require the scheduler, and queued messages require the worker. Passing command/notification tests does not prove these long-running processes or actual SMTP/SMS delivery are healthy on the domain.

## Verification performed

| Check | Fresh result | Evidence |
| --- | --- | --- |
| Post-fix focused Laravel checks | **146 passed, 1180 assertions** across rider availability, financial ledger, seller reports, returns and workflow revision suites | Current working-tree run; the five focused suite results are summarized in [post-fix status](audits/2026-11-14/post-fix-status.json) |
| Earlier broad Laravel run | **723 passed, 6205 assertions** before the last two fixes | Historical evidence: `storage/logs/teacher-reaudit-2026-11-14-final.xml`; not a post-fix full-suite result |
| Frontend Node tests | **63 passed**, zero failures/skips in the earlier audit | `storage/logs/teacher-reaudit-frontend-2026-11-14.txt`; not rerun for these server-side changes |
| New isolated audit characterizations | **8 passed, 36 assertions** in the earlier audit | [Probe source](audits/2026-11-14/TeacherReauditProbeTest.php), [observations](audits/2026-11-14/probe-observations.jsonl), `storage/logs/teacher-reaudit-probes-2026-11-14.xml`; pre-follow-up |
| Source integrity/coverage | 5 source hashes matched; 319 unique cases, 127 unique checklist items; exactly 54 original partials reassessed | [Machine-readable summary](audits/2026-11-14/audit-summary.json) |

The initial standard Laravel test command hit the process's **128MB PHP memory limit**, not an assertion failure. The historical complete rerun passed with a **512MB process-only** limit. For this recheck, a regular full-suite rerun did not complete within the session and was stopped; the focused post-fix suites passed. No PHP/.env memory setting was changed. Frontend tests were not rerun in this pass.

Tests used isolated SQLite fixtures and fake external notifications/storage. They do **not** establish literal teacher-fixture acceptance, real browser layouts across devices, domain session behavior, page speed, delivered email/SMS, external payment settlement or MySQL timing races. No frontend build or production deployment was performed for this documentation-only audit.

To reproduce the isolated probes, use the project's testing configuration, `HASH_DRIVER=bcrypt`, and:

```powershell
php -d memory_limit=512M vendor/bin/phpunit docs/audits/2026-11-14/TeacherReauditProbeTest.php
```

Passing characterization probes confirm the described differences. Do not interpret them as teacher-case passes. Do not rerun the 13 November characterization class unchanged as a current regression: several of its old expectations describe behavior that has now correctly been fixed.
