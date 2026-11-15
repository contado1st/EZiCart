# Buyer and control-tower continuation plan

15 November 2026. Preserve the user's pink frame hierarchy, neutral notification pages, existing features and role boundaries.

## Progress measurement

At the start of this continuation, the 100-concept register contains 25 Applied, 42 Retained, 6 Diagnostic and 27 Deferred entries. This supports **67% concept coverage**, counting Applied + Retained. It does not establish a whole-system completion percentage: concepts overlap and several are optional or diagnostic rather than implementation tasks.

Four of six main role dashboards have recorded desktop/mobile preview evidence: seller, marketplace admin, rider and sorting center. Buyer and logistics admin remain pending. Catalog preview is additional evidence outside that denominator. A successful main-dashboard preview does not imply every module/state is verified.

## Source findings and planned changes

1. Buyer success feedback is rendered twice: a local flash block and the shared feedback component. Use one shared confirmation and keep a session error as a clearly labeled alert instead of the success class. Preserve the actual server message, review/receipt/cancellation rules and prices.
2. Buyer already-reviewed text uses a standalone star glyph. Reuse Shopicons and retain the numeric rating/comment and private-history link.
3. Logistics application, exception and parcel lists can describe an off-end page as an entirely empty queue. Distinguish total-zero from page-empty and offer a first-page link. Add useful zero-state text for service areas/managed accounts without adding a new administrative workflow.
4. Check the remaining dashboards in native desktop/mobile preview when the browser URL can be verified. Stop Computer Use if its URL-policy guard blocks it; document the exact remaining limit.

Native-review refinement: the control-tower parcel table leaves occasional transfer/reassignment forms fully expanded in every row. The observed transfer row includes a hub selector, reason textarea and submit button before the next parcel. Use labeled native disclosure controls for these secondary actions, keep Messages and current parcel state visible, and retain all existing POST routes, required fields and confirmations. Open disclosures on server validation errors and verify that the embedded forms remain available. This applies progressive disclosure to a demonstrated density problem rather than adding a new navigation system.

The 400px preview also showed that this parcel table inherited a 640px minimum width, putting coordination controls outside the initial view. Give this specific table the same labeled mobile-row approach already used by operational queues, preserve accessible table/row/cell semantics and desktop columns, and keep the table's existing outer border treatment. Do not apply this transformation to unrelated financial tables.

Buyer mobile refinement: four vertically stacked summary cards delay the purchase list. At a content width of 350–640px use two columns for this four-card summary, retaining the single-column fallback below 350px and existing larger layouts. Keep every value and accounting/status explanation; shorten only the repetitive filter help text.

In the purchase-list preview, Reset wrapped to an otherwise empty row. Keep Status above one compact mobile row containing the selector, Filter and Reset, and reuse existing muted helper-text styling so instructions do not compete with orders.

## Verification and audit

Add a narrow regression for the buyer's single success announcement/error semantics and control-tower empty-page recovery. Run the related dashboard and role-boundary tests, parse any edited public CSS and review the final diff. Existing loading, tabs, confirmations, pricing and tab-account isolation stay in place; no additional libraries, migrations or speculative interactions are needed.

Update the concept register for newly applied ideas without counting each repeated page refinement as a new concept. Update a progress scorecard and write a separate continuation audit with fresh results and honest browser status. No production deployment or live mutation controls are part of preview.
