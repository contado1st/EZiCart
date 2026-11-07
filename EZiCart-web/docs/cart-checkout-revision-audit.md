# Cart and checkout revision audit

Reviewed on 7 November 2026. Scope: the Laravel web buyer cart, product purchase buttons and checkout. Layout references are the supplied Shopee screenshots; colors, icons and branding remain EZiCart's.

## Requested revisions

| Request | Implementation | Result |
| --- | --- | --- |
| Buy Now checks out individually | A separate session draft holds one selected product/variation and its chosen quantity. Saved cart lines are excluded. | Implemented; HTTP tests pass. |
| Cancel Buy Now without adding to cart | Buy Now never writes the saved cart. Cancel clears its draft and returns to the product page. Navigating away does not add a cart line. | Implemented; cancellation leaves cart, stock and orders unchanged. |
| Cart disappears after logout | `buyer_carts` stores each buyer's line identifiers and quantities. Web middleware restores the account cart after login. | Actual logout/password-login test passes, including another buyer's isolation. |
| Checkout only checked items from one shop | Individual and shop checkboxes select multiple products within one shop. Selecting another shop clears the previous selection; mixed-shop checkout is rejected by the server. Only reviewed quantities are ordered and removed. | Same-shop bulk purchase, mixed-shop rejection and duplicate-submission tests pass. |
| Shopee-inspired shopping format | Shop-grouped cart rows, quantity controls, selection summary; address-first checkout, seller shipments, shipping, voucher, payment and final total. | Pages render in HTTP tests; asset build and source layout scan pass. Browser review is blocked. |
| Add original useful details | Persistent-cart note, unavailable-item explanations, Find similar search, shipment preview, explicit saved-item guidance and inline add feedback. | Implemented; relevant interaction tests pass. |

## Buyer walkthrough

1. Select a product variation and quantity. **Add to cart** saves them to your account. With JavaScript enabled, the product page shows success and updates the distinct-product/variation badge. Without JavaScript, the normal form opens the cart.
2. **Buy now** opens checkout for just that product/variation. **Cancel Buy Now** returns to the product without changing saved cart quantities, including previously saved quantities of the same product.
3. In **Shopping cart**, select several products from one shop or use that shop's checkbox to select all its available products. Selecting another shop switches the selection. Quantity changes affect prices; the badge and selection count represent distinct product/variation lines.
4. Unavailable products remain visible with an explanation and cannot be checked. They can remain saved while available selected products are purchased.
5. **Check out** reviews the submitted selection. An empty or invalid selection is rejected on the server. Unchecked products stay saved.
6. Confirm/change a saved delivery address or enter another address. Review the selected shop's products, shipping and order total.
7. Apply an eligible voucher. The existing one-voucher-per-purchase rules apply to selected products only.
8. Review the final COD total and confirm **Place order**. Checkout removes only the reviewed quantities; Buy Now leaves the saved cart untouched.
9. Logout and sign into the same buyer account to restore saved cart items. Another buyer account has its own cart.

## Implementation and safeguards

- Applied migration `2026_11_07_141813_create_buyer_carts_table` on local MySQL. The table has a buyer primary/foreign key, JSON item identifiers/quantities and timestamps. Client prices and seller IDs are not saved as authoritative cart data.
- `BuyerCartService` handles account-scoped storage, catalog display, mutation and purchased-quantity removal. `SyncBuyerCart` loads this cart into the session for existing navigation. A buyer identity change clears the previous session cart and checkout draft before loading the new account.
- A live legacy session cart is imported only if no account cart exists. Once stored, even an empty cart is authoritative: stale sessions cannot overwrite it or restore removed lines.
- Buy Now and cart checkout use separate draft modes. Starting another draft clears the previous voucher and checkout token. Address changes and voucher operations keep the current selection.
- Prices, variants, stock, seller availability and discounts are checked by the existing pricing and purchase services. Checkout keeps integer-cent totals, quote hashes, COD validation, per-shop shipments and existing stock reservation rules.
- Buyer locks serialize cart changes with purchase completion. The purchase and cart consumption commit together. Later additions to the same cart line remain saved; reducing/removing a reviewed quantity blocks checkout without reserving stock.
- Replaying a completed checkout returns the existing purchase without consuming the saved cart again.
- One-shop checkout is checked during selection, preview, voucher application and web order submission. The shared purchase service also checks authoritative shop groups before creating a purchase, so another checkout caller cannot create a new mixed-shop purchase. Product prices and seller identity come from the catalog, not submitted seller fields.
- Add feedback uses text content for server messages, prevents duplicate clicks while saving, handles expired/non-JSON responses and preserves native Buy Now submission. Selection controls also work when optional browser storage is unavailable.

## Verification

| Check | Outcome |
| --- | --- |
| New cart revision tests | 15 HTTP tests pass, including four one-shop checks: same-shop bulk purchase, mixed selection/legacy preview rejection, direct POST bypass rejection and shared purchase-service enforcement |
| Latest targeted PHP run | **44 tests / 319 assertions passed** across cart revisions, checkout integrity, product detail and buyer addresses |
| Latest JavaScript run | **11 tests passed**, including switching shops and restoring an older mixed-shop selection safely |
| Earlier full regression run | Before the one-shop policy revision, 337 tests executed: 336 passed; one failed on the replaced payment-copy sentence. That assertion was updated and its test passed separately (23 assertions). The full suite was not repeated after the one-shop change; the latest targeted results above cover it. |
| Final production build | `npm run build` passed after responsive refinements |
| PHP checks | Affected backend Pint checks and PHP syntax checks passed; Blade templates compiled successfully |
| Database and routes | Local MySQL table/key inspection and checkout route listing passed |
| Source layout scan | No findings from the scoped design detector |
| Desktop/mobile visual review | **Blocked:** browser host and native computer connection unavailable |

The older Buy Now test now asserts the newly requested separate-draft behavior. The cashless-order test still checks that only COD is offered and other methods are rejected; only its obsolete checkout-copy expectation changed.

PHP tests use the isolated test database. No real checkout, stock reservation or payment was submitted in the local application for visual review. No dependencies or native API response structures changed. The shared one-shop purchase rule applies to all checkout callers; native mobile cart controls were not redesigned.

## Operational notes and limits

- Refresh open tabs to load updated styles and scripts. Local production assets have been built. Other environments need their usual migration and build steps.
- The same buyer's saved cart is shared across their tabs. Login sessions, checkout drafts and optional checkbox preferences remain tab-specific. Different buyers have separate saved carts.
- Previously destroyed session contents cannot be recovered. Live legacy carts are imported on the next authenticated buyer web request.
- Normal cart checkout uses the checkbox POST selection. Directly opening checkout without a draft works only when the saved cart contains one shop. A mixed-shop saved cart redirects to the cart so the buyer can select one shop.
- Saving/selecting an item does not reserve stock or guarantee its price. Stock is reserved when the order is placed.
- Payment remains COD. Coins, e-invoices, invented discounts, delivery promises and simultaneous multiple vouchers were not added. Native mobile cart UI/storage is outside this web revision.
- Responsive CSS, semantic forms, labels, keyboard focus and reduced-motion support are implemented. Actual browser rendering remains unverified until browser access is available.

## Relevant files

- `app/Services/BuyerCartService.php`
- `app/Http/Middleware/SyncBuyerCart.php`
- `app/Http/Controllers/Buyer/CartController.php`
- `app/Http/Controllers/Buyer/CheckoutController.php`
- `resources/views/cart/index.blade.php`, `resources/views/checkout/index.blade.php`
- `resources/css/buyer/cart.css`, `resources/css/buyer/checkout.css`
- `public/js/cart-selection.js`, `public/js/cart-feedback.js`
- `tests/Feature/BuyerCartRevisionTest.php`

## Repeat the checks

```powershell
php artisan test --compact tests/Feature/BuyerCartRevisionTest.php tests/Feature/CheckoutIntegrityTest.php tests/Feature/ProductDetailPageTest.php tests/Feature/BuyerAddressTest.php
node --test tests/Frontend/cart-selection.test.cjs tests/Frontend/cart-feedback.test.cjs tests/Frontend/product-selection.test.cjs
npm run build
```
