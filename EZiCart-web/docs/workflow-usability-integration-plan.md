# Workflow and usability integration plan

Prepared 16 November 2026. Scope: Laravel/web implementation and shared server behavior; preserve the current Flutter client's existing API contracts. This plan precedes implementation. Items below are not completed merely because they are documented.

## Objective and constraints

Reduce the effort needed to understand and complete an existing task without replacing the application's workflow or visual identity. Retain role authorization, tab-specific sessions, order status values, custody checks, vehicle/load checks, approval history, private files, and ledger accounting. Keep the current neutral dashboard frames, typography, Tabler icons, and role accents. No real-money provider, new frontend framework, wholesale dashboard redesign, or new generic workflow engine is proposed.

Use the existing services, Blade components, native dialogs, JavaScript, PHP GD, QR generator, and financial ledger. Do not add dependencies without approval. New persistence must use additive migrations; historical migrations and existing records remain intact.

## Assessment of the current implementation

| Area | Existing implementation | Revision needed |
| --- | --- | --- |
| Order flow | `OrderTransitionService`, `OrderProgressService`, workflow attention, custody/history and scoped role queues | Consolidate presentation of the next action and contextual links; retain all physical and financial gates. |
| Action feedback | React decision dialog, page loading, cart feedback, flash/validation components | Inventory missing feedback; provide consistent pending/result/error notifications without reporting success before the server commits. |
| Pop-ups | Need Actions dialog, message drawer, confirmation dialog, variation selection | Reuse these patterns for short tasks; avoid replacing long forms, history, checkout or multi-step receiving with stacked dialogs. |
| Images | `ProductUploadProcessor` resizes/re-encodes product images; `ProfileAppearanceService` crops/re-encodes avatars/banners | Review photos are stored directly by `ReviewService`; extend a shared, bounded processor. Retain existing profile cropping; assess a manual crop editor separately. |
| Messaging entry points | Workspace top-bar icon plus floating drawer launcher; dedicated chat page suppresses the floating launcher | Remove the top-bar duplicate on dashboards only after verifying the retained entry point for each role and no-JavaScript fallback. |
| Staff profiles | Authorized verified details, avatar, capacity verification and revision history; rider operational QR already exists | Present a reusable ID-style profile summary with a unique QR and preserve access to approval controls/history. |
| Action colors | Several button families and decision tones; state meanings vary | Normalize semantic variants through shared styles and explicit attributes; do not infer a destructive operation from button text. |
| Wallet | Balanced ledger, available/pending/held/payout balances, sandbox funding requests, independent review, escrow/refunds/payouts | Clarify ownership; add scoped admin-issued funding codes, admin allocations and user transfers, receipts, and wallet-oriented navigation. |
| Mobile | Existing token-authenticated JSON APIs; buyer mobile `ApiService` uses bearer authentication | Preserve URLs, fields, statuses, response envelopes and error semantics; add capabilities without requiring a simultaneous Flutter update. |

## Confirmed wallet decisions

- Approved buyer, seller, courier and sorting-center wallets may send/receive demo funds.
- User wallets and admin treasury/administration balances stay distinct.
- Administrators may send their authorized existing balance to approved buyer, seller, courier and hub wallets across modules. This is an admin allocation, separate from a user-to-user transfer. Code issuance continues to use the existing commerce/logistics funding-review scope.
- Funding codes are bound to a named recipient, single-use, expiring, and accompanied by a receipt.
- Existing financial sandbox restrictions remain. These are school-testing credits, not real cash or GCash/Maya interoperability.

## 1. Baseline and compatibility checks

1. Record the affected named web routes and existing API request/response contracts before changes.
2. Review actual Flutter callers alongside Laravel routes, especially checkout, product media, review, profile, notifications and authentication.
3. Run the relevant existing suites against the isolated test database. Never use a fresh/reset migration on the configured application database.
4. Preserve old deep links and form actions. UI changes must continue working with ordinary navigation when JavaScript cannot enhance them.
5. Keep the same shared checkout, inventory, order-transition and ledger authority for every caller. A web-only layout must not become a mobile business-rule exception.

**Acceptance:** Existing mobile integration tests pass; no removed/renamed API route, changed existing status, or new required field for an old client. Record any additive API separately. Flutter source is inspected, not silently revised.

## 2. Feedback and button semantics

Build on the existing decision dialog rather than adding a second confirmation system.

- Writes: validate first, show a pending state, prevent duplicate submission, then show the actual committed result or actionable failure.
- Full-page writes: use existing server flash/error bags to populate one shared notification region. Preserve inline field errors and entered values.
- Asynchronous writes: send the same feedback events to that region from the relevant handler. Do not globally intercept `fetch` or guess success from a click or HTTP redirect.
- Clipboard/download/local actions: report copy failure, file preparation failure or completion accurately. Download preparation is not proof that the user saved a file.
- Tabs, filters, opening/closing drawers and navigation: retain selected/expanded/loading feedback. Do not add a confirmation modal or success toast to every ordinary navigation click.
- Notifications: use escaped text, bounded stacking, keyboard-accessible dismissal, polite success announcements and persistent actionable errors. Avoid duplicate inline-plus-toast success announcements.
- Colors: primary/submit, neutral/secondary, success/approve, warning/recovery, and danger/delete/reject get explicit variants with readable contrast, labels and disabled/busy states. Color is not the only indicator.

**Acceptance:** Each inventoried action has a named feedback path; cancelled/invalid/failed actions never show a success message; keyboard focus, submitter names, CSRF, file uploads and method spoofing survive enhancement.

## 3. Pop-ups, contextual work and redundancy

### Suitable short tasks

Wallet Send, Receive, Redeem code, issue funding code, receipt details; staff ID preview; short profile/approval detail preview; existing variation, confirmation and Need Actions interactions.

Use a single active dialog where possible. Reuse authorized server actions and validation. Give dialogs a heading, close action, focus containment/return, Escape handling, controlled scrolling, and a normal page fallback. Do not fetch an entire application layout into another layout with duplicate scripts/session bootstrapping.

### Keep as pages or sections

Checkout, full product editing, full staff edit/history, multi-parcel dispatch, evidence-heavy disputes, reporting and long review tables. These need space, deep links and reliable recovery.

### Consolidation

- Show current state, responsible role, blocker and next authorized action consistently from the existing progress services.
- Group parcel-specific tracking, evidence, incidents and return links around the parcel; retain specialist operational queues and their permissions.
- Hide specialist/empty controls unless relevant to the current role, state and task; do not remove access to historical records.
- Remove dashboard top-bar Messages only where the floating launcher or visible inbox navigation provides the same destination, scope and unread information. Keep a working inbox link if the launcher fails to initialize; keep chat pages' own inbox navigation.
- Flag Reports / Sales exports / Analytics as overlapping navigation candidates, not duplicate business features: they currently serve different measures and formats.
- Flag logistics company / sorting center / hub and courier / rider / transport driver terminology for consistent labels without changing stored role identifiers.
- Flag overloaded controllers for a later, behavior-preserving extraction. Do not combine that refactor with a broad financial rewrite merely to reduce line counts.

**Acceptance:** No dead end, removed capability, cross-role exposure or lost deep link. A user can find the next action without visiting multiple unrelated pages. Record each redundancy as confirmed, context-dependent, or intentional before removing it.

## 4. Image optimization and crop handling

Reuse GD and the existing upload checks; no new imaging package is needed.

- Server processing remains authoritative for web and API uploads. Validate content/type, file size and decoded pixel count before expensive processing.
- Normalize orientation, resize with resampling, re-encode with appropriate quality and remove metadata from display assets.
- Fit product/review images within documented bounds without upscaling or automatically cutting off products, text or review evidence. Preserve transparency where needed.
- Keep fixed avatar/banner crop behavior. A new manual crop editor is optional follow-up, not required for bounded server optimization. Do not silently crop product, review or evidence content.
- Add review-photo optimization through the shared service while retaining private storage, MIME correctness, ownership checks, removal history and transaction-failure cleanup.
- Distinguish display assets from originals. Do not destructively recompress uploaded licenses, registration papers, dispute/evidence documents or existing stored files as a side effect of this revision.
- Keep existing media URLs valid. Use lazy loading and reserved dimensions for off-screen display images, with original-size/full-view access where the current surface requires it.
- Benchmark original and resulting bytes, dimensions, orientation, transparency and visual legibility using fixtures; a smaller image is not acceptable if essential content becomes unreadable.

**Acceptance:** New product/review/profile uploads have bounded decoded dimensions and correct MIME/extension; private photos remain private; existing mobile payloads remain accepted; processing failures leave neither orphan records nor broken old media.

## 5. Staff ID presentation

Add an ID-style summary inside the existing authorized staff profile, with a pop-up preview where useful.

- Show approved name/photo, role, readable unique ID, account status, assigned hub/operating area, relevant contact details and rider vehicle/license-expiry/capacity information when the viewer is authorized.
- Reuse existing rider badge identity for operational rider scanning; do not rotate or replace `EZR:` credentials for a visual revision.
- Give other staff a unique profile-identification QR. Profile-identification QR and operational handover credentials must have explicit, different purposes.
- Scanning a profile QR never logs anyone in or reveals private documents. Resolve through existing authorization; a QR does not grant permission.
- Keep original profile-edit approval, history, private papers and admin capacity verification accessible in their existing contexts.
- Missing/unapproved values show their real state. Do not fabricate verification, issue dates, expiry dates or signatures.

**Acceptance:** IDs/QRs identify the correct person; unrelated users cannot read protected profiles or papers; existing rider handover tests still pass; long names and missing photos fit on narrow screens.

## 6. Demo wallet and admin funding

### Ownership and screen hierarchy

The source currently displays `platform:treasury` for commerce admins and a user-scoped available account for logistics admins. Preserve the ledger ownership but label it accurately: Platform treasury versus Logistics administration balance. Logistics pools, user available funds, pending earnings, holds, escrow and reserved voucher budgets are not interchangeable balances.

Wallet layout: clear owner/mode and available balance; primary Send / Receive / Redeem actions for eligible users; recent transactions; tabs for history, funding and existing withdrawals. Admins additionally see Issue demo code, Allocate funds, review queues and the relevant treasury/pool summaries. Retain existing financial records and exports inside Finance & Wallet.

Use familiar balance/action/receipt hierarchy from wallet applications, adapted to EZiCart's fonts, colors and neutral frames. Do not copy another application's branding or claim bank/QR Ph compatibility.

### A. Admin-issued demo credit code

1. An approved administrator selects an eligible recipient within the existing commerce/logistics review scope, amount, reason and expiry.
2. The server creates a unique receipt/reference and cryptographically random code, binds recipient/amount/issuer/expiry, and stores a hash of the redeemable secret. Raw codes do not go in logs, URLs, ordinary notifications or transaction metadata.
3. Issuance alone does not credit the recipient. The authorized issuer gets the code/receipt through a protected response; any later secret re-display requires an explicit secure design rather than plaintext storage.
4. The named recipient redeems while signed in. The server locks the code/account, verifies status/expiry/scope/freeze flags, and posts the balanced demo funding transaction once.
5. The receipt distinguishes Issued / Redeemed / Expired / Revoked, amount, recipient, issuer, timestamps and posted ledger reference. A receipt is evidence of status, not a second redeemable credential.
6. Pending codes may be revoked by an authorized admin. Redeemed ledger entries cannot be deleted or converted back to an unused code.
7. Keep old reviewed funding requests/history functional for compatibility; the revised screen gives the new issuance path priority and explains the legacy request path.

### B. Admin sends an existing balance

This debits the authorized admin treasury/administration available account and credits an eligible recipient's available wallet. Label the action Admin allocation. It cannot spend pending earnings, held funds, order escrow, reserved voucher budgets or another administrator's unauthorized pool. Code issuance and allocation must never both credit the same instruction.

### C. User sends and receives

1. Receive shows a wallet reference/QR identifying the receiving account; it contains no session token, password, balance or withdrawal permission.
2. Send resolves an exact recipient reference or QR, then displays the recipient identity, amount and demo mode before confirmation. Do not provide an unrestricted user directory.
3. Require current-password verification for sending, role/status/freeze checks, positive integer-cent amounts, configured transaction limits, no self-transfer, and enough available balance.
4. Lock accounts in deterministic order and use idempotency keys plus a canonical request hash. Retry with the same request yields one transfer; changed details with the same key are rejected.
5. Post one balanced debit/credit through `FinancialLedgerService`. Notify both parties after commit and show a protected receipt. Failed transfers change neither balance.
6. User transfers do not fund admin treasuries. Administrators use the separate allocation operation. Cross-module user transfers do not bypass recipient approval or freeze rules.

### Additive persistence and deployment

Finalize the minimum new code/redemption and transfer-reference fields/tables after checking existing ledger metadata/idempotency contracts. Add foreign keys, unique code hashes/references and lookup indexes. Keep legacy funding enums/routes readable rather than rewriting historical rows.

Apply additive migrations before enabling new readers/writers; gate new actions when unavailable. A rollback preserves posted ledger history and disables new actions rather than deleting used financial records. Do not automatically enable production-mode funding or change `.env`.

**Acceptance:** Test wrong recipient, invalid/expired/revoked/replayed code, double redemption, two concurrent sends, insufficient funds, held/frozen accounts, unauthorized admin scope, session cross-account isolation, receipt IDOR, transaction rollback and balanced ledger totals. Existing checkout, vouchers, refunds, payouts and remittances continue to pass.

## Implementation order and checkpoints

| Batch | Work | Required evidence before advancing |
| --- | --- | --- |
| 0 | Baseline, detailed action/redundancy inventory, mobile contract map | Source findings, preserved-contract list and focused baseline results |
| 1 | Shared feedback/button variants; confirmed redundant Messages control | Frontend behavior checks, role render checks, desktop/narrow visual review |
| 2 | Image pipeline extension; retain existing profile cropping | Real-image fixtures, byte/dimension/MIME checks, private-file and failure cleanup regressions |
| 3 | Staff ID presentation and authorized QR preview | Staff visibility/history tests, rider badge regression, responsive review |
| 4 | Demo-code and send/receive domain services, additive schema and authorization | Ledger/security/concurrency tests before UI activation |
| 5 | Wallet dialogs, receipts, role explanations and contextual navigation | End-to-end demo scenarios and existing finance/checkout integration tests |
| 6 | Remaining confirmed navigation redundancies; tightly scoped controller extraction only where justified | Same route/action contracts, happy-path and exception regressions |
| 7 | Final audit and documentation | Completed/deferred findings, exact tests, browser/device limits, migration/rollback notes |

Do not label a whole batch complete because one role or one happy path passes. Record partial browser access separately from automated evidence. Browser automation has previously had intermittent URL-detection failures; report those limitations instead of declaring every module visually verified.

## Test and audit coverage

- Existing focused suites include `WebModuleSessionTest`, `CheckoutIntegrityTest`, `CheckoutConcurrencyTest`, `LogisticsWorkflowTest`, `HubTransferFlowTest`, `WorkflowAttentionTest`, `WebOperationsTest`, `ProfileChangeApprovalTest`, `ProductMediaTest`, review integration tests, `FinancialLedgerWorkflowTest`, `DemoWalletFundingTest` and `DeferredWalletOrderTest`.
- Add behavior tests for changed decisions rather than CSS snapshots or declarations repeated as assertions. Test database/financial work in isolated fixtures; no persistent demo credits or production account changes during verification.
- Use the actual production asset build and Blade compilation checks. Review desktop/narrow layouts for buyer, seller, courier, hub and both admin surfaces in bounded passes.
- Final audit maps every requested goal to implemented files, behavioral evidence, preserved mobile contracts, confirmed redundancies removed, intentional overlaps retained, deployment steps and unverified conditions.

## Research references

- [Maya Send Money](https://www.maya.ph/send-money): recipient/detail review as a familiar interaction reference.
- [GCash sending with QR](https://help.gcash.com/hc/en-us/articles/900005327223-How-to-send-money-via-QR-Code): receiving-identity QR as a familiar interaction reference, not a claim of interoperability.
- [PHP resampling](https://www.php.net/imagecopyresampled) and [WebP encoding](https://www.php.net/manual/en/function.imagewebp.php): reuse installed GD capabilities rather than introducing another image stack.

## Current status

Implementation followed the assessment and the user's wallet decisions. Shared feedback, short wallet tasks, image processing, staff ID presentation, demo funding codes, admin allocations and user transfers are implemented. Six logistics storage actions were extracted with their existing route and authorization contracts retained. The additive funding-code migration was applied on 16 November 2026; it creates no balances or credits.

Final automated verification: 232 focused backend tests (2,343 assertions), two separate concurrent-operation tests (17 assertions), 129 frontend tests, production build and Blade compilation passed. New layout detector findings: none. See the [implementation audit](workflow-usability-implementation-audit.md) for the exact scope, deployment/rollback instructions and manual-acceptance limits. No Flutter source or existing API contract was revised. Larger workflow/controller redesigns and a new manual crop editor were deliberately excluded from this incremental revision.
