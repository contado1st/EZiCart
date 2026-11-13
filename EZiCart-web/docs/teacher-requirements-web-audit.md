# Teacher requirements audit for the Laravel web application

Audit date: **13 November 2026**. Reviewed branch: `web-revisions-&-updates-w/mobile-integration`, commit `782fb283331a9acf96cd1887b6e25722b7ca906a`. Scope: `EZiCart-web` routes, controllers, services, models, validation, Blade/JavaScript behavior, configuration and automated Laravel tests. **The Flutter application is excluded from compliance assessment.**

**The system does not meet every supplied criterion.** It implements the core seller-to-buyer parcel journey, private messaging, account approval, cart persistence, stock protection, financial records and analytics. However, many exact teacher rules differ, and several complete features are absent. Some differences follow earlier project decisions; others are concrete validation or feature gaps.

This was an audit, not an implementation request. Application features, database records, configuration and teacher attachments were not changed. New files contain audit evidence, isolated characterization probes and reports only.

## Documents reviewed and complete coverage

| Supplied document | Scope reviewed | Detailed comparison |
| --- | --- | --- |
| `Ecommerce_Role_Functionality_Checklist.docx` | **127 numbered items**, including admin, seller, buyer, logistics, rider, integration and general checks | [Full role checklist](audits/2026-11-13/role-checklist-audit.md) |
| `Blackbox_Test_Cases_Seller_Modules-1.xlsx` | **124 cases**, S1–S12 | [Seller cases](audits/2026-11-13/seller-test-case-audit.md) |
| `Blackbox_Test_Cases_Buyer_Modules-1.xlsx` | **110 cases**, B1–B14 | [Buyer cases](audits/2026-11-13/buyer-test-case-audit.md) |
| `Blackbox_Test_Cases_Rider_Modules-1 (1).xlsx` | **41 cases**, R1–R7 | [Rider cases](audits/2026-11-13/rider-test-case-audit.md) |
| `Blackbox_Test_Cases_Logistics_Modules-1 (1).xlsx` | **44 cases**, L1–L8 | [Logistics cases](audits/2026-11-13/logistics-test-case-audit.md) |

All **319 workbook cases** and all **127 checklist items** have an explicit assessment. Each detailed case table retains its ID, sheet/expected-result cell, scenario, expected result, actual implementation difference and code anchor. [The evidence index](audits/2026-11-13/evidence-index.md) identifies related regression tests. [The assumption comparison](audits/2026-11-13/assumption-comparison.md) accounts for A1–A37.

The workbook introduction says its rules are assumptions modeled on marketplaces, not extracted from this system. In particular, A2–A8 must be agreed before testing and A25–A37 define additional strict limits. They were treated as **comparison criteria**, not authorization to change the application, seed their example accounts or reset your stock.

## What the assessment statuses mean

| Status | Meaning |
| --- | --- |
| **Aligned** | The implementation supports the substantive expected behavior for equivalent records/configuration. Code and related regression evidence support it. This is **not** an executed teacher Pass checkbox. |
| **Partial** | A feature exists, but one or more required subfeatures, channels, exact boundaries or workflows are incomplete/different. |
| **Different** | The system implements a materially different rule, limit, timing or workflow. Some are intentional project decisions; this does not automatically mean the system is broken. |
| **Missing** | No matching Laravel route, persistence and user workflow was found for the requested feature. |
| **Unverified** | Inspection/server tests cannot establish the requested browser/device/external behavior. |
| **Optional** | The checklist itself makes the feature conditional; agreement is required before treating absence as a mandatory failure. |

These are implementation comparison counts, **not a system completion percentage or a black-box pass rate**. A complex missing checkout behavior and a small boundary case are not equally weighted features.

| Workbook | Aligned | Partial | Different | Missing | Cases |
| --- | ---: | ---: | ---: | ---: | ---: |
| Seller | 31 | 18 | 31 | 44 | 124 |
| Buyer | 57 | 16 | 20 | 17 | 110 |
| Rider | 16 | 8 | 12 | 5 | 41 |
| Logistics | 16 | 12 | 12 | 4 | 44 |
| **Total** | **120** | **54** | **75** | **70** | **319** |

The separate DOCX checklist contains **62 aligned, 40 partial, 15 different, 6 missing, 2 unverified and 2 optional** items. Do not add checklist and workbook counts as though they were independent features: many overlap.

## Important features already present

- Role login/logout, password reset by email OTP, administrator permission separation, private registration-document review, and approved-account access controls.
- Seller product/media/flat-variation management, compliance review, guarded preparation stages, parcel weights, printable labels and custody handovers.
- Persistent buyer cart with shop grouping and checkboxes, independent Buy Now checkout, current-price/stock revalidation, address book and per-order address snapshots.
- Buyer receipt confirmation and product star/text reviews; shop following and related-user private messaging with attachments/unread notifications.
- Seller pickup, hub intake, sorting, dedicated hub-transfer driver, destination dispatch, delivery proof and buyer delivery-code verification. Capacity/weight and ownership checks remain enforced.
- Stock reservation on placement, once-only restoration, scoped tracking/history and rollback-aware notifications.
- COD financial records/batches, independent cash verification, discrepancy investigation, pending seller proceeds, controlled payouts/refunds and sandbox wallet accounting.
- Role-scoped analytics with animated charts and CSV/XLSX/PDF exports. Financial statement formats and report recognition rules are more limited than the broad analytics feature.

The existing full-journey tests support the core physical flow. They do not establish that every teacher variant, device, exact UI label or external delivery channel works.

## Differences that should be agreed with your teachers

| Topic | Teacher reference | Current Laravel behavior | Interpretation |
| --- | --- | --- | --- |
| Mixed-shop checkout | Multiple shops in one purchase, split into seller sub-orders, with stacked discounts | Cart can hold many shops, but selection/checkout must be from **one shop**; each later purchase is independent | **Earlier explicit user decision.** Do not enable multi-shop checkout just to match the sheet without revisiting it. Cases `BUY-CHK-001`, `BUY-VOU-003`, checklist `3.3.1`, `6.1.1`. |
| Product categories | DOCX requires one globally exclusive category per seller; workbooks describe independent seller-owned category hierarchies and multiple platform categories | Shared configured platform categories with no exclusive seller owner, no seller-category CRUD or hierarchy | Reference models need agreement. These are larger catalog changes, not just a dropdown restriction. |
| Seller/rider profile edits | Changes become visible after save, with re-verification flag for vehicle documents | Official personal/business/appearance changes wait for the relevant administrator to approve; old details stay active | **Earlier explicit user decision.** Uploading replacement licence/ORCR in the profile update remains a separate gap. |
| Buyer cancellation | Cancellation before Ready for Pickup is immediate | Same cutoff is enforced, but cancellation is a request requiring seller decision, auto-approved after **24 hours** if unanswered | **Earlier approved timer/process.** Ready-for-Pickup buyer cancellation is correctly blocked; it was checked directly. |
| Rider matching and limits | Online/Offline, nearest rider, 60-second offer, 3 jobs, automatic next rider after decline | Manual area-based dispatch, **15-minute acceptance**, **10 active deliveries** by default plus admin-verified vehicle/cargo capacity; no work availability toggle/nearest engine | Fifteen-minute timer follows earlier approved defaults. Remaining assignment model differs from the teacher's dispatch design. |
| Earnings and compensation | PHP30 pickup, PHP50 delivery credited immediately; seller funds available on completion | Configurable approved logistics costs allocated at settlement; seller funds require completion, seven-day delivery hold, verified funding/dispute checks and approval | Configurable rates/sandbox examples were explicitly requested. There is no evidence for adopting the workbook sample wages as actual operating rates. |
| Commission | A9 assumes **5%**, after seller-funded discounts | Current global setting/default is **10%**; no seller/category-specific rate interface | A commercial-rule difference requiring agreement. Existing order snapshots should not be rewritten. |
| Payments | Sandbox gateway To Pay → paid, failure/retry/cancel, one-hour unpaid order timer; DOCX also lists live card/bank methods | Operational COD plus full-wallet **local/testing sandbox** payments; no hosted card/bank/live e-wallet gateway or persistent unpaid retry workflow | The custom wallet sandbox is not the same as a payment provider's gateway sandbox. |
| Shipping | Workbook route table and whole-kg actual weight, rate changes from checkout start | Runtime is **PHP50 flat per selected shop**. Implemented approved tariff versions use greater of actual/volumetric weight and immutable order snapshots | Rate engine exists but is **not activated/configured** here; no approved tariffs were found. |
| Return restocking | Seller receipt restores returned stock | A separate seller inspection determines whether stock is restockable; damaged items may be excluded | Deliberate stronger custody/stock handling; exact teacher step differs. |
| Notifications | In-app, email and SMS; sometimes instantaneous updates | In-app plus queued mail dispatch, polling/refresh for counters; **no SMS provider/channel** | Delivery and real-time latency need separate external/browser checks. |
| Session expiry | 30 minutes idle | Inspected runtime/session configuration: **120 minutes** | Tab separation tests pass; idle expiry at the teacher's 30-minute limit does not align. |

Observed configuration is recorded in [runtime settings](audits/2026-11-13/runtime-settings.json). Only a small allowlist of non-secret settings was read. At audit time, financial sandbox configuration was disabled, no database sandbox override was present, and the approved tariff count was **zero**. Thus implemented sandbox wallet/rate paths should not be described as already enabled commercial payment features.

## Concrete gaps and failed teacher boundaries

These findings are distinct from intentional workflow choices. The case tables show the exact affected IDs and expected input limits.

### 1 Buyer account update validation differs from registration

The web account update accepts one-character buyer names and a non-mobile contact string. The audit HTTP probe saved `NOT-A-MOBILE`. There is no staged email change with OTP, and phone changes do not implement the teacher verification/duplicate contract. Strong registration validation does not automatically protect this separate profile-update route.

Evidence: [AccountController](../app/Http/Controllers/AccountController.php), `BUY-ACCT-002`, `006`–`009`. Password validation checks complexity/current password but does not prohibit reusing the same current password (`BUY-ACCT-005`).

### 2 Product create has permissive boundaries and duplicate submission

The audit created two products from two identical POSTs. The accepted payload had a one-character name, PHP 0.99, stock 100000, no description/image and a supported category different from the seller's business category. Both rows correctly required product compliance review, but the creation contract differs from the workbook.

Current creation rules use name max255/no min5; nullable description; price min0.01 without the teacher decimal/maximum constraints; stock min0 without a99999 ceiling; optional media. Product/variant idempotency is not universal simply because checkout itself is idempotent.

Evidence: [ProductController](../app/Http/Controllers/Seller/ProductController.php), `SEL-PROD-003`–`007`, `017`, checklist `2.2.2`, `6.3.4`.

### 3 Address book behavior differs

The audit saved **11 addresses**, because no ten-address ceiling is enforced. Deleting the default succeeds and automatically chooses an existing address, rather than requiring the buyer to choose another default first. The default consistency and order address snapshots are implemented; those portions align.

Evidence: [BuyerAddressService](../app/Services/BuyerAddressService.php), `BUY-ADDR-005`, `007`.

### 4 Return eligibility and evidence do not match the teacher flow

A completed order delivered 20 days ago accepted a dispute with no evidence file. Current disputes accept delivered **or completed** orders without a seven-day cutoff. Description is20–2000 characters and one optional JPG/PNG/PDF file, not10–500 plus1–5 mandatory photos. An administrator handles the case directly; there is no seller three-day response, auto-approved return or one-time escalation from seller rejection.

Evidence: [DisputeController](../app/Http/Controllers/Buyer/DisputeController.php), `BUY-RET-001`–`005`, `SEL-RET-001`–`006`, A11/A31. This does not mean arbitrary refunds are executable: authorization, physical return and financial checks still gate execution.

### 5 Reviews are only a simpler product star and text feature

The audit saved a **501-character** review on an order whose completion event was 40 days earlier. Current comment maximum is1000. There is no review photo collection, buyer once-only edit, seller reply/edit or separate seller/rider rating. Completed-order ownership and duplicate order/product review checks do exist.

Evidence: [ReviewController](../app/Http/Controllers/Buyer/ReviewController.php), [Review model](../app/Models/Review.php), `BUY-RATE-*`, `SEL-REV-001`–`004`.

### 6 Voucher format and campaigns need more than existing vouchers

A funded voucher named `BAD SYMBOL!` was accepted. Code normalization/uniqueness exists, but the4–12 alphanumeric constraint does not. The voucher accepted a discount greater than its zero minimum spend. Other gaps include future-start scheduling, code-only visibility, selected-product eligibility, teacher post-claim editing and simultaneous voucher stacking. New campaign funding/reservations are an extra project constraint, so teacher setup must fund the owner first before testing code validation in isolation.

Evidence: [SellerVoucherController](../app/Http/Controllers/Seller/SellerVoucherController.php), [Voucher](../app/Models/Voucher.php), `SEL-VOU-*`, `BUY-VOU-*`.

### 7 Delivery code lock recovery is different

The correct code stayed unusable after 16 minutes following the fifth wrong attempt. Current recovery requires buyer code refresh, not automatic unlock after15 minutes. Proof-file requirements also vary: delivery accepts JPG/PNG/PDF up to4MB rather than JPG/PNG5MB. Missing proof and the specific5.1MB invalid proof are nevertheless correctly rejected.

Evidence: [DeliveryCodeService](../app/Services/DeliveryCodeService.php), [CourierController](../app/Http/Controllers/Courier/CourierController.php), `RID-DLV-003`, `004`, A16/A32.

### 8 Payout request contracts differ

Seller order payout requests are per eligible order. Separately, wallet withdrawal accepts amounts belowPHP100 and permits several pending requests with different request keys: the audit acceptedPHP99.99 and anotherPHP100. This does not match the teacher's single pending balance-payout contract. Available-fund reservation, duplicate-key protection and administrator review exist.

Evidence: [WalletWithdrawalService](../app/Services/WalletWithdrawalService.php), [OrderFinancialService](../app/Services/OrderFinancialService.php), `SEL-EARN-005`–`007`.

### 9 Checkout does not fail early for an unmapped delivery area

Web checkout resolves a destination map but permits a null area ID. The later sorting/rider workflow enforces routing. The teacher requires an unserviceable address to be blocked **before placing the order**. Adding a logistics area alone also does not automatically create an approved price/serviceability contract.

Evidence: [CheckoutController](../app/Http/Controllers/Buyer/CheckoutController.php), `BUY-CHK-004`, `LOG-RATE-003`, `004`. This finding is from code inspection, not a claimed browser/device replay.

### 10 Several complete modules or subfeatures were not found

- Seller store-category hierarchy, reorder/hide/delete/multi-category rules; exclusive category ownership from the DOCX is also absent.
- Scheduled store price sales, flash-sale inventory/countdowns, flash/store-price split, bundles and Buy X Get Y. Existing SellerPromotion is an advertising-notification system, not a price engine.
- Product wishlist; shop following is implemented separately.
- Save as Draft, duplicate-to-draft, complete seller deactivate/reactivate and bulk catalog action workflows.
- Two-axis variation matrix and exact20-options-per-type rules; existing rows can represent combined labels but do not generate a Cartesian matrix.
- Rider Online/Offline work toggle, nearest-rider auto-assign/offer cascade and map/navigation action.
- Seven-day buyer order auto-completion; existing scheduled seller deadlines and cancellation timers do not implement it.
- Dedicated Lost/Damaged parcel states with automatic claim workflow; damaged-parcel failure reasons and evidence are different.
- Hub arbitrary manual override to Delivered. Current strict delivery proof/code controls remain intact.

Some checklist functions are optional, including bulk product import and separate seller-promotion approval. They should not be treated as mandatory failures unless your teachers confirm that scope.

### 11 Smaller differences worth retaining in the revision list

- Buyer search has no typo correction and no best-selling sort; price/latest/rating and stable pagination exist.
- Seller order list has status tabs, but not all requested date/search filters.
- Product/profile photos generally use2MB limits and accept WebP; chat allows documents up to5MB and2000 text characters, not an image-only/1000-character contract.
- Licence renewal requires a date after today, while the teacher expects expiry today accepted.
- Seller dashboard low-stock count is below10; buyer stock subscriptions use threshold5. The specified seller/variant once-per-crossing alert is not implemented.
- Rider suspension opens recovery exceptions instead of automatically putting every not-yet-picked-up job into Unassigned. Third failed/refused delivery needs physical hub recovery rather than automatic RTS.
- Analytics supports CSV/XLSX/PDF, while dedicated remittance/wallet/shipping statements are CSV only. Existing report scopes/recognition differ; the older seller report has no explicit executed-refund exclusion, whereas ledger/analytics do. This warrants a consistency check with valid historical refunded records, rather than assuming all report totals are interchangeable.

## What was actually executed

| Verification | Result and limits |
| --- | --- |
| Existing Laravel regression suite | **380 passed, 3,533 assertions**,129.108 seconds. Covers the current application contracts, including financial and parcel journeys. This is not319 teacher cases. The suite includes existing server API tests; no Flutter/device test was run. |
| Audit characterization probes | **9 passed, 32 assertions**. These probes deliberately confirm the observed implementation differences; a passing probe can therefore demonstrate a teacher-rule mismatch. |
| Runtime configuration query | Read-only, non-secret allowlist:120-minute sessions,10% commission,PHP50 flat shipping,15-minute rider acceptance,10 active-delivery cap, sandbox disabled, zero approved tariffs. |
| Attachment extraction | Read-only OOXML extraction, retained source SHA256 hashes, case IDs and sheet/row locations. No source workbook/Word modification or teacher checkbox sign-off. |
| Code inspection | Routes/controllers/services/models/migrations/Blade and relevant frontend scripts; no Flutter functionality assessment. |
| Browser/device acceptance | **Not run.** Responsive CSS and HTTP-render tests do not certify desktop/tablet/mobile interaction, scan-camera permissions, visual layout or domain/tunnel availability. |
| External services | Real SMTP delivery, SMS, live payment/bank/provider settlement, external address-service availability and performance/load tests **not verified**. |
| Build | Not rerun for this documentation/probe-only task; no application or asset source was changed. Previous build results were not counted as a new audit result. |

Regression/probe result summaries and their JUnit output are preserved in the local ignored `storage/logs/teacher-audit-*` and `storage/logs/teacher-guideline-probes.*` files. [Probe observations](audits/2026-11-13/probe-observations.json) and [probe source](audits/2026-11-13/TeacherGuidelineProbeTest.php) are retained with the audit. Probes used isolated SQLite in-memory transactions, fake notifications and fake storage; no real account, order, stock or funds were inserted into the configured application database.

The first probe attempt contained two incorrect route names and one incorrect assumption about cancellation cutoff. Those probe definitions were corrected against the actual routes/service. The final run passed all 9; the report records Ready-for-Pickup cancellation as aligned. Initial probe failures were not counted as application defects.

## How to use this audit for your next revision

1. **Agree on the business model first:** single-shop versus mixed-shop checkout; exclusive seller categories versus shared platform/store categories; one voucher versus stacking; seller-first versus admin-led disputes; commission, shipping and compensation schedules. Several references cannot be copied unchanged into the present system.
2. **Address validation and duplicate-submit gaps next:** buyer contact update, product input rules/media requirement, voucher codes, address rules, code lock behavior and destination serviceability. These have direct, bounded cases and several are already reproduced here.
3. **Build missing feature groups as coherent workflows:** seller category management, sale/flash/bundle pricing, wishlist, review media/edit/reply, rider availability/dispatch and return deadlines. A menu label alone would not satisfy the dependent cases.
4. **Retest with controlled source-equivalent fixtures:** approved accounts, category/rate setup, campaign budgets, stock and timestamps. Do this in an isolated audit database rather than resetting your live/demo records. Correct the source Assumptions columnD where you and your teachers intentionally choose a different rule.
5. **Run teacher acceptance separately:** record actual results, screenshots and browser/device/external checks in the source Pass/Fail/Actual Result columns after the agreed flow is exercised. Keep the source case IDs from this audit for traceability.

No fixes were silently applied during this audit. The detailed tables give you a revision backlog while preserving earlier approved project decisions.
