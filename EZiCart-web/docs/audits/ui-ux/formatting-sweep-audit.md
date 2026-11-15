# Cross-project formatting sweep audit

15 November 2026. Follows [the formatting plan](formatting-sweep-plan.md). This is a focused overlap/wrapping pass, not a redesign or certification of every UI state.

## Corrections

| Area | Correction |
| --- | --- |
| Cart (preceding fix retained) | Price breakdown wraps inside its own column; mobile checkout summary stays in normal document flow |
| Checkout | Payment choices use a wrapping action group; wallet checkbox/radio controls are excluded from full-width text-field styling; nested product text can shrink; long voucher removal labels can wrap |
| Seller vouchers and admin disputes | Named grids respond to available workspace width and collapse to one column at 850px of content space; child panels can shrink while wide data tables scroll |
| Voucher form introduction | Separate the explanatory paragraph and sales-management link from the first field; this missing gap was observed in desktop/mobile previews |
| Shared feature forms/cards | Labels have bounded, shrinkable widths; 210px card tracks cannot exceed their container; long contents can wrap |
| Review photo removal controls | Shared feature-form checkbox/radio controls no longer inherit full-width 40px text-field styling; direct checkbox labels stay beside their controls and fieldsets can shrink |
| Product details | Seller identity, purchase facts and review flex children can shrink and wrap long user text |
| Messaging | Composer helper text and send action wrap as a group instead of squeezing one another |
| Address/profile details | Address-card headings can wrap with their status; profile description text can break long tokens |
| Mobile storefront navigation | Account links do not shrink into one another; horizontal scrolling has a visible thin scrollbar; compiled and fallback styles agree |

No controllers, business services, permissions, schemas, dependencies, financial values or confirmation logic changed. Pink frame rules and notification exceptions are preserved. No live financial, order or approval submission is used for preview.

## Fresh checks

- **79 Laravel tests passed, 736 assertions:** DashboardNavigationTest, CheckoutIntegrityTest, BuyerCartRevisionTest, ProductDetailPageTest, ProfileShopTest, WebOperationsTest, PartialVoucherIntegrationTest. Existing tests, isolated test database.
- **41 additional Laravel tests passed, 352 assertions:** OrderMessagingTest and ReturnWorkflowRevisionTest. Total for these distinct suites in this pass: **120 tests, 1,088 assertions**.
- **16 review integration tests passed, 130 assertions:** PartialReviewIntegrationTest after finding the photo-removal checkbox styling issue. Final distinct total for this pass: **136 Laravel tests, 1,218 assertions**.
- **78 frontend tests passed:** all existing frontend test files.
- **Final production build passed:** Vite reported 2.96 seconds after the voucher introduction spacing correction; existing optional fontaine notice only.
- **All 48 source/public stylesheets parsed** with installed PostCSS. Source inventory contains 169 Blade views; a source scan is not a browser pass.
- **Blade compilation passed:** php artisan view:cache --no-interaction completed successfully.
- **git diff --check passed**, with the pre-existing CRLF normalization notice.
- **Eight canonical pink-frame rule records unchanged** from the original baseline.

## Browser coverage

The expired local seller session was restored with its existing seed account. The seller voucher page was observed at desktop width with a bounded form and independently contained empty table; no voucher was created. Earlier role-dashboard and finance previews belong to their own audits and are not new evidence for this sweep.

- Seller vouchers: desktop and 400 x 768; all form sections fit, table follows the form on mobile and scrolls inside its frame. Live voucher history was empty.
- Admin announcements: 400 x 768; field groups fit, long heading wraps, controls remain spaced. Published notices were empty; no notice was submitted.
- Admin disputes list: 400 x 768; heading and filters fit; live cases were empty. Populated detail layout was source-reviewed and existing return-workflow tests passed, but it was not visually reviewed with a live case.
- Product detail: desktop overview observed with separated shipping/payment facts, quantity control and cart/purchase actions; no purchase completed.
- Checkout: Buy Now desktop review and wallet 400 x 768 review observed. Address/product rows, voucher controls, seller notes, wrapping payment choices, correctly sized pay-later checkbox, separated totals and Place order action fit their sections. The temporary checkout was cancelled through its normal UI; no order/payment was submitted.
- Mobile storefront navigation: the visible horizontal scrollbar was observed in the checkout header; account links retain their width.
- Final voucher spacing confirmation: after the final asset build, the mobile introduction, management link and first field had visible separation. No voucher form was submitted.

Native preview bounds changed unexpectedly during cart interaction; no selected-cart checkout visual pass is claimed from that attempt. The browser extension host was unavailable. The existing product's Buy Now review subsequently opened successfully: desktop address/product rows, voucher field, seller notes and spaced payment choices were observed. No Place order action was used. Remaining source/isolated-test verification must not be presented as browser proof.

## Limits

No exhaustive long-data/empty/error matrix, physical-device, assistive-technology or runtime performance certification is claimed. Intentionally scrolling financial/operational tables retain their columns and controls. Print layouts and document exports are outside this formatting change. No deployment command or new server is introduced.

## Completion and changed source

The identified formatting defects are corrected. Shared fixes benefit module pages using the same components; this does not mean each of the 169 Blade views received an individual live screenshot review. Messaging composer, populated review-photo controls, long synthetic names and populated dispute detail were source/test verified rather than live visually reviewed in this pass. No claim is made that all possible formatting issues in every data state have been eliminated.

Changed in this sweep: `public/css/responsive-layout.css`, `roadmap-features.css`, `product-detail.css`, `communications.css`, `navigation-fallback.css`, `buyer-addresses.css`, `profiles.css`; `resources/css/buyer/checkout.css`, `shared/app.css`, `seller/seller-vouchers.css`; and `resources/views/checkout/index.blade.php`. The preceding cart CSS correction and earlier dashboard revisions remain intact. These presentation-only hunks can be reverted independently; no data rollback is needed.
