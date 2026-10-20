# Future feature roadmap implementation report

Date: 2026-10-21. Input: `C:/Users/Admin/Downloads/future_feature_implementation_roadmap.md`.

This extension fills gaps in the roadmap while reusing the application's existing commerce, logistics, authorization, inventory, voucher, and COD finance services. “Reused” means the capability already existed and was retained; “Extended” means existing behavior gained the listed capability; “Added” means a missing capability was implemented. Source inspection supports the matrix; it does not establish that every roadmap requirement has automated or visual test coverage.

## Roadmap coverage

All source paths below are relative to the project root.

| ID | Result | Implementation and source evidence |
|---|---|---|
| USR-01 | Reused | Multiple saved buyer addresses and checkout selection: `app/Services/BuyerAddressService.php`, `app/Http/Controllers/Buyer/BuyerAddressController.php`. |
| SEL-01 | Reused | Required business permit uploads on seller registration/resubmission, private document storage and authorized access: `app/Http/Controllers/Auth/AuthController.php`, `app/Http/Controllers/SecureDocumentController.php`. |
| SEL-02 | Reused | Identity document registration and protected document access: the same auth/document controllers and `app/Services/RegistrationIdentityService.php`. |
| SEL-03 | Reused | Seller account approval/rejection and status presentation: `app/Http/Controllers/Admin/AdminController.php`, auth application flows, existing seller views. |
| BUY-01 | Added | Category recommendations from recent browsing and eligible purchases, clear browsing history: `app/Services/ProductRecommendationService.php`, `app/Http/Controllers/Buyer/DiscoveryController.php`, `resources/views/buyer/for-you.blade.php`. |
| BUY-02 | Extended | Buyer/seller chat inbox and shopping dock reuse existing authorized order conversations: `app/Http/Controllers/ChatInboxController.php`, `app/Http/Controllers/OrderMessageController.php`, `resources/views/orders/inbox.blade.php`, `resources/views/layouts/app.blade.php`. |
| BUY-03 | Added | Product stock alert opt-in/out and notifications when availability changes: `app/Services/StockSubscriptionService.php`, `app/Notifications/StockAvailabilityNotification.php`, `resources/views/buyer/stock.blade.php`. |
| BUY-04 | Extended | Visible seller, cancellation, and rider deadlines with existing seller expiry handling and new cancellation/rider automation: `resources/views/components/order-deadlines.blade.php`, `public/js/order-deadlines.js`, `app/Services/OrderTransitionService.php`. |
| PRM-01 | Reused | Platform-funded voucher management: `app/Http/Controllers/Admin/PlatformVoucherController.php`, `app/Models/Voucher.php`. |
| PRM-02 | Extended | Existing seller-funded store vouchers gain create/toggle/delete audit records and code normalization before uniqueness validation: `app/Http/Controllers/Seller/SellerVoucherController.php`. |
| PRM-03 | Reused | Eligibility, minimum eligible spend, type, expiry, usage limits and funding allocation remain in `app/Models/Voucher.php`, `app/Services/CheckoutPricingService.php`, `app/Services/PlacePurchaseService.php`. |
| ORD-01 | Added | Buyer cancellation request becomes a distinct pending request, pauses fulfillment, and notifies seller/buyer: `app/Models/OrderCancellationRequest.php`, `app/Services/OrderTransitionService.php`, buyer order views. |
| ORD-02 | Extended | Seller approval uses locked centralized cancellation resolution, inventory restoration and uncollected COD voiding: `app/Services/OrderTransitionService.php`, `app/Services/InventoryRestorationService.php`, `app/Services/OrderFinancialService.php`, `app/Http/Controllers/Seller/CancellationController.php`. |
| ORD-03 | Extended | Request/approval/rejection notifications and tracking/audits reuse transaction-aware delivery; existing executed-refund workflows remain: `app/Notifications/OrderWorkflowNotification.php`, `app/Services/OrderTransitionService.php`, `app/Services/OrderFinancialService.php`. |
| ORD-04 | Reused | Confirmation/status notifications continue through `app/Services/OrderTransitionService.php` and existing order notification classes. |
| LOG-01 | Reused | Platform-managed logistics and existing internal hub/courier workflow retained; provider is recorded in `config/commerce.php`. No external courier integration was introduced. |
| LOG-02 | Reused | Municipality-to-hub/service-area mapping: `app/Models/AreaMunicipality.php`, `app/Models/Area.php`, `app/Services/OrderAreaService.php`, existing logistics administration. |
| LOG-03 | Extended | Explicit barangay rider coverage within existing municipality service areas: `app/Http/Controllers/Logistics/CoverageController.php`, `app/Services/LogisticsCoverageService.php`. |
| LOG-04 | Added | One primary and optional distinct backup per normalized municipality/barangay, enforced by a unique database key; non-primary dispatch requires a reason: coverage controller/service and new migration. |
| LOG-05 | Extended | Existing municipality/address mapping is reused for barangay coverage and dispatch checks: `app/Services/OrderAreaService.php`, `app/Services/LogisticsCoverageService.php`, `app/Http/Controllers/Logistics/LogisticsController.php`. |
| LOG-06 | Added | Explicit `two_wheel` rider classification and vehicle description: coverage controller and `resources/views/logistics/coverage.blade.php`. |
| LOG-07 | Added | Explicit `four_wheel` classification using the existing courier role, with vehicle description: the same coverage controller/view. |
| LOG-08 | Extended | Authorized logistics administrator sets PHP 50–80 standard shipping per seller parcel; new quotes read the database setting: coverage controller, `app/Services/CheckoutPricingService.php`. |
| LOG-09 | Reused | Assignment notifications use email and database channels: existing logistics assignment workflow and `app/Notifications/OrderWorkflowNotification.php`. SMS was not added. |
| LOG-10 | Added | Rider acceptance endpoint, countdown and expired-assignment escalation to the hub dispatch queue: `app/Http/Controllers/Courier/AssignmentAcceptanceController.php`, `app/Services/OrderTransitionService.php`, `app/Console/Commands/ProcessRoadmapAutomation.php`. |
| ANA-01 | Extended | Existing order/sales/delivery dashboard metrics gain marketplace buyer/seller/product counts: `app/Http/Controllers/Admin/AdminController.php`, `resources/views/admin/dashboard.blade.php`. |
| ANA-02 | Extended | Existing finance commission records reused; new reports recognize commissions only for completed, nonrefunded orders with verified remittance: `app/Services/OrderFinancialService.php`, `app/Services/SalesReportService.php`. |
| ANA-03 | Extended | Order-level gross sales reporting with explicit recognition rules: `app/Services/SalesReportService.php`, `resources/views/reports/sales.blade.php`. |
| ANA-04 | Extended | Itemized seller/platform discounts, executed refunds, subsidy reversal and recognized commission produce net seller sales: the same report service/view. |
| ANA-05 | Added | Excel-compatible UTF-8 CSV and native paginated PDF exports: `app/Services/TabularExportService.php`, `app/Http/Controllers/SalesReportController.php`. CSV is the spreadsheet format; XLSX is not generated. |
| ANA-06 | Added | Date range, seller, product, order status and municipality filters under seller ownership or commerce administrator authorization: sales report controller/service. |
| ADM-01 | Extended | Existing commerce administration retained, with report, expanded dashboard and audit-search entry points: admin controller, workspace sidebar and `routes/web.php`. |
| ADM-02 | Extended | Existing logistics administration gains coverage, vehicle classification, shipping policy and rider acceptance escalation: coverage/logistics controllers, coverage/dispatch views. |
| ADM-03 | Extended | Existing administrative audit store reused for new cancellation, coverage, shipping, vehicle, rider and seller-voucher events: `app/Services/AdministrativeAuditService.php` and relevant services/controllers. |
| ADM-04 | Extended | Actor, entity type/ID, action, timestamp, reason and before/after payloads reused for these new events: audit service and call sites. This is important-event auditing, not an exhaustive record of every page visit. |
| ADM-05 | Added | Authorized audit search by actor ID, module, action, dates, entity ID and type, with commerce/logistics domain restrictions: `app/Http/Controllers/Admin/ActivityLogController.php`, `resources/views/admin/activity.blade.php`. |
| ADM-06 | Reused/extended | Existing role/account middleware and server ownership checks retained; new endpoints validate role, seller ownership or hub/service-area scope: `routes/web.php`, `app/Http/Middleware/CheckRole.php`, new controllers/services. |

## Entry points and behavior choices

- Buyers: `/buyer/for-you` and `/buyer/stock-alerts`; stock opt-in is also available on product detail. Browsing uses the last 90 days; delivered/completed purchase categories carry greater weight. Clearing history removes browsing interactions, while eligible purchase history still informs recommendations. Cold starts show eligible recent products.
- Buyers and sellers: `/chat`, workspace navigation and a lower-left shopping dock. Conversations remain order scoped and reuse existing messaging permissions. This adds no live push transport or unsolicited pre-order chat.
- Buyer/seller order detail: cancellation request/decision controls and deadline displays. Pending cancellation is a separate record so existing `OrderStatus` values remain authoritative. All status transitions are blocked while cancellation is pending, including direct seller cancellation. Requests are available only before physical parcel custody; the seller has 24 hours by default. Automation approves unanswered requests. Rejection extends seller response/preparation deadlines by the time fulfillment was paused. The existing COD model means pre-custody cancellation has no collected payment to refund; existing paid return/refund processing is retained.
- Sorting center/logistics administrator: `/logistics-coverage`; configure primary/backup barangay riders and two-/four-wheel descriptions. Existing municipality coverage continues until explicit barangay coverage is configured. Backup or other non-primary dispatch requires an explanatory reason and still respects existing hub/service-area eligibility. Classification does not create a new driver role or automatic capacity optimizer.
- Logistics administrator: shipping controls on coverage. The configured PHP 50–80 fee applies to future checkout quotes per seller parcel. Existing orders retain their recorded shipping. No distance/size tariff engine was introduced.
- Couriers: assignment acceptance on order detail. Default window is 15 minutes. An on-time physical hub release scan also records acceptance. Unaccepted expired assignments return to `SORTED`, clear the rider and notify the former rider/hub; hub staff select a replacement manually.
- Sellers and commerce administrators: `/sales-reports`, also linked from existing reports. Seller scope is enforced server side. Product filtering selects orders containing that product; monetary totals remain whole-order totals. Dates filter order creation time. Defaults cover the current month. PDF requires 2,000 orders or fewer; larger selections use streaming CSV. CSV protects spreadsheet formula-like cell values. PDF uses a basic native text layout and transliterates text to the base font encoding.
- Commerce/logistics administrators: `/activity-logs`. Domain access follows actor roles, with system events available in either authorized domain. This searches the existing administrative audit store rather than merging every operational event table.

Report accounting uses integer cents. Gross is recognized for completed orders and previously delivered orders with executed refunds. Seller-funded discounts reduce seller sales; platform-funded discounts are separately shown. Executed merchandise refunds are capped at the discounted merchandise amount; a full merchandise refund reverses the platform subsidy. Recognized commission requires completed status, no executed refund and verified remittance. Net seller sales is gross minus seller discount, merchandise refund, platform subsidy reversal and recognized commission, floored at zero. Shipping and total executed refunds remain separate columns. These are sales reports, not a replacement for the existing COD settlement ledger.

## Operations

The additive migration is `database/migrations/2026_10_21_020157_add_roadmap_feature_records.php`. It creates product interactions, stock subscriptions, cancellation requests, barangay rider assignments and commerce settings, plus courier vehicle fields. The implementation owner reports that it was applied successfully to the local database. Deployments must apply this migration before exposing the new endpoints. No dependency changes were made.

`routes/console.php` schedules `commerce:process-automation` every minute with overlap protection. Configure Laravel's scheduler on the target environment for cancellation auto-approval, rider deadline escalation and stock-state polling. Existing seller deadline and parcel alert schedules continue separately. Displayed countdowns do not themselves execute server transitions.

For local development, keep these running in separate terminals:

```powershell
php artisan schedule:work
php artisan queue:work
```

Use the deployment environment's persistent scheduler/worker processes in production. The migration was applied locally; workers were not started by this implementation run.

Stock subscriptions notify only on a change from the recorded availability state; the default low-stock threshold is 5. Notifications use database and email channels through `TransactionAwareNotificationSender`. Non-database channels dispatch after commit to `DeliverNotificationChannels`; configure mail delivery and a queue worker unless the queue connection is synchronous. Mail delivery and automation against production data were not exercised for this documentation pass.

## Design continuity and drift

The extension reuses incumbent workspace layouts, cards, icons, navigation and role palettes. Supplemental rules live in `public/css/roadmap-features.css`; deadline behavior lives in `public/js/order-deadlines.js`. It does not introduce a global token/font rewrite. `DESIGN.md` and `.impeccable/design.json` remain unchanged.

`PRODUCT.md` describes the earlier dashboard redesign and its prohibition on schema/workflow changes for that design task. This report records the separately requested roadmap implementation, including its explicit schema/workflow additions; the older product document was not rewritten. No new visual identity was inferred. Browser-based responsive/accessibility review remains blocked because the available computer-use inventory contained no browser or app surfaces. Source implementation intent is not visual verification.

## Recorded validation

The implementation owner supplied these results; this documentation task did not run tests, builds, automation or mail:

- `RoadmapFeaturesTest`: passed, 17 tests / 123 assertions.
- `LogisticsWorkflowTest`: passed, 89 tests / 839 assertions.
- Earlier combined focused run of RoadmapFeaturesTest (then 13 tests), WorkflowRevisionTest, BuyerAddressTest and OrderMessagingTest: passed, 58 tests / 406 assertions. This overlaps later runs and is not an additional independent coverage total.
- Independent review found a direct seller cancellation bypass while a cancellation request was pending; the transition guard and seller form were corrected. A legacy already-cancelled approval audit branch was also corrected. Reviewer reported the code findings resolved.
- Frontend production build: passed. The pre-existing optional `fontaine` warning remains; no package was added.
- Blade template compilation: passed. Supplemental CSS parsed with zero warnings; deadline JavaScript syntax and `git diff --check` passed. PHP formatting applied with Pint.
- Visual browser review: blocked. Live mail delivery, production scheduler/queue operation and exhaustive roadmap coverage: not verified.
