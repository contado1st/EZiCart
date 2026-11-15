# Product variation findings: resolution audit

**Date:** 15 November 2026, Asia/Manila. **Scope:** resolution of PV-01 and PV-02 in the [initial variation audit](product-variation-revision-audit.md). Laravel web and shared catalogue, checkout and inventory services.

**Outcome: both findings resolved.** The unchanged legacy search expectation passes alongside the intended base-mode conversion. Restoring retired options preserves their units without adding those units to sellable stock. Final full regression: **854 passed, 7,125 assertions, zero failures/errors/skips**. Browser/device visual acceptance remains a separate unverified item.

## PV-01: saved mode and legacy compatibility

The additive `2026_11_15_062908_add_variations_enabled_to_products_table` migration adds nullable `products.variations_enabled`. Existing rows remain null; no catalogue, price, stock or historical option was backfilled or deleted.

| Saved value | Meaning |
| --- | --- |
| `false` | Seller explicitly chose a base product. Retired history does not require a variation selection. |
| `true` | Seller explicitly chose variations. A purchasable active option is required. |
| `null` | Preserve the legacy contract: any historical variations mean it remains a variation product; only products with no variation history use the base contract. |

The model's shared `usesVariations` method and listing scope distinguish an intentional base conversion from an unavailable legacy variation listing. Product-page, cart, authoritative checkout and stock-notification readers use this distinction. They do not treat retired-only legacy rows as base inventory. An explicitly selected inactive/foreign option remains invalid.

Seller create/update persists the validated choice, private drafts retain it, and duplication copies it into the new private draft. Older payloads retain legacy behavior; adding nonempty options through the existing legacy web-edit contract promotes the product to variation mode. Editing without supplying a new mode/options retains the current mode. The published forms initialize from the saved mode rather than only counting active rows.

A mode change triggers the existing compliance-review lifecycle even when both old and new active-row counts are zero. Seller audit records include the previous/next mode. An approved legacy retired-only listing cannot become a live base product merely by bypassing another compliance review.

## PV-02: restoration of retired inventory

`InventoryRestorationService` already locks the order, products and options and records `inventory_restored_at`. It now reads the locked option's active state before incrementing the sellable product total:

- Active option: the approved restored quantity increases both option stock and product stock, once.
- Retired option: the approved quantity increases that retired option's stock only. The option stays inactive; the product's sellable total does not increase.
- An order for an option retired during an explicit base conversion follows the same rule; its units do not silently join the new base stock pool.

Each retired-option restoration writes a system `retired_variation_stock_restored` administrative audit entry with order, order-item and quantity references. Historical units remain recorded against their original option. Replay/concurrent calls cannot restore them or record their audit twice. No refund, payout or financial posting is created by this change.

Inspected returns keep their existing approval and immutable per-item inspection rules. Only `restock_quantity` is restored; excluded/damaged quantities are not made sellable. The added partial-return test verifies one approved and one excluded unit against a retired option. Cancellation tests verify exactly-once restoration, active-total consistency and base-conversion behavior.

To sell recovered retired units again, the seller must explicitly review/reconcile their inventory and edit/reactivate an appropriate option through the existing seller/compliance process. There is no automatic option reactivation or automatic transfer into another option. This change does not add a separate recovered-inventory screen.

## Verification

| Check | Final result |
| --- | --- |
| Focused mode/search/return suites | **59 passed, 416 assertions** |
| Complete installed PHP suite | **854 passed, 7,125 assertions; zero failures/errors/skips** |
| Original failing legacy search case | Passed without removing or changing its expectation |
| All frontend tests | **74 passed**, including toggle/preview checks |
| Production Vite build | Passed; existing optional fontaine warning retained |
| Style and whitespace | Pint and `git diff --check` passed |
| SQLite migration roundtrip | Passed; product/variation rows retained and legacy fallback restored after reversal/reapply |
| Disposable MySQL migration | Up/down/reapply retained copied product counts and null legacy states |
| Disposable MySQL visibility | Explicit base and legacy retired-only products produce their distinct intended results |
| Disposable MySQL concurrency | Two workers restored retired units once, left sellable totals unchanged, and wrote one retired-stock audit |
| Configured database inspection | Passed: 23 users, 21 orders, 15 products; all 15 products retain null legacy mode; zero invalid modes |
| Browser/device visual acceptance | Unverified: preceding discovery found Chrome but the browser-control host unavailable. Automated script/rendering tests are not visual acceptance. |

PHP tests use isolated SQLite and test transports. The MySQL probe copies bounded local/testing data into a uniquely named disposable schema, verifies the Eloquent connection before fixtures, cleans workers/schema in `finally`, and confirms configured business-row counts unchanged. It creates no live seller, product, order or stock adjustment. The migration itself was applied locally after the private backup `storage/app/private/backups/before-product-variation-resolution-20261115-063858.sql`. No production deployment is claimed.

The focused tests overlap the full suite and must not be added to its count. An earlier 852-test full run passed before the final compliance/partial-return cases; the final 854-test result above supersedes it. Initial new-fixture mistakes used an input-only weight field and a guarded inactive attribute; corrected fixtures exercise the actual stored grams and retirement state without weakening production guards.

## Rollout and rollback

The local migration is already applied. Other environments require the additive migration before this code, since the listing query references the new column. No dependency installation or historical backfill is required. Do not infer/overwrite old modes from active rows alone.

`down()` removes the mode column without deleting products/options. It loses explicit mode choices: a converted base product with retired history would again fall under the legacy variation contract. Preserve the mode values and use coordinated code/schema rollback or a forward fix for a populated environment. Dropping the column while running the new query code is unsupported.

## Evidence and reproduction

Evidence prefix: `storage/logs/product-variation-resolution-`, with `focused.txt`, `regression.txt`, `junit.xml`, `errors.txt`, `frontend.txt`, `build.txt`, `mysql.json`, `mysql-errors.txt`, `runtime.json`, `migration.txt` and `style.txt`.

- Full PHP: `php -d memory_limit=512M vendor/bin/phpunit --colors=never --display-all-issues`.
- Focused PHP: ProductPricingModeTest, SearchPageTest and ReturnWorkflowRevisionTest.
- Frontend: `node --test tests/Frontend/*.test.cjs`; assets: `npm run build`.
- Local/testing disposable MySQL: `php scripts/check-product-variation-resolution.php`.
- Read-only configured inspection: `php scripts/verify-product-variation-resolution.php`.

Both functional audit findings are closed. Actual browser rendering/focus/mobile behavior and production deployment remain outside the verified evidence.
