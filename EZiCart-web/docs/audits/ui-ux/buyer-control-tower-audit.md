# Buyer and logistics control-tower audit

15 November 2026. Follows [the continuation plan](buyer-control-tower-plan.md). Preserves all earlier unstaged changes and the user's pink frame hierarchy.

## Changes

- Buyer success feedback now has one shared status announcement. Removed the duplicated local success block and duplicate shared include.
- Buyer session errors now use an alert with error styling and the actual server message, rather than a success container.
- Already-reviewed purchases use the existing Shopicons star, retaining the rating, comment and private-history link.
- Buyer summary cards use two columns at a content width of 350–640px, retaining the narrower single-column fallback and larger layouts. The mobile status selector, Filter and Reset now share a compact row below their label. Existing muted helper styling keeps instructions below the order content in visual priority.
- Logistics applications, parcels, managed accounts, service areas and recovery exceptions distinguish an empty pagination page from an entirely empty result set.
- Each nonempty queue's off-end page offers a first-page recovery link. Existing query filters and selected workspace tab are retained.
- True empty service-area/account views explain where to continue without adding a new workflow or removing existing controls.
- Control-tower transfer/reassignment forms now use labeled native disclosures, reducing default row height while retaining Messages, current parcel state, required fields, routes and confirmation guards. Server validation errors reopen the controls. No artificial animation wait or new handler was added.
- The 400px preview exposed the parcel table's inherited 640px minimum width. This specific table now becomes labeled mobile rows with explicit table/row/cell semantics and retained column headers. Desktop columns stay intact; financial tables are not transformed.
- The progress scorecard explicitly defines 67% concept coverage and separates it from main-dashboard browser acceptance. The concept register now records the already-implemented rider badge removal under A01.

Changed application files: `resources/views/buyer/dashboard.blade.php`, `resources/views/admin/logistics/index.blade.php`, `public/css/workspace.css`, `public/css/responsive-layout.css`. Regression additions are in `tests/Feature/DashboardNavigationTest.php`. No JS, business service, route, schema, dependency, financial logic or approval/parcel transition was changed in this continuation.

## Fresh verification

| Check | Status |
| --- | --- |
| DashboardNavigationTest + RegistrationReviewTest + PartialReviewIntegrationTest | **Passed:** 35 tests, 343 assertions |
| Final focused DashboardNavigationTest rerun | **Passed:** 11 tests, 139 assertions after the final buyer layout refinement; this is overlapping coverage, not 11 additional unique tests |
| Buyer feedback regression | **Passed:** success/error text appears once and belongs to the appropriate status/alert region |
| Control-tower pagination regression | **Passed:** five populated queues requested beyond their last page retain accurate copy and first-page links with filters/tab intact |
| Coordination disclosure regression | **Passed:** both guarded POST forms retain CSRF, selectors, required constrained reasons and reopen after real validation failure with the browser session cookie carried through the redirect |
| Production asset build | **Passed:** Vite reports 3.82 seconds; existing optional fontaine warning remains, no dependency added |
| Final public CSS parser | **Passed:** both edited public stylesheets parse separately; final refinements use the existing public stylesheet entrypoints |
| Pink hierarchy comparison | **Passed:** all eight canonical frame-related rule records match the original HEAD baseline after selector-whitespace normalization |
| Concept register / whitespace | **Passed:** all 100 expected IDs are present once, 27 Applied + 40 Retained = 67% coverage; `git diff --check` has no whitespace errors |
| Broader earlier checks | Historical results remain in prior audits; not counted as a fresh run here |
| Logistics-admin desktop browser check | **Passed:** local dashboard, compact parcel table and an expanded transfer form observed; no mutation submitted |
| Logistics-admin mobile browser check | **Passed:** 400px labeled parcel rows; expanded hub selector, reason and submit button fit without panning |
| Buyer desktop browser check | **Passed:** purchase list, status filter/reset, review star/history link, totals and dispute action observed |
| Buyer mobile browser check | **Passed:** 400px summary, status tabs, aligned filter/reset and readable purchase cards observed |

No live account approval/rejection, custody action, financial transaction, review submission or password change is part of preview. Existing seed-account login is used only when native browser controls can verify the local URL.

All six main role dashboards now have desktop/mobile preview evidence across the audits: 12 of 12 role/viewport checks. This closes the main-dashboard preview gap, not every page/state in the platform. Screenshots were observed through native Chrome and are not saved repository artifacts. The buyer and control-tower mobile corrections were confirmed in the final preview, including an expanded transfer form; no transfer was submitted.

## Limits and rollback

The 67% figure is concept coverage, not a universal completion score. Optional deferrals and diagnostic principles are listed separately. Physical-device keyboards/cutouts, full assistive-technology coverage, 320px/landscape, runtime reduced-motion emulation and measured CLS/INP remain unverified. Other module subpages and edge states still require scoped review.

The new disclosure test initially failed because its simulated redirect omitted the session cookie. The final regression carries that cookie as a browser does with JSON session serialization; application session/security settings were not changed to make the test pass.

Rollback these Blade/CSS changes and their regression additions independently of the previous UI revisions. No data rollback is required. Deployment is not performed.
