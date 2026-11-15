# Product variation revision audit

**Date:** 15 November 2026, Asia/Manila. **Scope:** Laravel web Add Product, Edit Product, private drafts, shared card pricing, and their cart/checkout integration.

**Resolution update:** PV-01 and PV-02 have now been resolved and checked. The subsequent full PHP suite passed **854 tests with 7,125 assertions**. See the [resolution audit](product-variation-resolution-audit.md) for the migration, restored-stock safeguards and current evidence. Browser visual acceptance remains unverified.

**Initial audit verdict (historical): core revision implemented, with one unresolved regression before an unqualified sign-off.** The focused behavior passed, but that audit's complete PHP suite reported **846 passed out of 847, with one failure**. The findings and results below retain that initial evidence; their unresolved wording describes the initial audit, not the resolution update above.

## Requested behavior and implementation

| Requirement | Implementation / audit result |
| --- | --- |
| Variations disabled: one price and stock quantity | Shared pricing fields show the base inputs. Explicit base-mode submission ignores hidden variation rows/files and requires base price/stock. Focused checks pass. |
| Variations enabled: hide base fields | JavaScript hides and disables the base fieldset, enables variation inputs, and updates accessibility state. The server independently derives price/stock rather than trusting hidden values. Script checks pass; real-browser visual acceptance remains unverified. |
| Full price and stock per variation | Each option uses a full PHP price, not a seller-entered adjustment. At least one complete row is required on submission. Focused create/edit checks pass. |
| Price range and combined stock | The existing price-range calculation is retained; shared product-price displays now include stock. Creation/editing stores the sum of submitted active-row quantities. Focused card rendering checks pass. See inventory edge case below. |
| Save and reopen drafts | Draft payload retains the mode flag and full option prices; incomplete private drafts are still allowed. Submission then applies full product validation. Focused checks pass. |
| Edit existing products and preserve history | Existing active rows initialize the toggle; full prices are reconstructed from the legacy base/adjustment values. Editing retains option IDs; switching off retires rows rather than deleting them. Focused checks pass, but mode inference conflicts with the legacy visibility rule described below. |

### Price and stock example

For Small at PHP 120.25 with stock 3, and Large at PHP 200.50 with stock 7, the saved product stock is **10** and the card range is **PHP 120.25–200.50**. Internally, the existing price contract stores a base of PHP 120.25 and adjustments of PHP 0.00 / PHP 80.25. Checkout can retain its established calculations and order snapshots.

The current buyer-facing range uses **active options with positive stock**. The editor preview includes all entered prices, including zero-stock rows. This distinction is retained from existing catalogue pricing behavior; the preview is an estimate, and a zero-stock option cannot be purchased. Sale/flash pricing can further change the displayed effective range. No per-option weight revision was included.

## Findings

### PV-01 — High: published mode is inferred, so retired-only legacy listings can be exposed

**Unresolved; reproduced by the complete regression suite.**

The new `variations_enabled` value is stored in private draft payloads and processed during product submission, but it is not persisted as a published Product attribute. Published forms infer the mode from active variation rows. A product intentionally switched to base mode and a legacy variation product whose options have all been retired therefore look identical to those checks.

The revised `Product::scopeAvailableForMarketplace` treats no active variations as a base product. The existing test `SearchPageTest::test_search_keeps_unavailable_unapproved_archived_and_unsellable_variants_hidden` creates a legacy product with positive stock and an inactive variation; that product must remain hidden. It now appears, producing the one full-suite failure. This is a meaningful compatibility failure, not a test expectation to delete.

**Required next correction:** persist an explicit published inventory mode, with a migration/legacy fallback that keeps existing retired-only variation products unavailable. Set that mode through validated seller actions and use it consistently in listing visibility, product pages, cart, checkout, and stock notifications. Keep retired rows and historical order references. Recheck both intentional base-mode conversion and the unchanged legacy search case. No migration or historical data rewrite was performed during this audit.

### PV-02 — Medium: retired-option restoration needs an inventory reconciliation check

**Source-review finding; the specific retirement/cancellation race was not executed in this audit.**

`InventoryRestorationService::restore` increments product stock and the historical variation stock when cancelling/restocking an order, even if that variation has since been retired. When other active variations remain, the product total can then exceed their combined sellable stock. The new card stock label uses the stored product total, so the requested combined-stock guarantee needs an additional retirement/restoration scenario, not only create/edit assertions.

This is an existing shared restoration path exposed by the broader stock-display promise. Before final sign-off, reproduce an order against one option, retire that option while another remains active, then cancel/restore the old order. Define how recovered retired inventory is recorded without making it sellable or losing physical-stock history, and verify that the displayed active total stays consistent. No restoration/accounting behavior was changed speculatively here.

## Corrections made while auditing

Reviewing the switch-back path found additional readers that treated *any* historical variation as an available option. Narrow corrections now make:

- The product page stop returning 404 solely because retired rows remain.
- Cart display and checkout require option selection only when active variations exist; an explicitly selected retired option is still rejected.
- Stock-subscription state ignore retired rows when deciding whether active variation stock limits a base listing.

The focused switch-back test was expanded to cover product-page access, adding the base item to cart, its availability/current price, checkout quote, stock state, and rejection of an old retired option. It passes. These corrections close the immediate path gap but **do not resolve PV-01's missing published-mode distinction**; they must be integrated with that fix before broader acceptance.

One initial audit test errored because it switched from the seller to buyer without clearing the test's module session. The fixture now clears the session before changing accounts and asserts the actual cart redirect. No production session isolation or authorization was weakened to make it pass.

## Validation and compatibility review

- New explicit variation mode validates 1–100 rows; every submitted row needs a numeric price from PHP 0.01 to PHP 99,999,999.99, up to two decimal places, and integer stock from 0 to 1,000,000.
- Existing unique-combination/SKU, seller ownership, variant-ID ownership, private draft, image upload and compliance checks remain in their established services/controllers.
- Extra base price/stock values cannot override derived variation totals. Explicit base mode discards hidden variation data instead of accidentally retaining it.
- Older requests without `variations_enabled` retain the existing base-plus-adjustment contract. API/mobile screens were not redesigned or independently accepted by this web revision.
- Existing review/publication workflow remains authoritative; switching mode may require compliance review. Current order snapshots, variation IDs and retired history are preserved. No new package or database schema was added by the revision/audit.

## Verification evidence

| Check | Result and boundary |
| --- | --- |
| Original related PHP regression | **86 passed, 679 assertions** before audit corrections; seven suites covering pricing modes, catalogue, PDP, seller workspace, sales/vouchers, cart and checkout integrity |
| Expanded focused audit suite | **8 passed, 53 assertions**, including the additional product-page/cart/quote/retired-option checks |
| Fresh complete PHP suite | **847 tests: 846 passed, one failure, 7,087 assertions, zero errors**; failed legacy search case recorded as PV-01 |
| Frontend tests from revision | **74 passed**, including three new mode/preview tests; not rerun during this audit because no frontend code changed here |
| Production build from revision | Passed with the existing optional fontaine warning; no UI asset changes were made during this audit |
| PHP formatting / whitespace | Pint and `git diff --check` passed for the audit corrections |
| Browser/device visual review | Unverified; the preceding discovery found Chrome but an unavailable browser-control host. DOM-style script tests do not prove rendered visibility, focus or mobile layout. |
| MySQL concurrency / new live flows | Not run for this revision audit. PHP tests use isolated SQLite. No live product, stock, account, order or funding fixture was inserted. |

Local evidence: `storage/logs/product-pricing-mode-{regression,frontend,build}.txt`, `product-pricing-mode-audit-focused.txt`, `product-pricing-mode-audit-focused.xml`, `product-pricing-mode-audit-regression.txt`, `product-pricing-mode-audit-junit.xml`, and `product-pricing-mode-audit-errors.txt`.

The eight focused PHP tests are included in the complete suite; their counts must not be added to it. The earlier 86-case result is historical targeted evidence, not a replacement for the later full-suite failure.

## Changed files

- Seller create/edit, `pricing-fields.blade.php`, `variation-fields.blade.php`, and `public/js/product-variation-editor.js`: toggle, full option prices and live preview.
- `ProductController`, `ProductVariationEditorService`, `ProductDraftService`: normalized validated prices/stock and draft persistence.
- `Product` and `components/product-price.blade.php`: listing compatibility change and shared price/stock display.
- Audit corrections: `HomeController`, `BuyerCartService`, `CheckoutPricingService`, `StockSubscriptionService`.
- Tests: `tests/Feature/ProductPricingModeTest.php` and `tests/Frontend/product-pricing-mode.test.cjs`.

## Before continuing

Resolve PV-01 while preserving the legacy test, execute and resolve PV-02's inventory scenario, rerun the focused/integration/full regression, and perform authenticated desktop/mobile visual acceptance when browser access is available. The audit is complete; the variation revision has **two outstanding findings and pending browser acceptance**. It should not yet be described as having a clean full-project sign-off.
