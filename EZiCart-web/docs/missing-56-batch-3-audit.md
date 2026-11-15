# Missing-case revisions: Batch 3 implementation audit

**Date:** 14 November 2026 (Asia/Manila). **Scope:** Laravel web and shared authoritative checkout services. **This batch:** 10 implemented case revisions. **Programme:** 43 of 56 implemented; 13 remain. Implementation delivery is separate from literal teacher-fixture execution and overall checklist reclassification.

## Delivered cases

| Group | Cases | Delivered behavior |
| --- | --- | --- |
| M05 | SEL-FLASH-001 through SEL-FLASH-006; BUY-PDP-006; BUY-CHAT-004 | Seller flash slots, validation, limited allocation, fallback pricing, split quantities, countdown and opt-in reminders |
| M06 | SEL-FLASH-007; SEL-FLASH-008 | Quantity-percentage bundles and selected Buy X Get Y gifts, with authoritative cart/checkout recalculation |

The existing Inventory → Sales and purchase limits page links to **Flash sales and bundles**. Flash slots and bundles have separate numbered 25-row tables, create forms and confirmed End offer actions. Seller ownership, eligibility and schedule rules are validated on the server. Repeated create/end submissions are safe to retry and changes are audited.

## Prices, allocation and order integrity

Flash slots use an inclusive start and exclusive end. The seller sets a fixed price of at least PHP 1, below the ordinary and overlapping store-sale option prices, and an allocation no greater than current product stock. Overlapping slots for a product are rejected. A subsequent store-sale change cannot undercut an overlapping flash slot. If a later catalogue change makes a flash price invalid, public discovery and authoritative pricing ignore that offer.

Flash allocation is checked under the existing product/variation locks and a flash-slot lock during placement. A unique slot/order reservation records the purchased quantity. When only one flash unit remains and a buyer chooses two units, checkout shows one at the flash price and one at the current store-sale or ordinary price. Quote fingerprints include the resulting breakdown; a stale reviewed quote must be reviewed again when allocation or pricing changes.

Cancellation releases an unfulfilled order's flash reservation once through InventoryRestorationService. An ended/expired slot does not regain promotional capacity. Delivered returns do not replenish flash allocation. Existing wallet reservation deadlines and cancellation behavior continue to apply.

Bundles count qualifying units across selected products and options from one shop. Store-sale prices apply first, the bundle applies next, and an eligible voucher applies afterward. A bundle cannot overlap another bundle or flash slot on its affected qualifying/gift products. These rules avoid ambiguous stacking while preserving ordinary store sales and vouchers.

Percentage discounts use aggregate half-up cent rounding and deterministic allocation. Uneven unit prices become actual separate price segments: for example, two PHP 1.03 units at 15% off total PHP 1.75, recorded as PHP 0.87 and PHP 0.88. Cart, checkout and order views retain the exact breakdown rather than displaying an inaccurate rounded average.

Buy X Get Y grants the configured gift quantity per completed qualifying set, bounded by the gift quantity the buyer selected. The gift must be added to the selected cart explicitly; it is not silently added to an order. Free gifts retain physical stock, parcel weight and per-buyer purchase-limit checks. Removing a qualifying item removes the unearned discount/free quantity. Split price segments do not grant extra reviews for the same product.

The cart selection endpoint recalculates the selected one-shop merchandise subtotal and per-line breakdown without mutating saved cart contents. Its browser handler ignores stale asynchronous responses and waits for the current selection quote before enabling checkout. Checkout stores the original cart keys and quantities, so refreshes and successful cart removal still work after price splitting. Final placement revalidates price, stock, allocation and purchase limits independently of browser previews.

## Countdown and reminders

The product page shows the offer schedule, remaining allocation, fallback-price explanation and quantity breakdown. The countdown uses an offset from the server clock, stops at zero and honors the existing product-option controls. Allocation displayed in an already open page is a snapshot; checkout confirms current availability.

Approved buyers can subscribe or unsubscribe from a product's flash reminder. The scheduler command `commerce:deliver-flash-reminders` is registered every minute with overlap protection. It sends a database notification in the minute before the slot starts, linking to the product. A late subscription during a live slot gets an accurately labeled live notification, rather than a false advance reminder. Unique subscriptions, locked processing and a notification timestamp prevent repeated delivery.

Cancelled, ended, hidden, unavailable, invalid-price and no-longer-eligible reminders are suppressed. Notification persistence and its delivery marker share a transaction through the existing notification sender. These are in-app notifications; external email/SMS/push delivery is not part of this batch. Server scheduler operation must be enabled and monitored in deployment; registration alone does not verify that a production scheduler runs.

## Verification performed

| Check | Result |
| --- | --- |
| New focused suite | 23 passed, 140 assertions in MissingFlashBundleTest |
| Finance/cart/voucher regression | 77 passed, 705 assertions across MissingSalesVoucherLimitTest, CheckoutIntegrityTest, BuyerCartRevisionTest, DeferredWalletOrderTest and FinancialLedgerWorkflowTest |
| Catalogue/workspace/review regression | 70 passed, 614 assertions across MissingSellerWorkspaceTest, PartialCatalogIntegrationTest, ProfileShopTest, SearchPageTest, ProductDetailPageTest and PartialReviewIntegrationTest |
| Combined related PHP suites | **170 passed, 1,459 assertions**; not a full-project regression |
| Frontend regression | **16 passed** across flash-promotion-pricing, product-selection, cart-selection and cart-feedback |
| Production build | Passed; existing optional fontaine warning retained |
| PHP syntax and formatting | 54 current changed/new non-Blade PHP files passed syntax checks; Pint passed; git diff whitespace check passed |
| Isolated MySQL migration | Up/down/reapply passed with existing rows preserved |
| Isolated MySQL full COD placement race | One last-flash-unit checkout committed; the competing stale quote was rejected; fallback pricing and exactly-once cancellation release passed |
| Isolated MySQL reminder race | Two workers produced exactly one persisted database reminder |
| Previous-batch MySQL compatibility | Overlapping-sale prevention, purchase-cap guard and single voucher-budget top-up passed again against the current services and new schema |
| Configured runtime probe | Passed schema, ownership, gift and allocation-bound checks; zero activated rows in the five new tables |
| Browser/device visual acceptance | Not performed; browser-control access host was unavailable during the preceding discovery attempt. Native browser presence alone is not visual acceptance |

Evidence: `storage/logs/missing-56-batch-3-focused.txt`, `finance-regression.txt`, `catalog-regression.txt`, `frontend.txt`, `build.txt`, `mysql-checks.json`, `runtime.json`, `syntax.json`, `pint.txt` and `pint-check.txt`, each using the same `missing-56-batch-3-` prefix. Relevant source probes are `scripts/check-flash-bundle-revision.php` and `scripts/verify-flash-bundle-revision.php`.

The updated prior-batch probe `scripts/check-sales-voucher-limit-revision.php` installs the new empty flash/bundle tables in its isolated copy so current sale validation can run. Its successful compatibility rerun is recorded in `storage/logs/missing-56-batch-3-sales-compatibility.json`; the cap check remains a service/lock guard check, distinct from this batch's full COD placement race.

The MySQL placement workers call the real PlacePurchaseService on an isolated local schema with notifications faked to exclude external delivery. Separate reminder workers exercise the database notification channel. The copy connection is explicitly named and verified before creating fixtures. Successful probes removed their isolated schema and confirmed configured business counts were unchanged.

Early focused testing exposed a Blade directive adjacent to text in the unit-price note; the source component was corrected. One initial catalogue run collided with a concurrently running finance suite's shared fake-storage directory. The complete catalogue group was rerun sequentially and passed; no test was weakened to hide the collision. Future PHP test groups using the shared Storage fake must run sequentially.

## Database rollout

The additive migration `2026_11_14_224226_add_flash_sales_bundles_and_reminders` creates `flash_sales`, `flash_reservations`, `flash_reminders`, `bundle_deals` and `bundle_products`. It was applied to the configured local MySQL database after saving the private backup `storage/app/private/backups/before-missing-56-batch-3-20261114-231343.sql`.

The read-only configured snapshot reports 23 users, 21 orders and zero records in all five new tables. No example campaign, order, reminder, account or funding was activated in that database for this batch. Existing order amounts, stock and balances were retained. A populated rollback would remove these new promotion/reservation records and must be preceded by a backup and a plan for their references in retained order snapshots. No production deployment is claimed.

## Walkthrough

1. As an approved seller, open Inventory → Sales and purchase limits → Flash sales and bundles.
2. Choose a product, flash price, quantity and Philippine start/end times. Submit the slot. Check Upcoming, Live, allocation and Ended in its paginated table.
3. As a buyer, open that product before the start, choose Remind me, and inspect Notifications when the scheduled command runs in the advance window. Cancel reminder to opt out.
4. During the slot, select an option and quantity. Review the flash/fallback breakdown. Add it to the cart or use Buy Now, then review the authoritative checkout quote.
5. For a percentage bundle, select qualifying products, required units, percentage and schedule. As the buyer, select enough qualifying units from that shop in the cart; uncheck one and verify the subtotal recalculates.
6. For Buy X Get Y, select a separate gift product, gift quantity and qualifying threshold. The buyer adds the gift and qualifying products to the selected cart together. Checkout labels free units and includes them in stock and shipping-weight checks.
7. Place an order and inspect price segments in buyer/seller order details. Existing placed orders retain their recorded price when an offer ends. Unfulfilled cancellation releases eligible stock/allocation once.

## Remaining programme

**13 of 56 remain:** Batch 4 has six return/escalation/deadline and automatic-completion cases; Batch 5 has four nearest-rider/decline-cascade and Lost/Damaged claim cases; Batch 6 has three payment-provider/delivery-override scope reconciliations. The latter must retain the agreed internal school-demo wallet and evidence-bound delivery policies rather than silently introducing an external gateway or proof bypass.

The progress register records implementation evidence separately. Original Missing classifications and `teacher_case_executed: false` remain unchanged until a new dated teacher-case assessment is actually performed.
