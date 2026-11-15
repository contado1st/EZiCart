# Finance and profile-review UI audit

15 November 2026. Follows [the finance/profile plan](finance-profile-plan.md). The previous main-dashboard preview matrix remains complete; this is additional subpage work.

## Changes

- Finance navigation identifies its current destination through the existing active-tab styling and `aria-current`.
- Wallet header/export action uses established secondary-button styling. Wallet-specific margin resets remove gaps that previously combined grid spacing with child margins.
- Available-balance help distinguishes personal sandbox funds from the shared platform treasury. All sandbox, hold, review and non-bank-account explanations remain intact.
- Withdrawal requests use grouped labeled fields. Administrative withdrawal decisions have a unique associated label and retain required reasons/execution references.
- Funding and withdrawal forms restore old values and their idempotency key only for their matching submitted form. Other forms receive fresh keys. Non-scalar old values are omitted from those controls.
- Profile-review retry reasons restore only to the matching revision instead of appearing on every pending request. The hidden form/revision markers control display restoration only; authorization remains on the existing server routes and services.
- Wallet/review off-end pages distinguish remaining records from zero-total histories and provide first-page recovery links.
- Explicit financial amount columns use right-aligned, unbroken tabular numerals; cents and formatting logic are unchanged. Financial history keeps its before/after columns and horizontal scrolling on small screens.
- Profile-review tables retain visible current/requested evidence and become labeled mobile records, with explicit table semantics. No approval action or evidence is removed or hidden behind a cosmetic disclosure.

Changed source: `resources/views/components/finance-navigation.blade.php`, `resources/views/finance/wallet.blade.php`, `resources/views/finance/partials/demo-funding.blade.php`, `resources/views/staff/reviews.blade.php`, `public/css/workspace.css`, `public/css/responsive-layout.css`. Regression additions are in the existing funding, ledger and profile-approval feature tests.

## Fresh verification

| Check | Result |
| --- | --- |
| DemoWalletFundingTest + ProfileChangeApprovalTest + FinancialLedgerWorkflowTest | **Passed:** 43 tests, 528 assertions |
| Final combined Laravel run: DashboardNavigationTest + the three suites above + RegistrationReviewTest | **Passed:** 62 tests, 741 assertions; isolated SQLite test database |
| All frontend test files | **Passed:** 78 tests; includes tabs, loading, account isolation, product/cart interaction and workflow feedback |
| Wallet retry restoration | **Passed:** both flows retain their own amount/key after real validation failure; the other form is blank with a fresh key; no funding/withdrawal record is created |
| Profile review retry | **Passed:** failed rejection reason remains on its intended request; both revisions remain pending; other request stays blank |
| Finance navigation / decision labeling / off-end pages | **Passed:** role-specific copy, one current destination, labelled required decision and three wallet histories recover correctly |
| Existing financial and profile behavior | **Passed in the targeted suites:** permission domains, sandbox review, ledger/holds and profile approvals remain covered |
| Final Vite build | **Passed:** 3.65 seconds reported; existing optional fontaine warning, no dependency added |
| Final public CSS validation | **Passed:** all six changed public stylesheets parse with installed PostCSS |
| Final whitespace check | **Passed:** git diff --check; existing CRLF normalization notice only |
| Pink hierarchy invariant | **Passed:** all eight canonical frame-related rule records match the original baseline after selector-whitespace normalization |

These are isolated test-database operations. No native browser funding, withdrawal, financial execution, account approval or profile submission is authorized or used for preview. No controller, business service, dependency, schema, role permission or session-security setting changed.

## Browser evidence

| Page / role | Desktop | Mobile |
| --- | --- | --- |
| Buyer Finance & Wallet | Observed: current navigation, personal sandbox copy, clearer export action, reduced duplicate gaps and aligned withdrawal controls | Observed at 400 x 768: stacked labeled withdrawal controls, spaced submit action, contained financial-table scroll and preserved frame hierarchy |
| Admin Finance & Wallet | Observed: active finance navigation, shared-treasury copy, aligned metrics and demo-funding panel; no submissions | Observed at 400 x 768: export/navigation fit, stacked balances, labeled funding fields and receipt controls with spacing; no submissions |
| Admin profile-change approvals | Observed: clear heading, aligned status/filter controls and complete zero-request table; populated two-request retry state covered by tests | Observed at 400 x 768: readable heading, aligned status/filter controls and zero-request state; populated state tested only |
| Registration details/documents | Observed: two-column details, honest missing-document states, separated document/decision frames and labeled rejection reason; no decisions submitted | Observed at 400 x 768: single-column detail fields, readable missing-document states, separated frames and accessible stacked approve/rejection controls; no decisions submitted |

Native preview window bounds changed during review; the dedicated EZiCart window was reselected and URL-based navigation used before further inspection. The buyer mobile preview was reset to normal magnification before assessment. Two earlier native administrator sign-in transitions ended in a Computer Use URL-verification policy stop; those previews were completed in the final continuation below. Do not interpret source/tests or an empty live queue as populated-state screenshot verification.

Final continuation: the existing administrator session was stable and all remaining planned screens were inspected without another sign-in. A native state-capture timeout recovered by reselecting the same returned window. The planned subpage preview matrix is now **8 of 8 observed**. No additional blocking layout defect was found in these bounded previews. The profile queue contained zero requests; the selected pending seller application contained no submitted documents. Populated profile comparisons and authorized file preview/download remain isolated-test coverage, not live visual verification.

The first sandboxed Node/build attempts were unable to spawn workers (EPERM). Their approved reruns outside the sandbox passed; these were environment failures, not failed application assertions.

## Limits and rollback

Main pink borders, softer nested/grey edges, notification exceptions, role fonts/palettes, exact monetary values and existing financial records/export destinations are retained. Protected document access remains server-authorized. Populated request/approval states are tested; physical keyboards/cutouts, full assistive-technology coverage, all financial states and runtime performance remain unverified.

Concept coverage remains 67%; these refinements deepen existing concepts rather than inventing new checklist entries. Roll back these scoped Blade/CSS/test hunks independently of earlier UI revisions; no data rollback is required. No production deployment is performed.

## Closeout

This planned UI revision is complete locally and ready for user review. Its implementation, targeted regression checks, asset build, six-role dashboard previews and four-page subpage preview matrix are recorded. No known blocking defect remains from the checked scope. The acceptance limits above are retained explicitly; this does not certify every application page, financial state, document type or physical device. Optional/deferred design techniques are not unfinished requirements. See the [progress scorecard](progress-scorecard.md) for the distinction between concept coverage and revision completion.
