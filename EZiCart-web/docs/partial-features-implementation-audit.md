# Partial feature implementation audit — accounts, catalog, vouchers, reports, feedback and rider availability

Started: **13 November 2026**. Updated: **14 November 2026**. Scope: **Laravel web only**. Baseline revision: `f232491600787515c70dcc97fd00b0c3fc6e7ef3`. The earlier teacher audit used a different historical revision; its results were not silently carried forward as current test results.

**Delivery status: account, catalog, restricted pending-seller drafts, P3 vouchers, seller reporting, P4 discovery/review/enquiry revisions and the P6 rider-availability subphase implemented.** There are **34 case revisions delivered and 20 remaining**; availability enforcement is implemented/tested but remains disabled pending rider choices and announced activation. One remaining case has existing-payment buyer tab work delivered but its provider-dependent portion remains gated. These are code delivery counts, not executed teacher passes or a completion percentage. The first sections record P1; dated continuations record later packages. Browser acceptance remains blocked. See the [54-case progress matrix](audits/2026-11-13/partial-implementation-progress.json).

## Preflight and preservation

The repeatable [preflight script](../scripts/partial-features-preflight.php) reads counts/schema metadata only. It does not output addresses, account emails, credentials or document contents.

Observed configured database: MySQL. Canonical email collision groups: **0**. Normalized store-wide SKU collision groups: **0**. Saved addresses missing street: **0**. No refund-execution states were present. Existing review uniqueness and order-item variation snapshots were confirmed in source/schema rather than recreated. GD and ZIP extensions are installed.

A private users-table SQL backup was saved under `storage/app/private/backups/` before the schema change. The temporary dump credentials file and one-off backup helper were removed. The backup was created successfully; restoration was **not** exercised against the configured database. Do not publish this backup: it contains account data.

No teacher/demo accounts were inserted. No product, order, price, payment balance, commission, tariff, stock or voucher rules were changed. Seller/rider profile review, original session-per-tab behavior, cart/Buy Now and physical parcel workflows remain in place. The migration adds a default security counter to existing users without changing their account identities/statuses.

## Implemented changes

| Area / teacher case | Before | Implemented behavior | Main files |
| --- | --- | --- | --- |
| Profile validation — SEL-PROF-002, BUY-ACCT-001 | One-character names and invalid mobile strings accepted by the general account update route. | First/last names require at least two characters. Contact must use `09XXXXXXXXX` or equivalent `+639XXXXXXXXX`. Blank business name remains rejected for sellers. A new/changed location requires region, province, municipality, barangay and street/location description. | `AccountController` |
| Legacy profile addresses — SEL-PROF-002 | Partial direct updates could resolve omitted location fields to empty strings. | Resolve against existing geographic values. Unchanged incomplete legacy location can remain during unrelated updates; changing it requires completion. Staff changes still create an admin-reviewed revision. | `AccountController`, existing `StaffProfileService` |
| Saved addresses — BUY-ADDR-003 | Street field optional when creating/updating an address. | Street/location description required server-side. House number remains optional for rural addresses. Existing PSGC/manual hierarchy validation remains. | `BuyerAddressController` |
| Password change — BUY-ACCT-005 | Current password could be reused as the new password. | Reuse fails before password/session mutation; existing complexity, confirmation, current-password and other-device invalidation remain. Array/non-string password payloads fail validation. | `AccountController` |
| Profile photo — BUY-ACCT-001 | Two-megabyte application/client limit. | Avatar limit is five megabytes; banners keep two megabytes. Existing dimensions, supported formats, private storage, JPEG re-encoding and staff appearance approval remain. Preview validates the corresponding avatar/banner limit. | `ProfileAppearanceController`, `profiles.js`, appearance Blade view |
| Buyer email change — BUY-ACCT-008 | Login email read-only. | Buyer submits new email and current password, then verifies a six-digit code sent to the new address. Old email remains active until verification. Names/contacts cannot be changed through this endpoint. | `EmailChangeController`, `BuyerEmailChangeService`, account Blade view |
| Email security — BUY-ACCT-008 | No dedicated workflow. | Canonical global duplicate checks before delivery and at confirmation; one durable challenge per buyer; hashed code; ten-minute expiry; five-attempt maximum; one-minute resend cooldown; purpose separation from registration/reset OTP; password/current-email binding; single-use transactional commit. Other users' codes cannot be used. Database unique-email failures become field errors. | New challenge table/service/notification |
| Session revocation after email change | No email-change event. | Increment buyer security version, rotate current session, preserve it with the new version; stale sessions for the same buyer are rejected on protected web requests. Other accounts' sessions remain valid. Fresh login records the current version. Rotate remember credentials. | `EnsureAccountActive`, `AuthController`, `EmailChangeController`, `User` |
| Sensitive input | Custom email-password field had no exclusion because the flow did not exist. | `email_password` excluded from flashed validation input and service parameter marked sensitive. Security counter hidden from serialized user responses. Old email receives a change notice after commit; mail failure there does not undo the verified change. | `bootstrap/app.php`, service, `User` |

Email-code delivery is synchronous to provide immediate send-failure feedback, following the existing recovery notification pattern. A failed send deletes only that specific pending challenge and reports a retryable validation error. No real email was sent during automated verification.

## Schema and activation

Applied to configured MySQL: `2026_11_13_223745_add_buyer_email_change_challenges`.

- Adds `users.web_auth_version` with default zero.
- Adds `buyer_email_changes`, keyed by buyer ID, with reference, current/proposed email, password fingerprint, hashed code, attempts and expiry/timestamps.
- No external dependencies were added, historical migrations edited or database reset performed.
- Code deployment elsewhere requires this migration **before** the email-change routes are used. If route caching is used, rebuild the route cache through the normal deployment process.
- For rollback, disable email-change writes and drain requests first. Prefer a compatible forward fix. Do not roll back by dropping the security counter while stale sessions exist: its version change is part of session revocation. Keep current authentication middleware/counter or invalidate affected sessions before reverting. A migration `down()` is structurally reversible, not a guarantee of a safe live rollback.

## Verification actually performed

| Check | Result |
| --- | --- |
| Full Laravel suite, final run | **394 passed; 3,637 assertions; 71.565 seconds** |
| Account + logistics targeted run after fixture update | **103 passed; 975 assertions** |
| Earlier focused profile/address/approval/tab-session run | **56 passed; 470 assertions** before the final additional resend test |
| Profile frontend suite | **6 passed**, including new 2–5 MB avatar preview and retained banner rejection |
| Pint changed-file check | Passed after formatting; run again for final diff |
| Preflight MySQL read-only inventory | Passed |
| Additive migration on configured MySQL | Applied successfully |
| Real MySQL concurrent email-claim test | **Not run**; request lifecycle/race recheck tested on isolated SQLite, unique database email constraint retained |
| Browser layout, real email provider delivery and real HTTP upload at 5 MB | **Not run** |
| Vite production build | Not needed for these public-JavaScript/Blade changes; not run |

Local test logs are under ignored `storage/logs/partial-p1-*.txt`. PHPUnit used the existing SQLite in-memory setup, fake storage/notifications and a process-only bcrypt override. The configured MySQL database was not used for test fixtures. One initial full-suite failure was an existing account-edit fixture missing the newly required region; the fixture now includes region and still verifies role/identity-field protection and password/session effects. The avatar rejection fixture was moved from 3 MB to above 5 MB to reflect the approved new limit, with a separate acceptance test at 3 MB. No tests were removed.

Node's test runner initially hit sandbox `EPERM`; its approved rerun passed. An initial Artisan filter invocation was rejected by Windows shell parsing before tests ran; direct PHPUnit was used for the recorded runs. A formatter import-placement issue in the preflight helper was corrected and the helper rerun successfully.

## Limitations and remaining work

- CLI PHP reports `upload_max_filesize=2M`, `post_max_size=8M`. Application validation/preview supports 5 MB avatars, but the actual serving PHP/web-server limit must also permit them. The existing `scripts/serve-media.ps1` starts its server with 24 MB uploads/64 MB POST bodies. It must be used/restarted on the intended port for local larger uploads, or equivalent limits configured on the actual host. Host-wide PHP configuration was not changed. The HTTP serving process may have different limits from the inspected CLI.
- Profile and appearance remain separate saves with separate feedback. Staff/admin email changes are not enabled. Phone OTP/duplicate-contact policy and the remaining teacher field-length/media conventions are not declared complete merely because profile validation improved.
- The teacher's immediate official staff-update behavior still differs from administrator review. Preserving unchanged incomplete legacy addresses also deliberately avoids blocking unrelated edits; it is not unconditional rejection of every historical blank.
- Web security version checks apply to protected web sessions; this package does not revoke mobile API tokens or implement Flutter changes.
- Pending-applicant drafts, catalog lifecycle/history/SKU ranges, scheduled vouchers, wishlist/search/reviews/chat context, financial reports, rider availability and logistics changes are **not implemented in this package**. Actual provider payments remain separately gated.
- No original teacher Pass/Fail checkboxes were filled. The progress matrix tracks implementation/testing separately from teacher alignment. The original 54 Partial classifications are historical; no blanket reclassification was made.

## Next package

Follow the reviewed execution order: P2 approved-seller drafts, seller availability, historical image protection and store SKU/price ranges; pending-seller authentication remains a separate subphase. Before any publication change, inventory every public/purchase/stock-alert consumer and ensure new private draft media has an authorized storage path. Continue the same per-case implementation audit as later packages are delivered.

## Catalog continuation — 13 November 2026

This continuation supersedes the first package's "Next package" status. It delivers the approved-seller part of P2. It addresses six further Partial scenarios (`SEL-PROD-008`, `010`, `011`, `015`, `SEL-VAR-006`, `007`), bringing delivered case revisions to **11 of 54**. Delivery is not a teacher pass count. `SEL-PROF-011` remains gated because pending accounts retain their original login restriction.

### Product behavior delivered

| Case / concern | Implemented behavior | Evidence |
| --- | --- | --- |
| SEL-PROD-010 — Draft publication | Approved seller can save incomplete work privately, reopen it and submit through existing full product validation/compliance review. Failed submission retains private files. Row lock and submission marker make replay return the original submission without duplicate products. | `PartialCatalogIntegrationTest`, `ProductDraftService`, `ProductDraftController`, `ProductController` |
| Draft media privacy | Cover, gallery, video and variation images are stored on the private disk, with owner-authorized download links. New gallery selection replaces saved gallery; explicit removal and changed variation attributes retire the corresponding private file. Successful submission copies media through existing product upload handling and retires private originals after commit. Cleanup failures are reported without deleting a committed product's files. | Draft privacy, publication/media, removal and deletion tests |
| SEL-PROD-011 — Pause/resume | Inventory has **Pause sales / Resume sales**. This flag is separate from compliance and archive. Public discovery, PDP, recommendations, shop pages, cart, checkout and stock alerts respect it. Resume never clears a compliance flag or archived state. Existing cart lines remain with an unavailable explanation. | Pause/resume, cart and compliance-isolation tests; existing regression suites |
| SEL-PROD-015 — History images | New purchase lines record the selected variant image or cover path alongside existing name/price/variant snapshots. File cleanup retains any path referenced by a purchase. Referenced products are archived under a product lock instead of deleted, protecting order items against concurrent checkout/removal. Buyer/seller order detail shows the recorded image. | End-to-end checkout → image replacement → archive → both order detail views test |
| SEL-VAR-006 — Store SKU uniqueness | Trimmed case-insensitive nonempty SKU uniqueness now covers the entire seller's catalog, including retired variants. Editor returns field errors; generated normalized key plus store ownership and unique index enforce it even if the editor is bypassed. Another store may use the same SKU. Existing SKU can remain while editing stock. | HTTP validation/update tests, SQLite database enforcement test and MySQL temporary-table constraint probe |
| SEL-VAR-007 — Variant price ranges | Storefront/search/shop/recommendation cards and PDP show min–max price across active stocked variants. Selecting a variant shows its exact price; clearing selection restores the range. Web search/shop price sort and web search price filters use the lowest available variant price. Aggregate subqueries supply card ranges without a PHP query for each card. | Range, price-filter/order and frontend selection tests |
| SEL-PROD-008 — Weight boundaries | Existing positive weight and supplied-dimension rules retained and checked directly. Invalid zero/negative/non-numeric weight and zero supplied dimensions fail; 0.01 kg stores exactly 10 g. | Weight/dimension integration test. Whole-kg teacher pricing remains a preserved tariff difference. |

The obsolete Archive checkbox in Edit Product did not have a matching update handler. It now explains the working Inventory availability controls instead of implying an ignored form field changes visibility. Inventory removal/archive functionality remains available.

### Design refinement and compatibility

Private drafts use a separate `product_drafts` table rather than filling `products` with placeholder prices/categories/stock and then changing every catalog reader to hide them. This keeps current buyer queries and compliance contracts intact and reduces the exposure risk. A submitted draft records its product ID and timestamp. Submitted draft records are not reusable workspaces; edit the real product afterwards.

The new visibility predicate and marketplace scope share the seller-active rule. One existing Laravel API product-detail guard was updated to respect the same rule so direct server requests cannot bypass a paused listing; Flutter files and mobile UI were not changed. Existing sold-out visibility behavior was retained rather than applying one overly broad predicate everywhere.

Historical image snapshots are **paths retained for new purchases**, not guessed backfills. Old orders lacking a recorded image say "Purchase image not recorded". Existing images are stored under generated names; app-level replacement creates a new file. No historical price, name, variant or photo was rewritten. Backup does not imply restoration or browser inspection was performed.

### Schema and rollout evidence

Applied `2026_11_13_225736_add_catalog_availability_drafts_and_purchase_images` to configured MySQL after a private full-database dump under `storage/app/private/backups/`. Temporary dump credentials and the one-off operation helper were removed. No application/user credentials were printed.

- Adds `products.is_seller_active`, default true, preserving existing listings.
- Adds indexed nullable `order_items.purchased_image_path`; no speculative history backfill.
- Adds variant store ownership and a stored computed normalized SKU, plus unique `(seller_id, canonical_sku)`. Conflict inventory runs before DDL. Ownership backfill uses chunks of 200; the inspected database had **zero variant rows** to backfill, so a populated production backfill was not exercised here.
- Adds private draft payload/media metadata and submission marker. No demo drafts/products were inserted into live business tables.
- MySQL check cloned the migrated variant table into a **connection-local temporary table**. It rejected same-store case/whitespace duplicates and accepted another store's identical SKU plus empty/null SKUs. Temporary table was dropped. This verifies actual MySQL generated/index behavior, not a timed concurrent transaction test.
- Post-check live counts: **15 products, 0 variants, 0 drafts**, unchanged by the check; variant ownership mismatches: zero.

During SQLite verification, a table rebuild initially discarded the computed-column definition. The migration was corrected **before it was applied to configured MySQL**, adding that column after the ownership nullability change. The unique-index behavior test now passes. A fresh Laravel Request is also created when hydrating saved media, avoiding Laravel's previously cached file list.

Rollout elsewhere requires deploying the migration and all compatible variant writers together before accepting product writes. Old writers that omit the now-required store ownership will fail rather than bypass SKU enforcement. Do not drop the new columns/tables as a routine live rollback: that destroys drafts, history image references and uniqueness guarantees. Preserve private/public media and database backups and use a compatible forward fix. No third-party dependency was installed.

### Catalog checks and remaining limits

Final release suite: **409 tests passed / 3,760 assertions / 70.804 seconds** (`partial-p2-release-suite.txt`). The targeted search/catalog run passed **24 tests / 182 assertions** after explicit numeric casts corrected SQLite's expression-versus-text comparison. An earlier broad suite passed 406 tests; a later price-filter run exposed that comparison issue, which was fixed before the final passing release suite. Product selection/media frontend suites passed **6 tests**, including restoring the price range after clearing a selection. Pint and `git diff --check` passed. Local logs: `storage/logs/partial-p2-*.txt` and `partial-p2-mysql-check.json`.

Real browser responsive review, playable-video/large-upload checks, timed MySQL concurrency and backup restoration were **not performed**. HTTP responses/views and media authorization were tested through Laravel; file/notification fakes kept test fixtures out of live storage/data. PHP/web-server upload ceilings remain the separate hosting limitation described in P1.

At the end of the 13 November catalog package, pending-seller draft access remained unavailable. The dated continuation below implements that separate restricted-auth subphase and P3. New catalog capability does not enable multi-shop checkout, bypass compliance, change shipping formulas or fulfill the teacher's 60-second dispatch/immediate earnings assumptions.

## Pending-seller drafts and P3 continuation — 14 November 2026

### Restricted applicant workspace — SEL-PROF-011

Previously, every pending seller was denied login, including access to private product drafts. A password-authenticated, email-verified seller whose application is still pending now receives a separate **30-minute draft capability**. The ordinary Laravel web guard remains signed out. Unverified applicants, suspended accounts and pending accounts in other roles cannot obtain it.

The capability is bound to the tab selector and a fingerprint of the current password, email and web authentication version. Each request checks the current account state and expiry. Logout, a password/security change, approval, suspension or expiry revokes access. Successful approval does not automatically turn this capability into an operational session: sign in again. Existing owned drafts are retained and available after approved login.

The dedicated `/applicant/drafts` routes permit only creating, reading, updating and deleting the applicant's own incomplete drafts and accessing their private draft media. They do not grant product publishing, order, messaging, payout, staff or API-token access. CSRF, owner checks, private/no-store responses and a locked account recheck on draft writes remain enforced. Approved sellers keep their normal inventory/draft workflow. No existing approval middleware was relaxed.

Main implementation: `ApplicantDraftSession`, `AuthenticateApplicantDraft`, `ProductDraftController`, `ProductDraftService`, the auth login branch, applicant routes and `layouts/applicant.blade.php`. `ApplicantDraftWorkspaceTest` covers six scenarios, including cross-owner denial, tab/expiry binding, revocation and approval retaining drafts. The existing pending-seller registration test now expects this restricted workspace while still asserting the normal guard is a guest.

### Voucher and cancellation revisions

| Teacher case | Implemented behavior | Preserved rule or limit |
| --- | --- | --- |
| SEL-VOU-001 | Seller and platform creation show required maximum promotion liability, available sponsor funds and shortfall before submission. Frozen available accounts show zero spendable funds. | Server-side reserve/ledger checks are authoritative; a browser preview never creates funds. Scheduled offers reserve immediately. |
| SEL-VOU-007 | Optional start timestamp, expiry after start, and per-buyer limit no higher than total usage. A claimed offer cannot be rescheduled. Platform percentage offers also require a cap. | Capped, fully funded 100% discounts remain valid; this intentionally differs from the workbook's blanket rejection. |
| SEL-VOU-010 | Ending an offer stops new use and releases unused reserved funds. Saved claims remain unavailable history with a reason. Confirmation text describes the actual operation. | Existing order discounts, redemption records and claim history are preserved. |
| BUY-VOU-004 | Integer-cent eligibility reports the exact additional eligible spend: 299.99 against a 300 minimum reports 0.01. | Shop/item/shipping scope and one-voucher checkout rules remain enforced. |
| BUY-VOU-005 | Shared eligibility distinguishes not started, expired, exhausted, paused/ended, unavailable shop, funding and minimum-spend reasons. Claim, quote and available-offer queries respect scheduling. | Invalid code and per-buyer/recipient restrictions remain enforced through existing pricing/claim paths. |
| BUY-VOU-012 | Approved cancellation restores recorded voucher usage once and explains current saved-offer eligibility. Naturally exhausted offers can become usable again only when the returned funds cover all remaining capped liability. | Manual pause/removal, expiry or insufficient funding never silently renews an offer. No automatic sponsor-wallet top-up. |
| SEL-INV-003 | Buyer/seller order detail explains that stock and voucher use remain reserved while cancellation is pending; approval releases stock and eligible use once. | Existing approval, Ready-for-Pickup cutoff and 24-hour unanswered-request policy remain unchanged. |

`VoucherEligibilityService` supplies the common reasons; `VoucherScheduleRules` supplies date/limit bounds. Available-now queries also exclude budgeted offers with a released, frozen or insufficient reserve. Legacy offers without a budget retain compatibility. Null start times preserve immediate-start behavior; historical inactive offers with no recorded reason are not guessed to be naturally exhausted and revived.

`VoucherFundingService` retains recorded redemptions and ledger transfers. On eligible cancellation it restores the actual discount from order escrow. Reactivation requires the remaining reserve plus returned discount to cover cap × remaining permitted uses. Otherwise returned funds go to the original sponsor account and the offer requires explicit reactivation/funding. A discount below its cap may therefore remain unavailable after cancellation. Repeated cancellation/restoration does not duplicate stock, usage or refunds.

Buyer voucher navigation separates **Available and upcoming** from **Saved vouchers and history**. Future offers display their start time without a claim button. Saved history belongs to the signed-in buyer. Cancellation notifications now report the actual supported sandbox wallet refund; COD cancellation does not claim that an uncollected payment was refunded. This improves the supported wallet branch of SEL-ORD-005 / BUY-CAN-003, but their external-provider requirements remain gated and are **not** included in the 19 delivered revisions.

### Migration and configured-database checks

Created a private full-database MySQL dump, `before-voucher-revision-20261114-011915.sql`, under `storage/app/private/backups/` using [the backup helper](../scripts/backup-partial-feature-database.php). Credentials are passed in a temporary private options file, removed afterwards; neither credentials nor dump contents were printed. Restoration was not tested.

Applied `2026_11_13_234515_add_voucher_scheduling_and_disable_reason` successfully. It adds nullable `starts_at` and `disabled_reason`; it does not rewrite existing offers, balances or campaign history. [The read-only verification script](../scripts/verify-voucher-revision.php) confirmed both columns and executed the available-now query against MySQL. The configured database contained **zero vouchers**, so this establishes schema/query compatibility, not a populated historical migration or live financial test. No campaign funds, offers or demo accounts were inserted into live tables.

Deploy this migration before code that queries its columns. Old application versions ignore future start timestamps: disable affected voucher writes/use before a rollback, or ensure scheduled offers stay inactive under old code. Prefer a compatible forward fix. Do not drop scheduling, disable reasons or financial history as a routine rollback. Existing settlement rates, commission, financial configuration, dependencies and `.env` were not changed.

### Verification and browser limitation

- Final PHP regression: **429 passed / 3,909 assertions / 78.126 seconds**, recorded in local ignored `storage/logs/partial-p3-release-suite.txt`.
- Focused applicant/voucher run: **20 passed / 149 assertions**. Fourteen voucher tests cover exact time/spend boundaries, role/claim restrictions, funded 100% offers, history isolation, frozen budgets, pending cancellation, one-time approved restoration, manual pause, expiry and insufficient returned funding.
- Frontend run: **15 passed** across four voucher-budget-preview tests and eleven tab-session regression tests. This is a separate run from the earlier twelve profile/product frontend tests.
- PHP suites use isolated SQLite test data and fake storage/notifications. MySQL timed transaction races, provider payment/email delivery, backup restoration and real large-upload/media playback checks were not performed.

Browser discovery was retried on **14 November**. The browser inventory remained empty with **“The browser access host is unavailable.”** The prior Windows attempt also could not establish a permitted browser URL context. No alternate automation was used to bypass that restriction. Consequently responsive appearance, actual clicks, back/forward/tab interaction and visual acceptance are **not verified**, despite passing HTTP/frontend tests.

An isolated loopback QA site and synthetic accounts are prepared using [the QA preparer](../scripts/prepare-browser-qa.php), [bootstrap](../scripts/browser-qa-bootstrap.php) and [router](../scripts/browser-qa-router.php). It uses a separate SQLite database, storage roots, session cookie, array mail and synchronous queue. The isolated migration ran and retained existing fixtures. This preparation is not a browser pass and did not seed live business records.

At this checkpoint P5 was next. Its seller portion is recorded below. P4 discovery/reviews/chat, P6 riders and P7 logistics remain planned; P8 external payments remains independently gated. The combined P9 teacher walkthrough, all 54-case reassessment and browser acceptance remain outstanding. No teacher workbook Pass/Fail cells were changed and no revision was committed or pushed.

## P5 seller reporting continuation — 14 November 2026

### Findings and integrated revisions

| Teacher case | Finding | Revision |
| --- | --- | --- |
| SEL-ORD-001 | In Packing counted both CONFIRMED and PREPARING but listed only PREPARING. Seller order lists lacked buyer/order search and placement dates. | Added validated order/buyer/recipient search, optional start/end placement dates, a status selector and reset. Counts share the seller/search/date base query; packing list includes both statuses. Tab/pagination links retain filters, with 25 orders per page. Search binds parameters and treats `%`, `_` and `!` as literal input. |
| SEL-RET-007 | The older seller report summed COMPLETED order fields without executed refund adjustments; rows and summary used different discount arithmetic. | Reused `SalesReportService` for report rows, cents-based totals, itemized CSV/PDF links and analytics. Gross activity is retained separately from executed merchandise refunds and subsidy reversals. Authorized-only refunds are not reported as money returned. |
| SEL-EARN-003 | A contradictory historical COMPLETED/refunded record could remain in sales/completion figures. Labels also called sales “profit” and all completed rows “settled transactions.” | Cancelled/void orders contribute zero recognized sales. Full refunds contribute zero net seller sales and no completed-sale count; partially refunded completed sales keep their completed count. Sales, recognized commission and recorded pending/settled payouts are separately labeled. No invented product costs, fixed 10% label or claim that completion means settlement. |
| SEL-EARN-007 | Order payout requests already use the locked order and unique order payout record; wallet requests already use a per-owner key and account lock. Explanation and failed-form continuity needed improvement. | Preserved both existing pathways and their guards. Added payout eligibility explanation, wallet reservation confirmation and retained withdrawal key/amount/destination after validation failure. Replays reserve once; reusing a key with a different amount returns conflict. No duplicate payout pathway was added. |

The report uses the same **order-placement date cohort/application timezone** as the itemized export. Lifecycle, refunds and payout records are their current recorded state, even when adjustments happened after placement. Date filters no longer reset on export. Report rows paginate at 25; summaries iterate matching orders in batches of 200 and cover the full filtered cohort. Printing explicitly covers the current page, while CSV/PDF downloads cover matching records under the existing export limits. Empty ranges show zero totals and a no-data message.

The shared recognition calculation excludes cancelled orders and VOID finance records, even if contradictory old delivery/refund timestamps exist. Original refund activity remains visible; no refund, commission, ledger or snapshot is rewritten to repair a display. Existing commission recognition remains completed + no executed refund + verified remittance. Partial-refund fixtures exercise defensive read arithmetic only; this package does **not** add partial-refund execution.

### Reconciliation example from isolated tests

The fixture has six matching orders: a normal completed sale, a completed full refund, a completed partial refund, a cancelled record with contradictory historical refund data, a completed record whose finance is VOID, and a completed sale with an authorized-but-unexecuted refund. A different seller and a previous-month order are excluded.

| Figure | Expected PHP / count |
| --- | ---: |
| Gross merchandise activity | 4,000.00 |
| Seller-funded discounts | 320.00 |
| All executed refund activity, including the contradictory cancelled history | 2,000.00 |
| Recognized commission | 144.00 |
| Net seller sales | 2,516.00 |
| Completed sales excluding full refunds/voids | 3 |
| Matching orders | 6 |
| Actual recorded pending / settled payouts | 848.00 / 848.00 |

Full-refund net is zero; the partial-refund row has PHP820 net under the existing commission policy, and each of the two unrefunded completed sales has PHP848. CSV net totals and the analytics net metric both equal PHP2,516. Finance snapshots are unchanged and no ledger transaction is written by these reads. Contradictory and partial-refund fixtures are not claims that those states can be created through the normal workflow.

### Checks and release limits

The new seller-report suite passed **11 tests / 79 assertions**. Combined seller-report and analytics verification passed **19 tests / 201 assertions** after correcting an initial overly broad count exclusion: partial refunds retain their existing completed-sale count, while full refunds do not. The earlier whole-suite attempt had 439 passed / 1 failed because it detected that compatibility issue; the passing release result is recorded below after the correction.

`npm run build` succeeded with Vite 8.3.4 after the sandbox blocked its worker process with `spawn EPERM` and the approved build was rerun outside that sandbox. The existing optional `fontaine` font-fallback warning remains; no dependency was added. Existing layout/report styles and components were reused; report action buttons can wrap at narrow widths. Browser visual acceptance is still blocked as described above.

Final release regression: **440 tests passed / 3,988 assertions / 80.584 seconds**, recorded in ignored `storage/logs/partial-p5-final-release-suite.txt`. Pint and `git diff --check` passed. The 54-case progress record retains all original case IDs, source references and unexecuted teacher-test flags; local documentation links were checked separately. These results supersede the failed first P5 whole-suite attempt above, rather than omitting that finding.

No migration or live financial write is required for this reporting package. Existing holds, payout authorization, funded promotion accounting and unique/idempotent payout controls remain authoritative. Timed MySQL races were not exercised; sequential retry/conflict behavior is verified, with database constraints/locks retained. Buyer order-tab grouping (BUY-TRK-001, shared P5/P8 work) remains planned; this is the **seller portion** of P5, not a claim that all of P5 or the programme is complete.

## Buyer order groups and P4 discovery continuation — 14 November 2026

### Delivered scope and preserved behavior

| Case | Before | Integrated revision and verification |
| --- | --- | --- |
| BUY-TRK-001 — existing-payment portion; provider portion still gated | Closed orders mixed completed, cancelled and returned parcels. Return-in-transit appeared under To receive. Status filtering and group counts did not share one scope. | One primary group per order: Preparing to ship, To receive, Completed, Cancelled, Returns & refunds, or Other history. Cancelled takes priority over stale dispute/refund records. Open disputes and refund records appear under Returns & refunds; pending cancellation alone stays in preparation. Same owner/status base feeds counts and lists, with 25 per page and retained filters/reset. Unknown historical states remain accessible. No fictional To Pay tab for COD or paid sandbox wallet purchases. |
| BUY-SRCH-004 | Literal search had no spelling suggestions. | Preserve exact results and the honest empty state; after zero matches, offer up to three close visible product titles. `ProductDiscoveryService` evaluates at most 300 filtered titles, uses bounded normalized edit distance, and preserves price/category/location/rating filters on suggestion links. The source `Ratan baskit` fixture suggests `Rattan basket`; unrelated nonsense yields none. Links and titles are escaped. |
| BUY-SRCH-009 | No best-selling sort; other sorts and pagination already existed. | Search and shop pages can sort by units on completed, non-void orders with no executed refund. Aggregate in SQL per product and break ties by descending product ID. Existing price/rating/latest/relevance options remain. Search pages keep 24 products and shop pages 20, within the requested 20–30 range. Tests cover cancelled/delivered/refunded/void exclusions, seller scope and an unchanged 25-product page boundary. |
| BUY-WISH-005 | Shop following existed; persistent product wishlist was absent. Guest follow links lost shop context. | Added a private buyer-owned wishlist, sidebar/account navigation and a heart control on the product page. Explicit desired saved state is idempotent; the database also enforces unique buyer/product identity. Logout retains entries. The list has 25 per page; unavailable entries remain removable and do not disclose newly private product details. Wishlist changes never change carts/orders or reserve stock. Guest save/follow links use authenticated GET return paths; saving/following still requires the user's explicit POST after login. Existing shop following stays separate. |

`BuyerOrderGroupingService` centralizes the primary-group CASE expression and count query. The controller eager-loads disputes and reviews, replacing per-item review queries in the purchases view. Order lifecycle, cancellation, payout and custody data are not changed by navigation/grouping.

Best-selling is a **completed non-refunded units ranking**, not a newly inferred net-return-quantity metric. Refund records contain amounts rather than per-item returned quantities. Any executed refund therefore excludes that order's units from ranking, including partial refunds; seller financial reports still retain their separately documented partial-refund sales/count arithmetic. No invented returned-unit allocation or rewritten sales snapshots. Authorized but unexecuted refunds are not counted as executed adjustments.

Spelling suggestions are optional and bounded, not a comprehensive vocabulary index or automatic query replacement. Product titles longer than the comparison bound are skipped, and relevant names beyond the first 300 visible filtered candidates may not be suggested. No external search service, dependency or whole-catalog PHP hydration was added. A production-scale SQL performance benchmark was not performed.

### Catalog visibility compatibility correction

The new no-results fixture exposed an existing gap: approved product rows could remain publicly discoverable after their seller became unapproved/suspended. `Product::availableForMarketplace()` and `isListingVisible()` now require an approved seller account as well as product availability/compliance. Seller vacation/closure is still a separate existing order-acceptance rule; it does not silently turn into administrative suspension.

One full regression then found that a Laravel API reader intentionally loads only public seller fields, omitting role/status. Visibility now performs a narrow approval-existence query when those loaded fields are absent, rather than incorrectly treating omitted fields as denial or expanding the public response. The existing API variation-image/privacy test passes again. No Flutter file, API response shape, financial configuration or dependency was changed.

### Additive migration and MySQL evidence

Created private full-database backup `before-discovery-revision-20261114-015925.sql` under `storage/app/private/backups/`, then applied `2026_11_14_015159_create_product_wishlists_table`. The table contains buyer/product foreign keys, timestamps and the unique pair; account/product deletion cascades only these preference entries. No existing table or financial record is deleted or rewritten. The configured table initially contains **zero wishlist rows**; no live wishlist, demo account or order was inserted. Backup restoration was not tested.

The [read-only discovery probe](../scripts/verify-discovery-revision.php) found MySQL `ONLY_FULL_GROUP_BY` rejected aggregating the correlated group CASE directly. Count aggregation now uses a projected derived table, preserving strict SQL settings. The corrected probe confirms the MySQL schema/unique pair and executes the count, wishlist and ranking queries using an empty synthetic owner/cohort. This checks SQL compatibility, **not** a populated MySQL workflow or timed race. SQLite integration fixtures verify populated grouping/ranking behavior; duplicate identity is also rejected through a direct database insertion test.

Deploy the additive wishlist migration before the new wishlist-aware readers. A rollback should disable new wishlist routes/readers and retain saved preference data, rather than routinely dropping the table. Existing account/cart/order data and private media remain intact. The isolated browser QA database was migrated without replacing its prepared fixtures.

### Verification checkpoint

The new focused suite passed **16 tests / 146 assertions**; combined discovery and existing web operations passed **30 tests / 309 assertions** after the partial-seller-field compatibility fix. An earlier 42-test catalog/search run also passed before adding the final three tests. The first discovery run identified one catalog visibility issue plus two corrected fixture mistakes (cart table name and a guarded account-status update). The first full run had 452 passing / 1 failing test because of the partial-seller-field issue; that behavior was corrected and retested rather than weakening the privacy assertion.

`npm run build` passed with Vite 8.3.4. Its existing optional `fontaine` fallback warning remains. Browser discovery was retried again on 14 November: no browser surfaces were available and the host returned **“The browser access host is unavailable.”** No native-browser bypass was attempted; visual, responsive, real multi-tab and back/forward acceptance remains unverified. HTTP authentication/authorization and existing regression tests do not substitute for that browser acceptance.

Final release regression: **456 tests passed / 4,134 assertions / 86.579 seconds**, recorded in ignored `storage/logs/partial-p4-discovery-release-suite.txt`. Pint and `git diff --check` passed. The read-only MySQL probe's final output is retained in `partial-p4-discovery-mysql.txt`; populated behavioral fixtures remain isolated SQLite tests. Existing frontend test totals in the matrix are historical separate runs; this package changed no frontend JavaScript and makes no claim of a new Node test run. The frontend production build was run for the updated Blade/navigation surfaces.

BUY-TRK-001 remains **partially delivered** in the matrix because genuine pending provider-payment attempts/To Pay require P8. The three discovery/wishlist cases increase delivered revisions from 23 to 26. P4 review photos/edit/history, seller review management and product-context messaging remain planned, followed by the rider/logistics packages and combined acceptance audit. Teacher attachments and their Pass/Fail cells were not changed; nothing was committed or pushed.

## P4 reviews and product enquiries continuation — 14 November 2026

### Case revisions and user behavior

| Case | Before | Implemented revision |
| --- | --- | --- |
| BUY-RATE-001 | Completed buyers could submit stars/text only. Public reviews displayed the buyer's first name/profile image and had no purchased-option context. | Review forms in purchases/order detail now accept up to five JPG/PNG/WebP photos, 5 MB each, with a 4096 × 4096 dimension ceiling. Files are stored privately and served through checked routes. Public reviews use masked names and a generic account icon. The server records all purchased variant labels and product-name snapshot from order items; client-supplied context is ignored. Old reviews use actual recorded order items, without inventing missing history. |
| BUY-RATE-003 | Duplicate-create guard and unique order/product identity existed, but there was no owner edit/history path. | Preserve that unique identity; add owner-only editing with a locked version check and private revision history. Retried creates cannot become silent edits. Stale edits fail with refresh feedback and staged-upload cleanup. Removed photos leave the public/current set but remain in private history; only the owning buyer and relevant commerce administrators may access removed photo files. Current photos on retired products remain available to the owning buyer/original seller/admin, while guest access requires a visible listing. |
| SEL-REV-001 | No dedicated seller review management/filter page. | **Customer reviews** navigation opens a read-only seller-scoped table with rating, with/without text, with/without photos, posting dates, reset, totals and 25-row pagination. Mask buyer names and display all purchased option labels/current photos. Historical reviews survive product retirement. Sellers cannot open the buyer edit/history page or edit buyer content. |
| BUY-CHAT-001 | Product-page chat buttons opened a private conversation without passing the product. | Carry a product ID into the enquiry composer and authorize it against the actual buyer/seller pair. The submitted message contains a server-owned title/advertised price/image reference alongside the buyer's question and existing attachments. Opening/reopening a thread sends no message or notification. Plain replies stay plain. Other-shop, hidden-product, nonparticipant and seller-forged buyer-enquiry requests fail. |

Review creation still requires an owned completed purchase and a product actually present in that order. It does not add a 30-day deadline, shorten the existing 1,000-character text limit or add another review per purchased variant. One product review displays all applicable purchased variants. An already-posted review remains editable through order history after a return; that does not permit creating a new review for an uncompleted purchase.

`ReviewService` stages validated files, rechecks ownership/eligibility under the order/review locks, enforces five current photos, and records version snapshots. Any transactional failure removes only newly staged files. Revision rows store rating/text and photo IDs, not publicly served file paths. Initial legacy content is recorded on its first edit; earlier unrecorded edits are not reconstructed. Public photo responses check review/photo membership, current/removed state, visibility or participant role, stored MIME and file existence, with no-store, nosniff and sandbox headers. File paths and new internal snapshot/version fields are hidden from ordinary model serialization.

PDP review rating/count aggregates still represent one current review row per order/product. Editing updates that row rather than adding another rating. The public **With photos** filter selects current photo records, not removed history. The shared review form retains the original feedback placeholder and existing create success/duplicate messages used by the end-to-end parcel journey.

### Shared Laravel API writer correction

Inspection found the existing API review writer used `user_id` against a table whose owner column is `buyer_id`, and attempted `updateOrCreate` without completed-purchase/item checks. It now calls the same review service for creation, purchase context and history recording. The valid response keeps its success/message/review envelope; new internal metadata is hidden. A repeat create now returns validation failure rather than overwriting the existing review; owner editing remains the explicit web edit action. API ownership, incomplete-order, unpurchased-product and duplicate tests pass. This is a shared Laravel data-integrity correction; no Flutter UI or mobile review-photo/edit interface was implemented or claimed.

### Product enquiry snapshots and media retention

`DirectMessagingService` rechecks approved participants and the product's actual seller/visibility when sending, under its existing locks. The browser cannot supply a replacement recipient, title, price or image path. A product card alone does not satisfy message submission: the buyer sends text and/or a supported attachment through the existing composer. Card context is removed from the redirect after send so subsequent plain messages do not automatically repeat it. Starting/reopening from the product still does not send a message.

Historical cards retain the title/advertised price and original product image path even after listing changes. The image is an existing public marketing image reference, **not** a copied private chat attachment. A participant-checked image route serves it in the conversation, and `ProductMediaService` retains images referenced by an enquiry as it already does for purchase snapshots. This preserves history without exposing conversation metadata to outsiders. Normal attachment files remain in private storage. Existing order context, one-to-one contacts, notifications, unread counts and file attachments remain in place.

### Migration, rollout and verification context

Created private full-MySQL backup `before-review-enquiry-revision-20261114-022602.sql`, then applied `2026_11_14_021149_add_review_media_history_and_message_product_context`. It adds nullable review purchase context, version default 1, `review_photos`, `review_revisions` with unique review/version, and nullable direct-message product context. The existing unique order/product review constraint is reused unchanged. No historical variant/media values were guessed or mass-rewritten.

The [read-only review/enquiry probe](../scripts/verify-review-enquiry-revision.php) confirms MySQL columns, both uniqueness contracts, seller/media filtering and JSON image-reference SQL. At verification, the configured database contained **0 reviews, 0 review photos, 0 revisions and 0 contextual messages**. These are schema/empty-query checks, not populated MySQL workflow or timed concurrent-transaction tests. Tests use isolated SQLite and fake storage/notifications; no live feedback, message, account or order was inserted. The browser QA database was migrated while retaining its existing synthetic fixtures. Backup restoration was not tested.

Deploy the migration before the new readers/writers. Retain private review files/history and enquiry image-reference retention rules during rollback. Old review writers can bypass history, and old product cleanup can delete an enquiry image: disable those writes/cleanup or keep a compatible version while fixing forward. Do not drop the new tables/columns as a routine rollback after users start writing them. No `.env`, rates, settlement, custody, dependency or Flutter changes were made.

The focused final suite passed **21 tests / 188 assertions**, including shared API guards. The full professor seller-to-buyer journey passed separately (**1 test / 86 assertions**) after restoring its existing feedback placeholder. Earlier new-test attempts corrected fixture mistakes (a required product category, identical seller/buyer fixture names affecting a masking assertion, and replicated parcel-code uniqueness). An initial full run passed 474/475 and exposed the missing placeholder; the implementation retained that interface instead of changing the acceptance assertion.

`npm run build` passed with Vite 8.3.4; the existing optional `fontaine` warning remains. No new JavaScript/dependency was added. Browser discovery was retried and again returned an empty inventory with **“The browser access host is unavailable.”** No browser bypass was attempted. Responsive appearance, actual browser upload ceilings, large-file network transfer, multi-tab visual behavior and external email delivery remain unverified. Application-level 5 MB validation is tested using fake uploads; hosts still need adequate upload/post limits as described earlier.

Final release regression: **477 tests passed / 4,322 assertions / 88.128 seconds**, recorded in ignored `storage/logs/partial-p4-feedback-final-release-suite.txt`. Pint and `git diff --check` passed. The final read-only MySQL probe again confirmed zero new feedback/history/enquiry records and the required schema/index/query contracts. These passing results follow the documented placeholder fix and shared API writer correction; the earlier failed run is retained as a finding, not reported as a pass.

These four case revisions increase delivered revisions from 26 to 30. The next planned package is **P6 rider work eligibility/history and safe exception guidance**, then P7 logistics reporting/serviceability and the combined P9 walkthrough. Twenty-four cases remain, including the independently gated provider-payment portions. Teacher Pass/Fail cells remain untouched; no agent commit or push was performed.

## P6 rider availability, suspension provenance and workload subphase — 14 November 2026

### Case revisions and preservation

| Case | Finding | Integrated revision |
| --- | --- | --- |
| RID-PROF-006 | Approval and last-seen presence did not express willingness for new work. | Manual Not chosen / Available / Unavailable choice, separately guarded by fresh approved-account checks. Suspension always wins, including before availability activation. Suspended-in-custody parcels retain the existing exception/recovery workflow. |
| LOG-RIDER-005 | Logistics suspension source did not identify which hub suspended the rider; changed hub membership could let another hub reactivate. | Record `suspending_hub_id`; only that hub within current scope can restore its own suspension. Administrator/other-hub/unknown legacy provenance cannot be overridden by the hub. Reactivation leaves availability Unavailable and preserves vehicle verification, capacity and accepted work. |
| LOG-RIDER-006 | The roster mixed account activity and eligibility and omitted failed-delivery workload/today's completions. | Separate account/manual availability labels; show reserved/held parcel count and weight, verified capacity, active local jobs/configured maximum, today's delivered count and existing lifetime outcomes. Batch workload summaries cover all reservation types; roster retains 20-row pagination. |
| LOG-DISP-003 | Approved riders could receive work without an explicit willingness check. | Shared new-work candidate predicate and locked server checks at pickup offer/claim, last-mile assignment/acceptance/automatic handoff acceptance, hub-driver release/replacement and buyer-return assignment/acceptance. Voluntary unavailability permits accepted work to continue. Preparation-mode flag leaves existing availability behavior unchanged until announced activation. |

Manual availability is **not online presence**. This preserves the approved plan's intentional distinction from a literal “offline means unavailable” teacher rule. Existing 15-minute offer deadlines, configured local-job limit, geographic scope, dedicated-driver separation, licence checks, verified kg capacity, financial rules and physical scan/code/proof requirements remain authoritative. No feature, test or prior workflow was deleted to match a workbook checkbox.

### Choice changes, offers and history

`RiderWorkAvailabilityService` locks the current rider and affected orders. Version checks reject a stale opposite choice from another tab; replaying the current choice is idempotent. Only the authenticated approved rider can submit their choice. The new page uses the existing confirmation, operational form and navigation components; its dashboard form is collapsed by default. No new JavaScript or dependency was introduced. New internal availability/provenance fields are hidden from normal API serialization.

Under enforcement, becoming unavailable withdraws only safely unaccepted pickup, last-mile and buyer-return offers. Last-mile orders return to Sorted with their active assignment released; pickups/returns keep actual parcel status/custody. Each withdrawal records audit/tracking events and appropriate logistics/participant notifications. Accepted jobs and in-transit hub manifests stay assigned. Background reconciliation uses a null system actor, not an invented user. Choice changes and releases invalidate related hub statistics after commit. Retries add no second release event and create no ledger entry.

Legacy last-mile assignments without a matching active acceptance record, unclaimed pickups with arrival/handover evidence and unaccepted buyer returns with custody evidence are flagged for review and never automatically cleared. The readiness command provides read-only counts and a failing `--check` gate; reconciliation previews by default and refuses `--apply` when enforcement is disabled. An every-minute non-overlapping scheduled reconciliation is conditional on active enforcement. It does not operate during preparation mode.

### Migration and configured-database evidence

Created private full-MySQL backup `before-rider-availability-revision-20261114-031536.sql`, then applied `2026_11_14_025201_add_rider_work_availability_and_suspending_hub`. New fields default to unknown/version 0 with nullable choice time and nullable suspending-hub foreign key. Older choices or suspension ownership were not inferred or backfilled. Backup restoration was not exercised.

The [read-only schema/count probe](../scripts/verify-rider-availability-revision.php) passed on MySQL: all four fields and the self-referencing hub foreign key exist, current-mode candidate and ambiguity queries execute, and no unknown suspension owner was fabricated. Counts at verification: **7 approved riders; 0 Available; 0 Unavailable; 7 Not chosen; 0 flagged acceptance/custody ambiguities; 0 legacy logistics suspensions without recorded hub**. Current-mode candidate count is 7 because enforcement is disabled; this is not a claim that each rider passes every area/licence/cargo check. `riders:availability-audit --check --json` correctly returned exit 1 / not ready for activation. No configured rider choices, offers, parcels, finance records or messages were changed. No configured `--apply`, flag activation or `.env` edit was performed. The isolated browser QA database was migrated while retaining its synthetic fixtures.

Deploy schema first; collect choices, repeat readiness, announce activation, deliberately enable the flag in every application/worker process, refresh dashboard cache and reconcile only safely unaccepted offers. Detailed steps and rollback limits are in the [operations guide](web-operations-user-guide.md). Keep new schema/provenance/history during rollback; disabling enforcement does not restore withdrawn offers. Older code may bypass the new checks, so use compatible code or pause new-work writes while fixing forward.

### Verification and remaining work

The focused suite passed **18 tests / 132 assertions**, including preparation-mode compatibility, role/owner/version guards, retry safety, all three offer withdrawals, accepted pickup/handoff/return continuity, real handover/scan requirements, missing verification/expired licence, hub-driver allocation, ambiguous legacy records, safe scheduled reconciliation and original-hub-only reactivation. Combined capacity/hub-transfer/logistics coverage passed **119 tests / 1,142 assertions** before the two final additional focused cases. An initial full regression passed **493 tests / 4,439 assertions / 95.260 seconds**; the final release result after those extra cases is recorded below.

`npm run build` passed with Vite 8.3.4; the existing optional `fontaine` warning remains. Browser discovery was retried: empty browser inventory and “The browser access host is unavailable.” Visual, responsive and real multi-tab browser acceptance remain blocked. SQLite/fake-notification tests prove sequential conflicts and retries; no timed MySQL suspension/availability/assignment race, live notification delivery or complete populated MySQL flow is claimed.

These four code revisions increase delivered revisions to **34**, with **20 remaining**. Availability activation is explicitly **pending**, not teacher acceptance. P6 profile/history, pickup/delivery and exception guidance remain next, followed by P7 logistics counts/tracking/serviceability/exports and P9 combined acceptance. Provider-dependent P8 remains independently gated. All 54 original source IDs/references and unexecuted teacher flags are retained; teacher checkboxes are unchanged. No commit or push was performed.

Final release regression: **495 tests passed / 4,454 assertions / 90.432 seconds**, recorded in ignored `storage/logs/partial-p6-availability-final-release-suite.txt`. Pint and `git diff --check` passed. The 54-case matrix still matches the original Partial source cases and retains unexecuted teacher flags. Enforcement is still disabled; the configured readiness gate remains blocked by seven unchosen riders, not by an application test failure. This completes the availability subphase only.
