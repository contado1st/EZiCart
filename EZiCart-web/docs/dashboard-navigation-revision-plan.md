# Dashboard navigation and pagination revision

## Assessment before implementation

The rider dashboard stacks six operational queues below its summary and badge. Four of those queues load complete collections; available pickups and deliveries paginate at 8 and 10 rows. The hub dashboard stacks exceptions, four queue previews and a lifecycle panel. Its previews stop after 5–8 rows without page navigation. Logistics administration combines exceptions, applications, service areas, accounts and parcels on one long page, with separate paginator names already available. Seller and marketplace-admin overviews show only 5–6 recent records without pagination. Buyers have a status dropdown but no quick purchase-status tabs.

Other web operational lists use inconsistent limits, including 10, 15 and 50. Product discovery grids, chat histories, addresses and public reviews are different content types and should keep their own limits. Mobile API pagination must remain unchanged.

## Implementation plan

1. Add shared, progressively enhanced dashboard tabs. Explicitly identify panels in Blade; do not guess panel boundaries from table markup. Keep summaries, alerts and priority actions outside tabs. Preserve role authorization and every existing action.
2. Group rider queues into available pickups, accepted pickups, hub handoff, deliveries, returns and failed deliveries. Give each queue its own paginator and a total count. Keep counts independent of the displayed page.
3. Group hub work into arrivals, sorting, dispatch, failed deliveries, exceptions and lifecycle. Replace truncated previews with paginated queues. Group logistics-admin work into parcels, applications, service areas, accounts and exceptions.
4. Separate seller orders from store tools and marketplace-admin orders from approvals. Add buyer status-group links using server-side filtering, so groups cover the whole history rather than only the current page.
5. Standardize web operational table/list pages on 25 records (existing 20–30 limits remain valid). Use numbered, responsive bottom pagination with a visible record range. Preserve query filters, tab session parameters and independent page names.
6. Support deep links into panels, reload/back navigation, keyboard tabs (arrows/Home/End), visible focus, narrow screens, validation errors and printing. Without JavaScript, display all panels and leave ordinary links usable.
7. Verify isolated role-scoped pagination, correct totals, buyer filters, page-two visibility, tab behavior and frontend build. Do not seed, migrate or modify live business records for this UI task.

## Boundaries

No schema, dependency, fee, permission, order transition or mobile API changes. Short dashboard summaries and product recommendation grids are not operational tables. A 25-row limit is a maximum; an empty queue must explain that there is no work instead of inventing filler rows.

## Acceptance criteria

- A user can reach each main dashboard work queue through a nearby named tab.
- Table queues have bottom page numbers and display no more than 30 records per page.
- Filters and active tab survive pagination; unrelated queues do not share a page parameter.
- Summary/tab counts represent the entire matching queue, not just the current page.
- Existing links to a queue or form error open its panel; hidden panels never strand focus.
- Existing workflow actions, isolated tab accounts and print content continue to work.

## Implementation coverage

| Surface | Navigation and paging |
| --- | --- |
| Buyer | Server-filtered All orders, Preparing to ship, To receive and Closed orders; 25 orders per page |
| Seller | Orders/dispatch and Store overview/tools tabs; 25 orders per page; inventory now has its missing bottom controls |
| Rider | Six workflow tabs with independent 25-row queues plus a directly accessible rider-badge tab; default opens the next actionable queue; accepted pickups exclude unclaimed offers |
| Sorting hub | Arrivals, Sorting, Dispatch, Failed deliveries, Exceptions and Lifecycle; full queues paginate rather than silently stopping at 5–8 rows |
| Marketplace admin | Platform orders and Approvals/overview tabs; independent 25-row pages; account-approval list now has its missing controls |
| Logistics admin | Parcels, Applications, Service areas, Hubs/riders and Exceptions; independent pages and retained search filters |
| Finance | Balances/COD, Refunds and Seller payouts tabs; existing 20-row limits retained |
| Storage and routing areas | Records separated from setup/counting tools; 25-row tables; routing dropdowns retain all eligible choices across pages |
| Staff directory | Admin management-domain scope retained; 25 rows and numbered controls |
| Other operational lists | 10/15/50-row web lists changed to 25 (orders, products, vouchers, disputes, announcements, moderation, hub lists and profile-review histories); existing 20–30 limits retained |

Shared pagination renders a range and total, numbered links on narrow screens, and Previous/Next. It retains filters, other paginator positions and the current tab-account URL parameter. Tabs retain section state in the URL, support direct links and browser back, and keep every section visible without JavaScript and in print. Only the current page of each queue is available for printing; use existing full report exports for all records.

Product discovery/recommendation grids, chat message history, addresses, public reviews, line items within a single order and editable variation forms retain their content-specific behavior. This revision does not turn an order's line items into separately paged records.

## Verification and outcome

Verified on 8 November 2026:

| Check | Result |
| --- | --- |
| Targeted navigation feature tests | 7 passed, 74 assertions; all six roles, scoped queues, record totals, second pages, independent pages, mapping options and retained filters |
| Full PHP regression suite | 361 passed, 3,329 assertions |
| Full frontend regression suite | 57 passed, including 10 tab interaction tests |
| Production frontend build | Passed; existing optional font-optimization notice remains non-blocking |
| Blade compilation and PHP formatting | Passed |
| PHP syntax and diff whitespace | Passed |
| Shared-navigation source design scan | No anti-pattern failures; advisory type/radius values aligned with DESIGN.md |
| Public-domain script delivery | HTTP 200 JavaScript; SHA-256 matches the local navigation script |
| Desktop/mobile browser visual review | Blocked: Computer Use could not reliably determine the active Chrome URL |

The implementation and automated checks are complete. Browser screenshots and a real-device visual sign-off remain unverified. No live business records were seeded or migrated for this revision.
