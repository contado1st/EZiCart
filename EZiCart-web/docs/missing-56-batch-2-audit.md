# Missing-case revisions: Batch 2 implementation audit

**Date:** 14 November 2026 (Asia/Manila). **Scope:** Laravel web and shared checkout/voucher boundaries. **This batch:** 15 implemented case revisions. **Programme:** 33 of 56 implemented; 23 remain. Teacher execution and overall checklist reclassification remain separate from implementation delivery.

## Changes and case coverage

| Group | Cases | Delivered behavior |
| --- | --- | --- |
| M03 | SEL-DISC-001 through SEL-DISC-010; BUY-VOU-011 | Scheduled percentage/fixed sale prices, time boundaries, validation, overlap prevention, variant ranges, running edits/end-now, stale quote detection, order snapshots, and vouchers applied to sale-priced merchandise |
| M04 | SEL-VOU-003, SEL-VOU-008, SEL-VOU-009 | Hidden code-only vouchers, selected-product eligibility, eligible-line minimum spend/discount allocation, claimed-term protection and atomic funding for increased total limits |
| M09 | BUY-PDP-005 | Seller-configured per-buyer lifetime product caps, shared across variants and enforced in cart changes, Buy Now and final placement |

## Sales and pricing

The new seller Sales and purchase limits workspace is linked from Inventory. Sellers schedule a whole percentage from 1 to 99 or a fixed sale price of at least PHP 1, below every active option's ordinary price. A fixed sale price applies equally to all options. Store sales for the same product cannot overlap; adjacent schedules are allowed. Schedules start inclusively and end exclusively at second precision. UI schedules use Asia/Manila and stored/query dates follow the application's configured timezone consistently.

ProductSaleService serializes writes under seller/product locks, validates schedules and prices, fingerprints repeated submissions, versions edits, and audits changes. ProductPriceService resolves effective prices used by product labels, recommendations, shop/catalog cards, cart display, authoritative quotes and placement. Search/shop price filters and sorting use the corresponding SQL expression. Prices are evaluated at reads; correctness does not depend on a scheduler changing a base-price column.

Seller sale reductions lower merchandise revenue and the existing commission base. They do not create a voucher subsidy or a second wallet debit. Existing commission, single-shop checkout and one-voucher policies remain in force. Quote fingerprints include promotion identity/version and pricing details. If a reviewed offer changes before placement, the buyer must review again.

Order-item snapshots retain ordinary/effective unit cents, sale ID/version and line voucher allocation. Existing placed/paid orders retain their prices when a sale changes or ends. Unpaid wallet reservations retain the agreed price until the original reservation deadline; wallet payment uses the stored order and does not reprice it.

## Vouchers and funding

Public discovery excludes code-only offers on product pages, voucher discovery and the voucher API list. Unclaimed code-only vouchers cannot be obtained through guessed claim IDs. Buyers can enter a known code, case insensitively, at checkout; their own resulting saved voucher/history remains visible privately. Seller promotion delivery skips code-only voucher campaigns. No attempt is made to censor a code a user manually types into an unrelated private message.

Selected-product vouchers validate seller ownership. Minimum spend uses only eligible merchandise at its current effective price. Discount allocation excludes ineligible lines and persists exact line allocations with deterministic cent remainders. Selected-product vouchers currently apply to merchandise; all-shop shipping vouchers retain their existing behavior. Products referenced by voucher targets are archived on removal to preserve those references.

New seller campaigns use terms version 2: after the first claim, amount/type/minimum spend/product scope/visibility/start and recipient limits are locked. Expiry can extend and total usage can increase. Increasing usage updates the voucher and budget maximum and reserves the additional required funding in one transaction. Insufficient/frozen funds roll back the change. Repeated requests do not reserve money twice. Paused/ended offers require the existing separate availability action to become usable again.

Historical vouchers retain version 1 recipient/per-buyer controls, with existing claim/redemption lower-bound checks and the no-rescheduling rule. Their historical terms are not rewritten. Unlimited legacy offers can still edit permitted fields; increasing a bounded total requires a funded campaign. The teacher scenario's amount/minimum-spend locking and funded extension are covered without removing these existing controls.

## Purchase caps

Each seller can set an optional lifetime unit cap per product in the Sales workspace. Blank means unlimited. The count is shared across variations and prior non-cancelled order items for that buyer. Unpaid orders reserve units; cancellation/expiry releases them when cancellation is recorded. Returned non-cancelled purchases remain counted, including completed returns. Changing a cap affects future purchases without cancelling previous orders.

Add to Cart, quantity update, cart selection, Buy Now, web quote and authenticated shared quote checks give early errors. Final placement rechecks under the existing buyer/product locks, so another cart, device or direct request cannot bypass the cap. A historical cart can still display its saved quantity; attempting an invalid change or checkout produces the limit error rather than deleting saved lines.

## Verification performed

| Check | Result |
| --- | --- |
| New focused feature suite | 19 passed, 132 assertions; schedule boundaries/validation/overlap/replay, variant prices, old-order snapshots, unpaid reservations, targeted vouchers, code-only web/API exposure, funding rollback, caps and migration reversal/reapply |
| Finance/cart/voucher regression | 89 passed, 857 assertions across PartialVoucherIntegrationTest, FinancialLedgerWorkflowTest, CheckoutIntegrityTest, BuyerCartRevisionTest, WebOperationsTest and DeferredWalletOrderTest |
| Catalog/workspace regression | 54 passed, 484 assertions across PartialCatalogIntegrationTest, ProfileShopTest, SearchPageTest, ProductDetailPageTest and MissingSellerWorkspaceTest |
| Combined PHP checks | 162 passed, 1,473 assertions; these related suites are not a full-project regression |
| Frontend checks | 12 passed in product-selection, cart-selection and cart-feedback suites |
| Production build | Passed; existing optional fontaine warning retained |
| Syntax / formatting | PHP syntax passed for all 40 current changed/new non-Blade PHP files across Batches 1–2; Pint and git diff whitespace checks passed |
| Isolated MySQL schema check | Up/down/reapply on a copy with existing rows preserved |
| Isolated MySQL two-worker checks | One overlapping sale accepted; one capped unit admitted; concurrent identical usage extensions produced one budget top-up |
| Configured runtime probe | New schema/ownership checks passed; zero sale/target/cap/code-only records activated in the configured database |
| Browser/device acceptance | Not performed; browser access host unavailable when discovery was retried |

The cap concurrency probe runs the actual cap service under the buyer/product locks used by placement and inserts isolated order fixtures. It is not a simultaneous full financial checkout acceptance test. Sale/funding workers call the actual sale and voucher-edit services. All concurrency fixtures and schema copies from the successful probe were removed, and configured business counts remained unchanged during that run.

Evidence is stored under `storage/logs/missing-56-batch-2-*`: focused.txt, finance-regression.txt, catalog-regression.txt, frontend.txt, build.txt, mysql-checks.json and runtime.json. The extra web-operations.txt rerun is a subset of the final finance suite and is not counted twice.

Early checks found and corrected a timezone normalization error and a legacy unlimited-voucher validation regression. Existing tests were preserved; the final relevant suites passed. No external dependency was added.

## Probe incident and cleanup

An initial MySQL copy probe encountered a generated SKU column; copying now omits generated columns. A subsequent probe inherited the source connection's `name`, so Eloquent created two fixture users in the configured local database before its product insert failed on the still-missing cap column. The fixtures were exactly identified by IDs 44/45, their generated example.invalid addresses, fixture names and creation timestamp. All user foreign-key dependencies were checked and found empty before removing those two test users. The configured user count returned to 23. No source product, order, voucher, balance or funding entry was created by that failed attempt; allocated user IDs may retain a gap.

The probe now explicitly names its copy connection, clears inherited URL configuration, and verifies the Eloquent model connection/database before creating fixtures. Its corrected run passed, checked source business counts, and cleaned up its isolated schema. This incident is retained here rather than described as a wholly read-only initial probe.

## Database rollout and limits

The additive `2026_11_14_214751_add_scheduled_sales_voucher_scope_and_purchase_limits` migration creates product_sales/voucher_products and adds the nullable product cap, voucher visibility/scope/terms-version defaults and nullable order-item pricing snapshot. Existing prices, order totals, category assignments, review replies and account balances are preserved. A private backup was saved before applying it locally: `storage/app/private/backups/before-missing-56-batch-2-20261114-221040.sql`.

The final configured snapshot reports 23 users and 21 orders, with zero product_sales/voucher_products rows and zero configured caps/code-only offers. No demo campaign, purchase limit or new funds were enabled as example data. Rollback removes these new records/fields, including snapshots, so preserve that data before any populated rollback.

Flash-sale slots/countdowns/quantity splitting, bundle and Buy X Get Y pricing, reminders, seller-first return stages, automatic completion, nearest-rider dispatch and Lost/Damaged claims remain later batches. Hosted payment callbacks and direct delivery overrides retain the plan's scope differences. Mobile client screens were not changed; the shared quote/placement services enforce the new money/stock rules.

## Walkthrough

1. As an approved seller, open Inventory → Sales and purchase limits.
2. Expand Create sale beside a product, select percentage or fixed sale price, set future Philippine start/end times and submit. Product and sale lists have separate numbered 25-row pagination.
3. Use Sales history to edit the discount/end time or End now. Existing orders retain their agreed price; buyers with stale quotes must review again.
4. Set Units per buyer beside a product, or clear it for unlimited. Caps span all variants and prior purchases for that buyer.
5. Open Store vouchers. Choose Public or Code only, then All products or Selected products. Select eligible merchandise for a targeted voucher and fund it through the existing campaign budget flow.
6. After claims, use Edit expiry / limits to extend expiry or increase total redemptions. Insufficient additional funding produces an error and leaves both the voucher and budget unchanged.
7. As a buyer, compare sale and ordinary prices on product/catalog cards, select options and review current checkout totals. Enter a known code-only voucher manually; ineligible products do not help meet its minimum spend.

Next planned batch: M05/M06, ten cases covering flash allocations, reminders and bundle/Buy X Get Y pricing.
