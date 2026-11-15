# Role dashboard continuation plan

15 November 2026. Continue the approved UI modernization without changing the pink frame hierarchy, notification exception, role access or parcel/financial services.

## Evidence and chosen scope

| Issue | Revision | Verification |
| --- | --- | --- |
| Marketplace dashboard Reject form omits the server-required reason | Add a labeled reason input with existing 5–1000 character constraints; link to the existing application/document review | Render/form-contract regression plus existing registration review tests |
| Logistics dashboard application controls and search fields have weak grouping | Use explicit filter, review-item and decision-action groups; keep all current POST routes and confirmations | Render tests and bounded native preview if available |
| Logistics hub-transfer link sits outside the main action hierarchy | Move it to the page action group, suppress its matching topbar duplicate, keep finance secondary | Rendered routes and navigation tests |
| Sorting hub queue tables omit mobile cell labels | Add labels matching each column heading | Render tests; existing responsive table styles remain |
| Empty paginated queue pages can imply the entire queue is empty | Distinguish an empty page from a zero-total queue; provide a first-page recovery link | Off-end-page regression |
| Rider priority section has a redundant badge and can claim no work when the selected page is empty | Keep its meaningful next-task title, remove the filler badge, use paginator totals for the empty-page explanation | Off-end-page render regression |

## Boundaries and sequence

1. Confirm current sources and record baseline native preview where supported.
2. Implement the listed Blade/CSS refinements in existing shared entrypoints. Avoid a new component library, JS handler or layout framework.
3. Run narrowly relevant navigation/review/logistics tests; parse public CSS and build if compiled styles change.
4. Perform one combined desktop/mobile review and one corrective pass where native controls permit. Record any browser policy block accurately and continue independent code verification only when permitted.
5. Publish a continuation audit that separates fresh evidence from the previous pass. Preserve all earlier unstaged changes and audit documents.

No new approval is required for these reversible UI changes. Do not approve/reject real accounts, change parcel custody, create finance entries, save browser passwords or alter seed data during previews.
