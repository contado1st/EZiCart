# Workflow and usability implementation audit

Date: 16 November 2026. Scope: Laravel pages and shared backend services. Prepared after the [assessment and implementation plan](workflow-usability-integration-plan.md), incorporating both user wallet decisions.

## Delivered changes

| Requested area | Result and implementation | Evidence |
| --- | --- | --- |
| Action feedback | Shared pending/result/error region in application, applicant and message layouts; native validation feedback; duplicate-submit protection; timeout warnings do not invent a result. Cart and product-copy handlers publish actual outcomes. Existing confirmation system retained. | `action-feedback.js`, component/CSS; frontend tests; six-role render test. |
| Short-task popups | Send, Receive, Redeem and Issue use native dialogs, one active at a time, labelled heading, keyboard close/focus return and progressive section/form fallback. Recipient review and posted receipts stay inside the wallet task. Existing Need Actions, messages and variation dialogs retained. | `short-task-dialogs.js`, `wallet-actions.js`, finance partials; fallback, deep-link, focus, timeout and receipt tests. |
| Upload optimization | Shared `ImageUploadProcessor` extracted from existing product processing. Product entrypoint remains compatible; new private review photos fit within 1600 x 1600 without upscaling, normalize orientation and re-encode. JPEG/PNG/WebP type and transparency handling retained. Existing profile avatar/banner cropping retained. | Product media, review integration, web operations and new real-image fixture tests; 2400 x 1200 review fixture becomes 1600 x 800 and fewer bytes. |
| Redundant Messages control | Dashboard top-bar duplicate removed. Floating launcher is now a real inbox link enhanced into the drawer. Chat pages retain their own inbox navigation. | All six role layouts render the retained launcher/link; dedicated chat behavior retained. |
| Staff ID | Authorized staff profile now presents photo/initials, approved identity, unique readable ID, status, contact, area, hub and applicable vehicle/license details, plus unique profile QR. Existing capacity form/history preserved. | Staff ID authorization and rider-badge regression tests. QR points to the protected profile; it is not a login or operational handover credential. |
| Action colors | Explicit success/approval, warning/recovery and danger/rejection variants. Decision-select changes update button and confirmation tone. Existing labels, focus and busy states retained. | Reactive decision-tone frontend tests; production build. |
| Admin/user wallet separation | Commerce uses existing platform treasury; logistics admin uses existing administration account; user wallets stay separate. Display owner/mode, available money, retained holds/reservations and relevant quick actions. | Financial ledger and wallet role tests. |
| Admin allocations | Admin can debit their available source and send to any of the four approved user modules, with recipient review and current-password confirmation. No user-to-admin transfer route added. | Cross-module admin allocations, insufficient/frozen funds, authentication and receipt-ownership tests. |
| Funding receipts/codes | Recipient-bound random secret, hash-only persistent storage, expiry, one-time issuer display, revocation while unused and exactly one balanced credit on redemption. Issuance itself credits nobody. | Wrong recipient, expiry, revocation, replay, sandbox gate, frozen accounts, scope, one-time secret and concurrent redemption tests. |
| User send/receive | Exact receiving reference/QR, server identity review, signed expiring review, positive bounded integer cents, current-password confirmation, deterministic locks, idempotent ledger posting, notifications and private receipts. | Balance preservation, changed details, expiry, replay, receipt IDOR and concurrent overspend tests. |
| Reduced controller complexity | Six existing storage/scan actions moved from `LogisticsController` into `Logistics/StorageController` with bodies and route contracts preserved. No new workflow engine or state vocabulary. | Logistics workflow and hub-transfer regressions. |

## Financial boundaries and security

- Approved buyers, sellers, couriers and sorting centers can send/receive demo funds. Admins allocate from their distinct available sources, including across user modules as requested.
- Code issuance retains existing commerce/logistics reviewer scope: buyers/sellers versus couriers/hubs. Codes create school credits from the balanced demo-issuance account only when redeemed; allocations transfer already funded balances. These are distinct actions.
- Old funding requests, independent admin funding review, holds, vouchers, checkout, escrow, refunds, payouts and remittances remain on their existing services. No opening balances or test credits were inserted into configured accounts.
- Raw codes are absent from URLs, ordinary notifications, ledger metadata and database secret fields. Database stores a hash. The no-JavaScript issuance fallback temporarily flashes the once-only secret in the protected session; AJAX returns it directly with no-store caching. Lost unused secrets must be revoked/reissued.
- A failed redemption clears the code before Laravel can flash old input. Posted receipt APIs do not expose the raw code or request secret. Only issuer/recipient or sender/recipient may read their respective receipts. Transfer receipt names are recorded snapshots.
- Existing sandbox activation and local/testing restrictions remain. No real money, external provider, bank transfer or QR Ph interoperability is added. Existing rate-limited authenticated routes and server-side authorization remain authoritative.

## Redundancy register

| Finding | Disposition |
| --- | --- |
| Top-bar Messages and floating Messages on the same dashboard | Removed top-bar duplicate; retained functional inbox fallback and unread drawer. |
| Separate photo/contact/location/vehicle fields repeated above staff details | Consolidated identity into ID summary; retained detailed capacity verification, biography, service areas and history. |
| Available balance repeated in overview and metric card | One prominent available amount; pending/held/reserved metrics retained. |
| Funding code versus admin Send | Explicitly different labels and funding explanations; issuance creates future demo credit, allocation debits available balance. |
| Reports, Analytics and Exports navigation | Flagged as overlapping discovery paths, retained because measures, formats and authorization differ. |
| Tracking, evidence, incidents, returns and recovery queues | Intentional specialist views of related parcels; retained distinct duties, history and access controls. |
| Pickup/arrival/possession/delivery confirmations | Retained distinct custody events and actors. A shorter UI must not remove proof or load checks. |
| Large logistics/auth/transition controllers | Storage extraction delivered. Broader rewrites deferred to separately tested changes; no financial/order process rewritten to reduce line count. |

## Compatibility

Existing API URLs, roles, status enums, request fields and envelopes remain intact. Product image public processing entrypoint remains intact. Storage/scan route names, URLs and middleware now point to the extracted controller. Existing rider badge credentials and handover flow remain intact. Tab-session middleware and form/CSRF handling remain intact.

The existing authenticated buyer API group gains additive wallet summary, recipient review/send, redemption and authorized receipt endpoints. API responses contain data rather than web fragments. Other role clients keep their existing contracts; this revision does not broaden the buyer API middleware. No Flutter file was changed and no new Flutter wallet UI is claimed.

## Verification

| Check | Observed result |
| --- | --- |
| Focused backend regression group | **232 passed; 2,343 assertions.** Finance ledger, demo funding, deferred wallet orders, dashboard navigation, review integration, product media, web operations, new wallet actions/usability, tab sessions, checkout integrity, logistics workflow, hub transfers and profile approval. |
| Separate real concurrent-process tests | **2 passed; 17 assertions.** Two sends cannot overspend the last balance; concurrent redemptions yield one credit/recorded result. Disposable SQLite fixture. |
| Full frontend glob | **129 passed.** Includes notifications, reactive decision tones, pending/timeouts, wallet fragments/restoration, dialog fallback/deep links/focus, and existing frontend behavior. |
| Production build | Passed; existing optional fontaine warning remains. No dependency added. |
| Blade compilation | Passed. |
| PHP formatting and whitespace | Pint passed on affected services/controllers/model/migration/tests; `git diff --check` passed. |
| Layout detector on added styles/finance/ID | No findings (`[]`). This is source inspection, not visual browser acceptance. |
| Additive configured-database migration | Applied successfully as batch 48; migration status Ran. No balance/credit seeding. |

Artifacts: `storage/logs/usability-final-recheck.txt`, `wallet-race-final.txt`, `usability-frontend-final.txt`, `usability-layout-detect.json`, `usability-migrations-after.txt`. These are local verification logs, not business receipts.

## Deployment and rollback

On another installation, back up normally, deploy compatible code/assets, then apply only the additive migration:

```powershell
php artisan migrate --path=database/migrations/2026_11_16_044443_create_demo_funding_codes_table.php --force
npm run build
php artisan view:cache
```

The wallet remains readable and new code actions are gated if the table is not yet present. No `.env` change or new dependency is needed. Use the existing sandbox controls; do not enable real-money operation. During rollback disable new writers and retain posted financial history. The migration refuses to drop a code table containing history; revert compatible application code rather than deleting receipts or credits.

## Acceptance limits

- Browser visual review is recorded separately below. Automated coverage does not certify every dashboard/action/device appearance or physical camera.
- Concurrent correctness was exercised with disposable SQLite processes. Live MySQL concurrent requests, deadlock behavior and throughput were not load-tested.
- Product/review images deliberately preserve full content; existing fixed avatar/banner cropping remains. A new interactive manual crop editor was not added. Existing media and private evidence/registration documents were not rewritten.
- Shared native-form feedback covers the main layouts; existing specialized AJAX handlers remain responsible for their own results. This is not a claim that every historical AJAX control has been manually exercised across all modules.
- Long forms, checkout, dispatch, private evidence and history remain pages/sections; they were not all converted to popups. New wallet tasks use dialogs, and existing specialized dialogs remain.
- No broad restyle, new real-money capability, Flutter UI implementation or deletion of legitimate workflow safeguards was performed.

Browser acceptance: read-only Chrome review on `shop.ezicart.trade` inspected the buyer wallet overview at desktop size and the Receive QR popup at desktop and a 502-pixel outer window (80% browser zoom). Escape closed the popup. Send was inspected without submitting. The review found shared flex-form wrapping put camera guidance alongside the amount; wallet task forms were changed to a single column and the final stacked Send form was visually verified after reload. Receive reference styling was also given a dedicated row. No funds were sent, codes issued/redeemed or camera permissions accepted during browser review. Existing balance/history were only viewed.

Browser access host was unavailable, so native-window fallback was used and needed target recovery. Staff ID, admin code issuance UI, every other role and a physical phone/camera remain manual visual acceptance items; their relevant server/role behavior has automated coverage. The visible pre-existing top-navigation gradient was outside this task and was not restyled. Automated dialog tests verify focus return; native accessibility capture retained address-bar focus metadata, so that capture is not evidence of manual keyboard-focus acceptance.
