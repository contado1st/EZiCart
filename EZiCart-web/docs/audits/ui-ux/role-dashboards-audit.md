# Role dashboard continuation audit

15 November 2026. Scope: marketplace admin, logistics admin, rider and sorting-center dashboards. This continues the [first UI pass](m6-audit.md) and follows the [role dashboard plan](role-dashboards-plan.md).

## Implemented

| Area | Before | Result |
| --- | --- | --- |
| Marketplace approval card | Reject could not supply the reason required by the server | Labeled, unique reason field with the existing required 5–1000 character limits; direct application/document review link; spaced wrapping actions |
| Logistics administration | Main hub-transfer link was detached from the action hierarchy | Hub transfers is the local primary action, COD/remittances remains secondary, matching topbar duplicate suppressed |
| Logistics applications | Search fields and repeated review forms lacked grouping | Shared filter/decision groups, neutral separators, readable applicant metadata and role labels |
| Service-area ownership | Repeated assignment forms could run together | Wrapping labeled field/action rows with neutral separators; unchanged reasons, confirmations and POST/PATCH routes |
| Sorting-center queues | Mobile parcel cells lacked column labels | Explicit parcel, rider, destination, stage, reason/action and last-update labels where applicable |
| Queue pagination | An empty page could imply no work remained in the entire queue | Accurate empty-page explanation; hub first-page recovery link; rider priority copy uses queue totals before claiming there is no open work |
| Rider task hierarchy | Redundant Priority Action badge | Existing specific next-task heading carries the meaning without filler |
| Rider summary grid | Native desktop review found five totals on one row and an orphan sixth card | Container-based six/three/two/one-column layout keeps complete rows where width permits |

Relevant concepts: A01, A02, A08–A10, A15, A17, B13, C08, C09, C16, C19, D03, D10, D13–D16 and D19. Existing tabs, skeletons, confirmation dialogs, numerical formatting, state badges and reduced-motion behavior were reused.

## Changed source files in this continuation

- `resources/views/admin/dashboard.blade.php`: review link and required rejection reason.
- `resources/views/admin/logistics/index.blade.php`: action hierarchy, shared feedback, search/review/area form grouping.
- `resources/views/courier/dashboard.blade.php`: truthful paginated-work copy, redundant badge removal and summary hook.
- `resources/views/logistics/dashboard.blade.php`: mobile labels and nonempty-queue/empty-page recovery.
- `public/css/workspace.css`: filter/review/row layout and accessible review-link sizing.
- `public/css/interaction-feedback.css`: include review links in 44px touch targets.
- `public/css/responsive-layout.css`: balanced six-card rider grid by available content width.
- `tests/Feature/RegistrationReviewTest.php`: regression proving a dashboard rejection has a labeled constrained reason and can submit through the existing endpoint.
- `tests/Feature/DashboardNavigationTest.php`: off-end rider/hub pages retain accurate queue totals and recovery guidance, with hub mobile labels.

No controllers, business services, schemas, dependencies, seed records or role permissions were changed. All previous unstaged UI improvements remain preserved.

## Fresh automated checks

| Check | Result |
| --- | --- |
| RegistrationReviewTest + DashboardNavigationTest | **Passed:** 16 tests, 162 assertions, including two new functional regressions |
| LogisticsWorkflowTest + LogisticsQueueTrackingTest + PartialReviewIntegrationTest | **Passed:** 131 tests, 1,163 assertions |
| PHP total for this continuation | **Passed:** 147 tests, 1,325 assertions; isolated test database |
| Existing frontend suite | **Passed:** 78 tests |
| Vite build | **Passed:** 3.50 seconds reported; existing optional fontaine warning remains |
| Edited public CSS | **Passed:** three stylesheets parse with installed PostCSS |
| Frame preservation | **Passed:** eight canonical frame-rule records equal the original HEAD baseline, normalizing selector whitespace |
| Whitespace | **Passed:** `git diff --check`; Git reports its CRLF normalization notice |

The final CSS-only touch-target/grid refinements use public stylesheets, not a new compiled entrypoint. They were parsed separately; no new JS bundle or dependency was introduced. Build time is not evidence of improved runtime performance.

## Native browser evidence

The native browser URL was readable on this continuation, allowing the previous Computer Use interruption to be resumed. Local seeded-account previews were used. No live approval/rejection, parcel movement, financial mutation or password-save control was used.

| Surface | Desktop | 400 × 768 mobile emulation |
| --- | --- | --- |
| Marketplace admin approval overview | **Passed:** document link, reason label/input and distinct decision buttons visible | **Passed:** card, fields and actions fit without horizontal page overflow |
| Rider work board | **Passed:** operational summary and corrected three-column grid observed | **Passed:** reachable two-row header action and two-column summary observed |
| Sorting-center dashboard | **Passed:** four summary totals, queue tabs, compact table and preserved border hierarchy observed | **Passed:** queue tabs, counts and labeled parcel cards observed |
| Logistics control tower | **Passed in the later buyer/control-tower continuation:** dashboard, compact table and expanded transfer controls observed | **Passed in the later continuation:** labeled parcel rows and reachable expanded controls observed |

Native screenshots are session observations, not saved repository artifacts. Automated role/ownership and workflow tests do not replace missing screenshot coverage.

The control-tower review was completed after the URL-policy interruption documented in the previous handoff. Its follow-up corrections and fresh checks are recorded in [the buyer/control-tower audit](buyer-control-tower-audit.md).

## Preserved design and limitations

Main pink frames, softer nested borders, grey inner edges, role palettes and neutral notification pages are unchanged. Existing numeric/status information and financial explanations are retained. The review-link target is at least 32px on ordinary pointers and 44px on touch pointers; button text is not split into arbitrary lines.

Physical cutouts/keyboards, screen-reader testing, runtime reduced-motion emulation, every table/state, 320px/landscape coverage and CLS/INP/frame-rate measurements remain **not run**. No universal accessibility or production-readiness claim is made.

Rollback: revert only the listed continuation hunks/tests. The earlier shared mobile grid correction and other first-pass changes can remain. No data rollback is required. Deployment is not performed.
