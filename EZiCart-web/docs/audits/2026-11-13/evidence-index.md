# Evidence index

Code anchors were resolved against the actual source, not guessed. Related test names identify regression coverage, not one-to-one execution of teacher cases. Missing features were searched across Laravel routes, controllers, services, models, migrations and Blade views. Flutter was not inspected for feature compliance.

| Evidence key | Source | Related tests or verification scope |
| --- | --- | --- |
| auth | [app/Http/Controllers/Auth/AuthController.php:203](../../../app/Http/Controllers/Auth/AuthController.php#L203) | LogisticsWorkflowTest; RegistrationFlowTest; WebModuleSessionTest |
| roles | [routes/web.php:99](../../../routes/web.php#L99) | WorkflowRevisionTest; LogisticsWorkflowTest; RegistrationReviewTest |
| profile | [app/Http/Controllers/AccountController.php:38](../../../app/Http/Controllers/AccountController.php#L38) | ProfileChangeApprovalTest; LogisticsWorkflowTest; audit probes |
| appearance | [app/Http/Controllers/ProfileAppearanceController.php:13](../../../app/Http/Controllers/ProfileAppearanceController.php#L13) | ProfileShopTest; ProfileChangeApprovalTest |
| staff | [app/Services/StaffProfileService.php:35](../../../app/Services/StaffProfileService.php#L35) | WebOperationsTest; ProfileChangeApprovalTest |
| address | [app/Services/BuyerAddressService.php:36](../../../app/Services/BuyerAddressService.php#L36) | BuyerAddressTest; audit probes |
| category | [app/Services/ProductCategoryService.php:33](../../../app/Services/ProductCategoryService.php#L33) | WebOperationsTest; audit product probe |
| product | [app/Http/Controllers/Seller/ProductController.php:33](../../../app/Http/Controllers/Seller/ProductController.php#L33) | ProductComplianceTest; ProductMediaTest; audit probes |
| delete | [app/Http/Controllers/Seller/ProductController.php:268](../../../app/Http/Controllers/Seller/ProductController.php#L268) | ProductComplianceTest |
| media | [app/Services/ProductMediaService.php:16](../../../app/Services/ProductMediaService.php#L16) | ProductMediaTest |
| variation | [app/Services/ProductVariationEditorService.php:12](../../../app/Services/ProductVariationEditorService.php#L12) | WebOperationsTest |
| search | [app/Http/Controllers/SearchController.php:17](../../../app/Http/Controllers/SearchController.php#L17) | SearchPageTest |
| pdp | [app/Http/Controllers/HomeController.php:52](../../../app/Http/Controllers/HomeController.php#L52) | ProductDetailPageTest; WebOperationsTest |
| follow | [app/Services/ShopFollowService.php:10](../../../app/Services/ShopFollowService.php#L10) | ProfileShopTest |
| cart | [app/Services/BuyerCartService.php:15](../../../app/Services/BuyerCartService.php#L15) | BuyerCartRevisionTest; CheckoutIntegrityTest |
| checkout | [app/Services/PlacePurchaseService.php:24](../../../app/Services/PlacePurchaseService.php#L24) | CheckoutIntegrityTest; BuyerCartRevisionTest; FinancialLedgerWorkflowTest |
| checkoutform | [app/Http/Controllers/Buyer/CheckoutController.php:106](../../../app/Http/Controllers/Buyer/CheckoutController.php#L106) | CheckoutIntegrityTest; BuyerCartRevisionTest |
| voucher | [app/Http/Controllers/Seller/SellerVoucherController.php:27](../../../app/Http/Controllers/Seller/SellerVoucherController.php#L27) | RoadmapFeaturesTest; FinancialLedgerWorkflowTest; audit probes |
| claim | [app/Services/VoucherClaimService.php:12](../../../app/Services/VoucherClaimService.php#L12) | WebOperationsTest; RoadmapFeaturesTest |
| budget | [app/Services/VoucherFundingService.php:18](../../../app/Services/VoucherFundingService.php#L18) | FinancialLedgerWorkflowTest |
| discount | [app/Models/Voucher.php:67](../../../app/Models/Voucher.php#L67) | CheckoutIntegrityTest; FinancialLedgerWorkflowTest |
| pricing | [app/Services/CheckoutPricingService.php:19](../../../app/Services/CheckoutPricingService.php#L19) | CheckoutIntegrityTest; FinancialLedgerWorkflowTest |
| shipping | [app/Services/DeliveryPricingService.php:47](../../../app/Services/DeliveryPricingService.php#L47) | FinancialLedgerWorkflowTest |
| tariff | [app/Services/DeliveryRateManagementService.php:13](../../../app/Services/DeliveryRateManagementService.php#L13) | FinancialLedgerWorkflowTest |
| stock | [app/Services/InventoryRestorationService.php:12](../../../app/Services/InventoryRestorationService.php#L12) | CheckoutIntegrityTest; WorkflowRevisionTest |
| lowstock | [app/Http/Controllers/Seller/SellerController.php:32](../../../app/Http/Controllers/Seller/SellerController.php#L32) | ModuleRevisionTest; StockSubscriptionService is buyer subscriptions |
| shop | [app/Services/ShopAvailabilityService.php:12](../../../app/Services/ShopAvailabilityService.php#L12) | WebOperationsTest |
| promotion | [app/Services/SellerPromotionService.php:12](../../../app/Services/SellerPromotionService.php#L12) | WebOperationsTest |
| sellerorder | [app/Http/Controllers/Seller/SellerOrderController.php:27](../../../app/Http/Controllers/Seller/SellerOrderController.php#L27) | LogisticsWorkflowTest |
| transition | [app/Services/OrderTransitionService.php:25](../../../app/Services/OrderTransitionService.php#L25) | LogisticsWorkflowTest; HubTransferFlowTest |
| cancel | [app/Services/OrderTransitionService.php:241](../../../app/Services/OrderTransitionService.php#L241) | RoadmapFeaturesTest; audit probes |
| cancelcutoff | [app/Services/OrderTransitionService.php:565](../../../app/Services/OrderTransitionService.php#L565) | LogisticsWorkflowTest; audit probes |
| buyerorder | [app/Http/Controllers/Buyer/BuyerController.php:17](../../../app/Http/Controllers/Buyer/BuyerController.php#L17) | BuyerCartRevisionTest; LogisticsWorkflowTest |
| dispute | [app/Http/Controllers/Buyer/DisputeController.php:31](../../../app/Http/Controllers/Buyer/DisputeController.php#L31) | WorkflowRevisionTest; audit probes |
| return | [app/Services/ReturnPickupService.php:15](../../../app/Services/ReturnPickupService.php#L15) | ModuleRevisionTest; WorkflowRevisionTest |
| inspection | [app/Http/Controllers/Seller/ReturnInspectionController.php:15](../../../app/Http/Controllers/Seller/ReturnInspectionController.php#L15) | WorkflowRevisionTest |
| review | [app/Http/Controllers/Buyer/ReviewController.php:12](../../../app/Http/Controllers/Buyer/ReviewController.php#L12) | ProductDetailPageTest; audit probes |
| chat | [app/Http/Controllers/DirectMessageController.php:62](../../../app/Http/Controllers/DirectMessageController.php#L62) | CommunicationCenterTest; OrderMessagingTest; WebOperationsTest |
| notifications | [app/Services/TransactionAwareNotificationSender.php:15](../../../app/Services/TransactionAwareNotificationSender.php#L15) | LogisticsWorkflowTest; CommunicationCenterTest |
| notifychannels | [app/Notifications/OrderStatusNotification.php:24](../../../app/Notifications/OrderStatusNotification.php#L24) | LogisticsWorkflowTest (fake mail transport) |
| finance | [app/Services/OrderFinancialService.php:18](../../../app/Services/OrderFinancialService.php#L18) | WorkflowRevisionTest; FinancialLedgerWorkflowTest |
| payout | [app/Services/OrderFinancialService.php:176](../../../app/Services/OrderFinancialService.php#L176) | WorkflowRevisionTest; FinancialLedgerWorkflowTest |
| withdraw | [app/Services/WalletWithdrawalService.php:14](../../../app/Services/WalletWithdrawalService.php#L14) | FinancialLedgerWorkflowTest; audit probes |
| wallet | [app/Services/WalletFundingService.php:16](../../../app/Services/WalletFundingService.php#L16) | FinancialLedgerWorkflowTest |
| settlement | [app/Services/LogisticsSettlementService.php:18](../../../app/Services/LogisticsSettlementService.php#L18) | FinancialLedgerWorkflowTest |
| batches | [app/Services/RemittanceBatchService.php:42](../../../app/Services/RemittanceBatchService.php#L42) | FinancialLedgerWorkflowTest |
| reports | [app/Services/AnalyticsService.php:100](../../../app/Services/AnalyticsService.php#L100) | AnalyticsTest; AnalyticsExportFormattingTest |
| exports | [app/Http/Controllers/AnalyticsController.php:23](../../../app/Http/Controllers/AnalyticsController.php#L23) | AnalyticsTest; AnalyticsExportFormattingTest |
| sellerreport | [app/Http/Controllers/Seller/SellerReportController.php:13](../../../app/Http/Controllers/Seller/SellerReportController.php#L13) | LogisticsWorkflowTest; see refund/report consistency concern |
| hub | [app/Http/Controllers/Logistics/LogisticsController.php:196](../../../app/Http/Controllers/Logistics/LogisticsController.php#L196) | LogisticsWorkflowTest; DashboardNavigationTest |
| dispatch | [app/Http/Controllers/Logistics/LogisticsController.php:897](../../../app/Http/Controllers/Logistics/LogisticsController.php#L897) | LogisticsWorkflowTest; ParcelWeightLoadTest |
| ridercreate | [app/Http/Controllers/Logistics/LogisticsController.php:53](../../../app/Http/Controllers/Logistics/LogisticsController.php#L53) | RiderActivationTest; RegistrationReviewTest |
| rideraccept | [app/Http/Controllers/Courier/AssignmentAcceptanceController.php:13](../../../app/Http/Controllers/Courier/AssignmentAcceptanceController.php#L13) | RoadmapFeaturesTest |
| pickup | [app/Http/Controllers/Courier/CourierController.php:98](../../../app/Http/Controllers/Courier/CourierController.php#L98) | LogisticsWorkflowTest; HubTransferFlowTest |
| delivery | [app/Http/Controllers/Courier/CourierController.php:390](../../../app/Http/Controllers/Courier/CourierController.php#L390) | LogisticsWorkflowTest; FinancialLedgerWorkflowTest |
| fail | [app/Http/Controllers/Courier/CourierController.php:473](../../../app/Http/Controllers/Courier/CourierController.php#L473) | LogisticsWorkflowTest |
| code | [app/Services/DeliveryCodeService.php:36](../../../app/Services/DeliveryCodeService.php#L36) | LogisticsWorkflowTest; audit code-lock probe |
| exception | [app/Services/LogisticsExceptionService.php:15](../../../app/Services/LogisticsExceptionService.php#L15) | LogisticsWorkflowTest; ModuleRevisionTest |
| scope | [app/Services/HubOrderScope.php:19](../../../app/Services/HubOrderScope.php#L19) | LogisticsWorkflowTest; HubTransferFlowTest |
| tracking | [app/Http/Controllers/Logistics/LogisticsController.php:1537](../../../app/Http/Controllers/Logistics/LogisticsController.php#L1537) | LogisticsWorkflowTest |
| audit | [app/Services/AdministrativeAuditService.php:11](../../../app/Services/AdministrativeAuditService.php#L11) | RoadmapFeaturesTest; WorkflowRevisionTest |
| moderation | [app/Http/Controllers/Admin/AdminModerationController.php:19](../../../app/Http/Controllers/Admin/AdminModerationController.php#L19) | WorkflowRevisionTest; LogisticsWorkflowTest |
| approval | [app/Http/Controllers/Admin/RegistrationReviewController.php:12](../../../app/Http/Controllers/Admin/RegistrationReviewController.php#L12) | RegistrationReviewTest |
| schedule | [routes/console.php:12](../../../routes/console.php#L12) | RoadmapFeaturesTest; inspect absence of buyer auto-completion |
| session | [config/session.php:35](../../../config/session.php#L35) | runtime-settings.json; WebModuleSessionTest; TabSessionIsolationTest |
| inventory | [routes/web.php:62](../../../routes/web.php#L62) | Route/controller/model inventory: no matching route or persistence found for missing feature |
