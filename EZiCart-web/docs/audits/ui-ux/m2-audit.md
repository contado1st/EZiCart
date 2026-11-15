# M2 audit: task hierarchy and mobile layout

15 November 2026. Status: **implemented; seller/catalog pilots verified**.

Concepts: A02, A08, A09, A10, A12, C16, C19, D03, D15, D19, D20.

## Changes

- Seller overview: specific heading, task-oriented description, plain Add product action and one existing rating icon. Earnings, unsettled-cash explanation, counts and order states remain intact.
- Admin overview: Review applications is primary; Review Products is secondary. Both retain their queue counts and destinations. The old one-off left margin is replaced by group gap.
- Seller/admin/hub dashboards declare `workspace-primary-route`. The topbar suppresses its quick action only when the declared route equals its role-specific action. Other pages retain their quick action.
- Shared page actions wrap and align with consistent spacing. Mobile pages with a local primary action use a compact 64px header; pages retaining the header quick action reserve 116px for its separate 44px row.
- **Browser-discovered defect fixed:** at ≤900px the fixed sidebar leaves grid flow, but the old frame still had a zero-width sidebar track. The page auto-placed into that track, collapsing dashboard text into individual letters. The mobile frame now has one `minmax(0, 1fr)` column. Desktop expanded/collapsed sidebar tracks remain unchanged.
- At ≤440px the workflow summary status follows the disclosure indicator, keeping the indicator beside the task name/count instead of adding an unnecessary third row. Summary remains at least 44px tall.

Changed files: `resources/views/components/workspace-topbar.blade.php`, `resources/views/seller/dashboard.blade.php`, `resources/views/admin/dashboard.blade.php`, `resources/views/logistics/dashboard.blade.php`, `public/css/workspace.css`, `public/css/responsive-layout.css`, `public/css/workflow-progress.css`.

## Evidence

**Passed:** `DashboardNavigationTest` and `LoadingProfileTest`: 10 tests, 97 assertions. Covers buyer/seller/rider/hub/admin queue rendering, independent pagination and ownership boundaries, plus loading-profile rendering. This is automated HTTP/render evidence, not an all-role visual review.

**Passed:** native Chrome local seller sign-in using the existing seed account. Desktop screenshot showed one Add product action, the revised heading, preserved pink main/nested frames, task counts and numerical metrics. A 400 × 768 emulated screenshot reproduced the collapsed-grid defect; the corrected screenshot showed full-width readable content, reachable mobile header controls and the same task disclosure. The disclosure was manually collapsed to inspect the dashboard content; its default-open behavior was preserved.

Existing tabs, filters, numbered pagination, Finance & Wallet links and role routes were reused. Their frontend regressions passed in M3. No controllers, queries, authorization or workflow transitions changed.

**Not run:** native screenshots of every admin/rider/hub page, 320px width, landscape keyboards and full browser/assistive-technology matrix. Do not treat the successful pilot as proof of every page/state.

Rollback: remove the page sections/topbar conditional and restore the listed styles. Keep the mobile single-column bug fix separately if reverting only visual preferences. Ready for the feedback/motion review.
