# Cross-project formatting correction plan

15 November 2026. Follow-up to the cart price/quantity and mobile sticky-summary corrections.

## Scope and findings

Inspect the Laravel web view/style inventory for the same failure classes: unshrinkable flex/grid children, fixed columns without a narrow-container fallback, unbroken text crossing neighboring controls, inline actions with no wrapping gap, and checkbox/radio controls inheriting full-width text-field styles. Source inventory covers 169 Blade views and 48 source/public stylesheets at discovery; this is not a claim of 169 browser reviews.

Confirmed source findings:

- Checkout's broad input selector includes the wallet pay-later checkbox. Payment choices are adjacent inline links without an action group. Inner product-name flex items do not shrink explicitly; voucher removal includes a potentially long code.
- Seller voucher and admin dispute grids lack their own small-container collapse. Their child panels can retain intrinsic widths even when shared tables scroll.
- Feature forms have flexible labels without a zero minimum width; feature grids have a fixed 210px floor. Some narrow labels/long names can push controls beyond the available track.
- Product-page seller/review/fact flex children lack explicit shrink/wrap protection for long user text.
- Mobile storefront navigation scrolls, but its child links may shrink and its hidden scrollbar gives no indication that more account actions exist.

## Implementation

1. Reuse current page styles and shared responsive rules. Add shrink/wrap protection only to named components; preserve intentionally scrolling data tables and whole action words.
2. Group checkout payment links with a wrapping gap; size checkbox/radio controls independently of text inputs. Preserve form names, selected payment parameters, validation, confirmation and order logic.
3. Add container-aware collapse for voucher/dispute grids and bound their children. Correct feature-form/grid minimum widths and long-content handling in product/profile/address components where supported by source.
4. Preserve role colors/fonts, pink frame hierarchy, neutral notifications, cart selection and all permissions/business services. No dependencies, schema or financial operations.

## Verification

Parse CSS, build assets, run the existing relevant render/checkout/module tests and frontend interaction suites. Use existing authenticated buyer/seller/admin sessions for bounded desktop, intermediate and mobile previews; no order/payment/approval submissions. Fix defects revealed in one batch and confirm once. Record observed states, empty-data limits and unreviewed areas honestly in a separate audit. Do not treat a source scan, green parser or earlier dashboard review as new visual verification.
