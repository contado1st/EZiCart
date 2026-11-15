# Change product variations from the saved cart

15 November 2026. Buyer web/Laravel implementation.

## Buyer behavior

- Open **Shopping cart > Change variation** beneath an item with active variation options. Choose an option and press **Save variation**.
- The saved quantity stays the same. If the target option is already in the cart, quantities combine rather than overwriting a line. Stock, the 20-unit line limit and product purchase limits must still pass; rejected changes keep the original cart intact.
- Prices, promotion totals, image and stock information refresh from the catalog. Dropdown prices are current regular/sale estimates; selected-item offers are recalculated through the existing quote flow.
- Sold-out/insufficient options are disabled. Retired options are omitted; a previously retired saved option can be replaced with an available one. Base products do not show variation controls.
- Selected checkboxes move to the new variation key; duplicate selection keys merge. Unchecked products and other-shop selections stay unchecked.

## Integrity

New PATCH route `cart.variation` uses the existing authenticated buyer boundary and operational throttle. The service reads the product and quantity from that buyer's saved line, validates active option ownership and variation mode, and writes the change under the existing buyer lock and a database transaction. Catalog/option stock, listing/shop status, quantity and purchase-limit checks run through existing services. Client price, product, seller and quantity fields cannot override the change.

A successful change invalidates that tab's cart checkout draft, token, quote hash and voucher so checkout must review current contents. Unrelated Buy Now drafts remain separate. Failed changes preserve both the draft and saved cart. No migration or dependency was added, and changing a saved cart does not reserve/decrement stock or create an order.

## Fresh verification

| Check | Result |
| --- | --- |
| New CartVariationChangeTest | 21 tests, 102 assertions passed |
| Combined cart/checkout/pricing/concurrency regression run | 63 tests, 398 assertions passed; isolated test databases |
| All frontend tests | 80 passed, including two new variation-selection remapping cases |
| Production build | Passed; Vite reported 3.16 seconds, existing optional fontaine notice |
| Scoped PHP formatting | Pint passed for the service, controller and new feature test |
| Syntax / templates / CSS | PHP lint, Blade view compilation, cart stylesheet parse and git diff --check passed |

The initial new-test fixture omitted required contact numbers; those fixtures were corrected. The render regression caught a Blade include adjacent to an endif directive not compiling; the include now sits on its own line and the render assertion passes. These failures are resolved.

## Browser/limits

Populated variation-editor manual browser review was **not run**: a read-only check of the current configured demo database found zero active variations and zero products with active variations. No live catalog/cart data was inserted or changed to manufacture a preview. The real Blade cart is rendered by the feature tests and its variation controls are asserted there.

Tests cover pricing, merging, rollback, retired/inactive/foreign options, insufficient stock, buyer isolation, malformed input, base-mode hiding, draft separation and selection remapping. Physical-device layout and a populated manual browser preview remain unverified. This does not claim a live purchase or production deployment.
