# Web operations revisions

## Scope and baseline

Requested source: user notes and the attached Laravel web implementation prompt (2 November 2026). Laravel 13 / PHP ^8.3, Blade, Vite 8, Tailwind 4, existing services and private storage are retained. No dependencies are added.

Protected surfaces: `routes/api.php`, `app/Http/Controllers/Api`, `app/Http/Middleware/AuthenticateApiToken.php`, `app/Models/ApiToken.php`, Flutter/Dart/mobile source and mobile resources. Those files were not edited. No Flutter/Dart project files were found inside this repository. Shared additions retain existing API response shapes; newly added web fields are hidden from model serialization. The shared purchase transaction gains additive enforcement for newly configured voucher limits and shop availability, closing bypasses through other checkout callers while retaining legacy open-shop/unlimited-voucher behavior.

## Assessment before implementation

| Requirement | Existing implementation | Required addition |
| --- | --- | --- |
| Staff profiles | Account/profile appearance, rider vehicle fields and assigned service areas exist | Dedicated related-staff views, proposals, admin decisions, immutable history; close immediate-edit bypasses |
| Parcel QR / status flow | Parcel labels, rider badges, scan events, guarded transitions, private final delivery evidence exist | Intermediate-event photos and a unified event/evidence view with responsibility and next-action guidance |
| Voucher recipients | Total redemption limit and transactional checkout exist | Separate unique-buyer claims, recipient cap/counters and per-buyer usage checks in web checkout |
| Chat files | Private two-person text conversations exist | Private attachment storage, participant-only previews/downloads, composer feedback |
| Seller promotions | Platform announcements and shop followers exist | Follower-targeted in-app campaigns, preferences, caps, deduplication and scheduled delivery |
| Activity / availability | Approved/suspended account state exists | Throttled web activity, seller inactivity notices, scheduled closures, web purchase enforcement |
| Tab sessions | Per-tab cookies, URL/header propagation, isolated logout already exist | Retain and regress the existing session behavior |
| Categories | Several conflicting hardcoded lists exist | Shared 12-category catalog with conservative legacy aliases and preserved unmapped categories |
| Variations | Backend type/value/adjustment/stock, stable IDs, retirement, cart/checkout/restoration exist | Seller editor and combinations, validation and buyer option/stock/image feedback |

## Verification and delivery

Implementation, migration and actual check results will be recorded below as each workflow is completed. Existing production data is preserved; only additive migrations are used.

## Completed workflows

| Feature | Implementation and access |
| --- | --- |
| Reviewed staff profiles | `/staff`, `/staff/{staff}`, `/staff/reviews`. Central proposals retain previous/proposed values and reviewer decisions. Sensitive account/appearance/vehicle entry points cannot apply unreviewed changes. Relevant parcel participants can view operational information; unrelated users cannot. Proposal history and license expiry are restricted to staff/reviewing logistics personnel. |
| Intermediate parcel photos | Existing receiving/release/delivery processes retained. `/parcel-evidence` accepts label/reference lookups; order detail pages link to the unified journey. Private photos attach only to the uploader's own accepted scan/tracking event. Three-photo cap, transaction/row lock and content digest prevent duplicate evidence. Existing final-delivery code, COD and mandatory proof behavior remains authoritative. |
| Recipient-limited vouchers | Unique claim per voucher/buyer, configured unique-recipient cap, independent per-buyer use limit, buyer wallet, seller counters and editable limits. Purchase replay is idempotent. Historical order redemptions initialize an old voucher's ledger before its first new claim/limit change; multi-seller purchases are counted once. Claimed/used voucher history is retained when removed from availability. |
| Chat attachments | Existing two-person conversation policy reused through a web upload wrapper. Up to three images/documents; extension, content type and size validation; hashed private filenames; member-only previews/downloads; file-only messages and composer feedback. No change to the API chat contract. |
| Promotions | Sellers schedule product/shop/voucher promotions for opted-in followers. One creation per seller/day, three deliveries per buyer/rolling week, per-campaign/buyer unique delivery ledger, start/expiry/cancellation checks, buyer preference and separate advertisement category. Commerce admins can stop campaigns. Database notifications only. |
| Activity / availability | Throttled web activity, approved-account login timestamps, approximate recent-activity indicators, configurable inactivity warnings, scheduled shop closures/returns. Closed shops remain viewable; new purchases are rejected and existing orders continue. |
| Sessions | Retained per-tab cookies, selectors, CSRF, approval/role protection and isolated logout. Fixed stale cached session-store/guard reuse after a cookie switch. Header-selected login also disables the global remember cookie. Real two-account login/refresh/role/logout coverage added. |
| Categories | Twelve configured web choices, shared aliases for browsing/search/display/reporting, seller product/shop/registration choices. Existing products remain stored as before; ambiguous legacy categories are preserved. |
| Variations | Completed seller row/combination editor, distinct SKUs/combinations, images, server price/stock validation and aggregate stock for submitted editor rows. Buyer selection updates effective price, stock, quantity ceiling and image. Stable IDs, retirement, cart/order variation information and stock restoration remain integrated. Client-supplied file paths are discarded. |

## Database changes

Applied locally: `2026_11_02_141611_add_web_operations_features`, migration batch **25**. `migrate:status` confirms **Ran**. The migration adds:

- User web activity, availability scheduling and promotion-preference fields.
- Voucher recipient limits, claim counts and per-buyer use limits.
- Optional variation SKU and image path fields.
- `staff_profile_revisions`, `voucher_claims`, `message_attachments`, `parcel_evidence`, `seller_promotions`, and `promotion_deliveries`.

Foreign keys preserve referenced operational history. Unique indexes protect pending proposals, recipient claims, per-event evidence and campaign delivery deduplication. No reset, historical migration edit, seed run or account/password change was performed. Existing rows/categories/orders remain intact.

## Security and compatibility

- Protected operations enforce server roles, ownership, conversation membership and parcel involvement.
- Profiles apply only after administrator approval, live identifier uniqueness validation and a stale-proposal check.
- Uploads use private storage, content/extension/size allowlists, no SVG/executable previews and authorization on every read. Public variation images use generated storage paths and the product compliance process.
- Voucher, purchase, evidence and review writes use transactions and locks; uniqueness constraints provide a second boundary.
- Promotion destinations are generated from owned resources, not supplied URLs. Existing notification redirect validation is retained.
- Staff activity/proposal information is not publicly exposed. New web-only model fields are hidden from existing API serialization; this is checked through the existing product endpoint.
- No dependency/package changes. Mobile controllers, API routes/authentication and Flutter/Dart source remain untouched. Native mobile categories intentionally retain their existing API list; the updated category catalog is web-only.

## Operating notes and limitations

See [the user guide](web-operations-user-guide.md) for the workflow and navigation instructions. Promotions need the existing Laravel scheduler running (`php artisan schedule:work` locally); their command is scheduled every five minutes. No live promotional campaigns were created or sent during implementation.

The existing notification interface is text-based, so promotional banner uploads are not introduced. Intermediate-event photos are attached after the authorized scan/handling action rather than making every existing scan require a photo. Final delivery keeps its existing mandatory proof rules. Camera hardware behavior and live desktop/tablet/mobile visual inspection could not be completed: the available browser window changed bounds and was minimized during inspection. HTTP view rendering, Blade compilation and automated workflow checks are recorded separately from visual review.

The composer advertises the effective file limit of the running PHP process (up to the configured 5 MB ceiling). The existing media development launcher supports the larger PHP limits; no server or PHP configuration was silently restarted or changed.

## Check results

- Additive migration: **passed / applied**, verified with `migrate:status`.
- JavaScript syntax checks for composer, variation editor and buyer selection: **passed**.
- Public CSS parser: **passed**.
- Blade compilation: **passed**.
- Vite production build: **passed** after the sandbox blocked its worker process and an approved rerun completed. The pre-existing optional `fontaine` notice remains; no package was added.
- Earlier complete suite: **283 tests / 2,543 assertions passed**.
- Focused final web/session workflows: **16 tests / 182 assertions passed**.
- Final complete suite: **286 tests / 2,563 assertions passed** (including the existing mobile API, full parcel journey, concurrent checkout and tab-session regression cases).
- Formatting, final whitespace checks and protected API-file diff check: **passed**.
- Manual visual/camera review: **not completed**, for the browser condition described above.

Existing tests for media creation and the end-to-end parcel demo now use a category from the requested catalog. The coverage test now expects a pending vehicle proposal instead of an immediate verified edit. Existing test coverage was retained. A concurrent checkout case was added for the final unique-recipient voucher slot.

## File manifest

New files:

- [app/Http/Controllers/ChatAttachmentController.php](../app/Http/Controllers/ChatAttachmentController.php)
- [app/Http/Controllers/ParcelEvidenceController.php](../app/Http/Controllers/ParcelEvidenceController.php)
- [app/Http/Controllers/SellerPromotionController.php](../app/Http/Controllers/SellerPromotionController.php)
- [app/Http/Controllers/ShopAvailabilityController.php](../app/Http/Controllers/ShopAvailabilityController.php)
- [app/Http/Controllers/StaffProfileController.php](../app/Http/Controllers/StaffProfileController.php)
- [app/Http/Controllers/VoucherClaimController.php](../app/Http/Controllers/VoucherClaimController.php)
- [app/Http/Middleware/TrackWebActivity.php](../app/Http/Middleware/TrackWebActivity.php)
- [app/Models/MessageAttachment.php](../app/Models/MessageAttachment.php)
- [app/Models/ParcelEvidence.php](../app/Models/ParcelEvidence.php)
- [app/Models/SellerPromotion.php](../app/Models/SellerPromotion.php)
- [app/Models/StaffProfileRevision.php](../app/Models/StaffProfileRevision.php)
- [app/Notifications/WebOperationsNotification.php](../app/Notifications/WebOperationsNotification.php)
- [app/Services/ChatAttachmentService.php](../app/Services/ChatAttachmentService.php)
- [app/Services/ProductCategoryService.php](../app/Services/ProductCategoryService.php)
- [app/Services/ProductVariationEditorService.php](../app/Services/ProductVariationEditorService.php)
- [app/Services/SellerPromotionService.php](../app/Services/SellerPromotionService.php)
- [app/Services/ShopAvailabilityService.php](../app/Services/ShopAvailabilityService.php)
- [app/Services/StaffProfileService.php](../app/Services/StaffProfileService.php)
- [app/Services/VoucherClaimService.php](../app/Services/VoucherClaimService.php)
- [app/Services/WebCheckoutService.php](../app/Services/WebCheckoutService.php)
- [config/web-commerce.php](../config/web-commerce.php)
- [database/migrations/2026_11_02_141611_add_web_operations_features.php](../database/migrations/2026_11_02_141611_add_web_operations_features.php)
- [docs/web-operations-revision-report.md](../docs/web-operations-revision-report.md)
- [docs/web-operations-user-guide.md](../docs/web-operations-user-guide.md)
- [public/css/web-operations.css](../public/css/web-operations.css)
- [public/js/product-selection.js](../public/js/product-selection.js)
- [public/js/product-variation-editor.js](../public/js/product-variation-editor.js)
- [resources/views/buyer/vouchers.blade.php](../resources/views/buyer/vouchers.blade.php)
- [resources/views/components/parcel-evidence-upload.blade.php](../resources/views/components/parcel-evidence-upload.blade.php)
- [resources/views/components/user-activity.blade.php](../resources/views/components/user-activity.blade.php)
- [resources/views/orders/evidence.blade.php](../resources/views/orders/evidence.blade.php)
- [resources/views/seller/availability.blade.php](../resources/views/seller/availability.blade.php)
- [resources/views/seller/products/variation-fields.blade.php](../resources/views/seller/products/variation-fields.blade.php)
- [resources/views/seller/promotions.blade.php](../resources/views/seller/promotions.blade.php)
- [resources/views/staff/index.blade.php](../resources/views/staff/index.blade.php)
- [resources/views/staff/reviews.blade.php](../resources/views/staff/reviews.blade.php)
- [resources/views/staff/show.blade.php](../resources/views/staff/show.blade.php)
- [tests/Feature/WebModuleSessionTest.php](../tests/Feature/WebModuleSessionTest.php)
- [tests/Feature/WebOperationsTest.php](../tests/Feature/WebOperationsTest.php)

Modified existing files:

- [app/Http/Controllers/AccountController.php](../app/Http/Controllers/AccountController.php)
- [app/Http/Controllers/Auth/AuthController.php](../app/Http/Controllers/Auth/AuthController.php)
- [app/Http/Controllers/Buyer/CartController.php](../app/Http/Controllers/Buyer/CartController.php)
- [app/Http/Controllers/Buyer/CheckoutController.php](../app/Http/Controllers/Buyer/CheckoutController.php)
- [app/Http/Controllers/DirectMessageController.php](../app/Http/Controllers/DirectMessageController.php)
- [app/Http/Controllers/HomeController.php](../app/Http/Controllers/HomeController.php)
- [app/Http/Controllers/Logistics/CoverageController.php](../app/Http/Controllers/Logistics/CoverageController.php)
- [app/Http/Controllers/NotificationController.php](../app/Http/Controllers/NotificationController.php)
- [app/Http/Controllers/ProfileAppearanceController.php](../app/Http/Controllers/ProfileAppearanceController.php)
- [app/Http/Controllers/SearchController.php](../app/Http/Controllers/SearchController.php)
- [app/Http/Controllers/SecureDocumentController.php](../app/Http/Controllers/SecureDocumentController.php)
- [app/Http/Controllers/Seller/ProductController.php](../app/Http/Controllers/Seller/ProductController.php)
- [app/Http/Controllers/Seller/SellerVoucherController.php](../app/Http/Controllers/Seller/SellerVoucherController.php)
- [app/Http/Controllers/SellerShopController.php](../app/Http/Controllers/SellerShopController.php)
- [app/Http/Middleware/SelectTabSession.php](../app/Http/Middleware/SelectTabSession.php)
- [app/Models/DirectMessage.php](../app/Models/DirectMessage.php)
- [app/Models/Product.php](../app/Models/Product.php)
- [app/Models/ProductVariation.php](../app/Models/ProductVariation.php)
- [app/Models/User.php](../app/Models/User.php)
- [app/Models/Voucher.php](../app/Models/Voucher.php)
- [app/Providers/AppServiceProvider.php](../app/Providers/AppServiceProvider.php)
- [app/Services/AnalyticsService.php](../app/Services/AnalyticsService.php)
- [app/Services/PlacePurchaseService.php](../app/Services/PlacePurchaseService.php)
- [app/Services/ProfileAppearanceService.php](../app/Services/ProfileAppearanceService.php)
- [bootstrap/app.php](../bootstrap/app.php)
- [config/registration.php](../config/registration.php)
- [public/js/communications.js](../public/js/communications.js)
- [resources/views/account/edit.blade.php](../resources/views/account/edit.blade.php)
- [resources/views/admin/compliance/index.blade.php](../resources/views/admin/compliance/index.blade.php)
- [resources/views/components/order-deadlines.blade.php](../resources/views/components/order-deadlines.blade.php)
- [resources/views/components/workspace-sidebar.blade.php](../resources/views/components/workspace-sidebar.blade.php)
- [resources/views/home.blade.php](../resources/views/home.blade.php)
- [resources/views/home-guest.blade.php](../resources/views/home-guest.blade.php)
- [resources/views/layouts/app.blade.php](../resources/views/layouts/app.blade.php)
- [resources/views/layouts/message-panel.blade.php](../resources/views/layouts/message-panel.blade.php)
- [resources/views/notifications/index.blade.php](../resources/views/notifications/index.blade.php)
- [resources/views/orders/messages.blade.php](../resources/views/orders/messages.blade.php)
- [resources/views/products/show.blade.php](../resources/views/products/show.blade.php)
- [resources/views/search/index.blade.php](../resources/views/search/index.blade.php)
- [resources/views/seller/products/create.blade.php](../resources/views/seller/products/create.blade.php)
- [resources/views/seller/products/edit.blade.php](../resources/views/seller/products/edit.blade.php)
- [resources/views/seller/products/index.blade.php](../resources/views/seller/products/index.blade.php)
- [resources/views/seller/products/show.blade.php](../resources/views/seller/products/show.blade.php)
- [resources/views/seller/vouchers/index.blade.php](../resources/views/seller/vouchers/index.blade.php)
- [resources/views/shops/show.blade.php](../resources/views/shops/show.blade.php)
- [routes/console.php](../routes/console.php)
- [routes/web.php](../routes/web.php)
- [tests/Feature/CheckoutConcurrencyTest.php](../tests/Feature/CheckoutConcurrencyTest.php)
- [tests/Feature/LogisticsWorkflowTest.php](../tests/Feature/LogisticsWorkflowTest.php)
- [tests/Feature/ProductMediaTest.php](../tests/Feature/ProductMediaTest.php)
- [tests/Feature/RoadmapFeaturesTest.php](../tests/Feature/RoadmapFeaturesTest.php)
