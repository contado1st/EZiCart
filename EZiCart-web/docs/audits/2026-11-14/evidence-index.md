# Current source and regression evidence

Audited **14 November 2026**, commit `a841440`. Laravel web only. **Implementation alignment, not execution of teacher Pass/Fail tests.** Full PHP/frontend regressions and eight isolated characterization probes passed. Historical findings were refreshed for the new implementations; unchanged rows retain their prior assessment after source delta/regression review. Exact teacher fixtures, browser/device acceptance, live email/payment delivery and MySQL races were not replayed.

Named regression classes under tests/ were part of the fresh full run; they support their tested contracts, not literal teacher-case execution. The eight new TeacherReauditProbeTest characterizations deliberately confirm differences. Historical 13 November probes were not rerun unchanged, because fixed behavior would invalidate their old expectations. Other historical evidence labels below describe source checks rather than executed tests.

| Key | Current source anchor | Related regression / probe |
| --- | --- | --- |
| auth | [app/Http/Controllers/Auth/AuthController.php:204](../../../app/Http/Controllers/Auth/AuthController.php#L204) | LogisticsWorkflowTest; RegistrationFlowTest; WebModuleSessionTest |
| roles | [routes/web.php:111](../../../routes/web.php#L111) | WorkflowRevisionTest; LogisticsWorkflowTest; RegistrationReviewTest |
| profile | [app/Http/Controllers/AccountController.php:39](../../../app/Http/Controllers/AccountController.php#L39) | ProfileChangeApprovalTest; LogisticsWorkflowTest; audit probes |
| appearance | [app/Http/Controllers/ProfileAppearanceController.php:13](../../../app/Http/Controllers/ProfileAppearanceController.php#L13) | ProfileShopTest; ProfileChangeApprovalTest |
| staff | [app/Services/StaffProfileService.php:35](../../../app/Services/StaffProfileService.php#L35) | WebOperationsTest; ProfileChangeApprovalTest |
| address | [app/Services/BuyerAddressService.php:36](../../../app/Services/BuyerAddressService.php#L36) | BuyerAddressTest; audit probes |
| category | [app/Services/ProductCategoryService.php:33](../../../app/Services/ProductCategoryService.php#L33) | WebOperationsTest; audit product probe |
| product | [app/Http/Controllers/Seller/ProductController.php:38](../../../app/Http/Controllers/Seller/ProductController.php#L38) | ProductComplianceTest; ProductMediaTest; audit probes |
| delete | [app/Http/Controllers/Seller/ProductController.php:326](../../../app/Http/Controllers/Seller/ProductController.php#L326) | ProductComplianceTest |
| media | [app/Services/ProductMediaService.php:18](../../../app/Services/ProductMediaService.php#L18) | ProductMediaTest |
| variation | [app/Services/ProductVariationEditorService.php:13](../../../app/Services/ProductVariationEditorService.php#L13) | WebOperationsTest |
| search | [app/Http/Controllers/SearchController.php:18](../../../app/Http/Controllers/SearchController.php#L18) | SearchPageTest |
| pdp | [app/Http/Controllers/HomeController.php:52](../../../app/Http/Controllers/HomeController.php#L52) | ProductDetailPageTest; WebOperationsTest |
| follow | [app/Services/ShopFollowService.php:10](../../../app/Services/ShopFollowService.php#L10) | ProfileShopTest |
| cart | [app/Services/BuyerCartService.php:15](../../../app/Services/BuyerCartService.php#L15) | BuyerCartRevisionTest; CheckoutIntegrityTest |
| checkout | [app/Services/PlacePurchaseService.php:24](../../../app/Services/PlacePurchaseService.php#L24) | CheckoutIntegrityTest; BuyerCartRevisionTest; FinancialLedgerWorkflowTest |
| checkoutform | [app/Http/Controllers/Buyer/CheckoutController.php:106](../../../app/Http/Controllers/Buyer/CheckoutController.php#L106) | CheckoutIntegrityTest; BuyerCartRevisionTest |
| voucher | [app/Http/Controllers/Seller/SellerVoucherController.php:31](../../../app/Http/Controllers/Seller/SellerVoucherController.php#L31) | RoadmapFeaturesTest; FinancialLedgerWorkflowTest; audit probes |
| claim | [app/Services/VoucherClaimService.php:12](../../../app/Services/VoucherClaimService.php#L12) | WebOperationsTest; RoadmapFeaturesTest |
| budget | [app/Services/VoucherFundingService.php:18](../../../app/Services/VoucherFundingService.php#L18) | FinancialLedgerWorkflowTest |
| discount | [app/Models/Voucher.php:66](../../../app/Models/Voucher.php#L66) | CheckoutIntegrityTest; FinancialLedgerWorkflowTest |
| pricing | [app/Services/CheckoutPricingService.php:19](../../../app/Services/CheckoutPricingService.php#L19) | CheckoutIntegrityTest; FinancialLedgerWorkflowTest |
| shipping | [app/Services/DeliveryPricingService.php:47](../../../app/Services/DeliveryPricingService.php#L47) | FinancialLedgerWorkflowTest |
| tariff | [app/Services/DeliveryRateManagementService.php:13](../../../app/Services/DeliveryRateManagementService.php#L13) | FinancialLedgerWorkflowTest |
| stock | [app/Services/InventoryRestorationService.php:13](../../../app/Services/InventoryRestorationService.php#L13) | CheckoutIntegrityTest; WorkflowRevisionTest |
| lowstock | [app/Http/Controllers/Seller/SellerController.php:32](../../../app/Http/Controllers/Seller/SellerController.php#L32) | ModuleRevisionTest; StockSubscriptionService is buyer subscriptions |
| shop | [app/Services/ShopAvailabilityService.php:12](../../../app/Services/ShopAvailabilityService.php#L12) | WebOperationsTest |
| promotion | [app/Services/SellerPromotionService.php:12](../../../app/Services/SellerPromotionService.php#L12) | WebOperationsTest |
| sellerorder | [app/Http/Controllers/Seller/SellerOrderController.php:29](../../../app/Http/Controllers/Seller/SellerOrderController.php#L29) | LogisticsWorkflowTest |
| transition | [app/Services/OrderTransitionService.php:25](../../../app/Services/OrderTransitionService.php#L25) | LogisticsWorkflowTest; HubTransferFlowTest |
| cancel | [app/Services/OrderTransitionService.php:261](../../../app/Services/OrderTransitionService.php#L261) | RoadmapFeaturesTest; audit probes |
| cancelcutoff | [app/Services/OrderTransitionService.php:621](../../../app/Services/OrderTransitionService.php#L621) | LogisticsWorkflowTest; audit probes |
| buyerorder | [app/Http/Controllers/Buyer/BuyerController.php:19](../../../app/Http/Controllers/Buyer/BuyerController.php#L19) | BuyerCartRevisionTest; LogisticsWorkflowTest |
| dispute | [app/Http/Controllers/Buyer/DisputeController.php:31](../../../app/Http/Controllers/Buyer/DisputeController.php#L31) | WorkflowRevisionTest; audit probes |
| return | [app/Services/ReturnPickupService.php:15](../../../app/Services/ReturnPickupService.php#L15) | ModuleRevisionTest; WorkflowRevisionTest |
| inspection | [app/Http/Controllers/Seller/ReturnInspectionController.php:13](../../../app/Http/Controllers/Seller/ReturnInspectionController.php#L13) | WorkflowRevisionTest |
| review | [app/Http/Controllers/Buyer/ReviewController.php:15](../../../app/Http/Controllers/Buyer/ReviewController.php#L15) | ProductDetailPageTest; audit probes |
| chat | [app/Http/Controllers/DirectMessageController.php:73](../../../app/Http/Controllers/DirectMessageController.php#L73) | CommunicationCenterTest; OrderMessagingTest; WebOperationsTest |
| notifications | [app/Services/TransactionAwareNotificationSender.php:15](../../../app/Services/TransactionAwareNotificationSender.php#L15) | LogisticsWorkflowTest; CommunicationCenterTest |
| notifychannels | [app/Notifications/OrderStatusNotification.php:24](../../../app/Notifications/OrderStatusNotification.php#L24) | LogisticsWorkflowTest (fake mail transport) |
| finance | [app/Services/OrderFinancialService.php:19](../../../app/Services/OrderFinancialService.php#L19) | WorkflowRevisionTest; FinancialLedgerWorkflowTest |
| payout | [app/Services/OrderFinancialService.php:218](../../../app/Services/OrderFinancialService.php#L218) | WorkflowRevisionTest; FinancialLedgerWorkflowTest |
| withdraw | [app/Services/WalletWithdrawalService.php:14](../../../app/Services/WalletWithdrawalService.php#L14) | FinancialLedgerWorkflowTest; audit probes |
| wallet | [app/Services/WalletFundingService.php:16](../../../app/Services/WalletFundingService.php#L16) | FinancialLedgerWorkflowTest |
| settlement | [app/Services/LogisticsSettlementService.php:18](../../../app/Services/LogisticsSettlementService.php#L18) | FinancialLedgerWorkflowTest |
| batches | [app/Services/RemittanceBatchService.php:42](../../../app/Services/RemittanceBatchService.php#L42) | FinancialLedgerWorkflowTest |
| reports | [app/Services/AnalyticsService.php:100](../../../app/Services/AnalyticsService.php#L100) | AnalyticsTest; AnalyticsExportFormattingTest |
| exports | [app/Http/Controllers/AnalyticsController.php:23](../../../app/Http/Controllers/AnalyticsController.php#L23) | AnalyticsTest; AnalyticsExportFormattingTest |
| sellerreport | [app/Http/Controllers/Seller/SellerReportController.php:11](../../../app/Http/Controllers/Seller/SellerReportController.php#L11) | LogisticsWorkflowTest; see refund/report consistency concern |
| hub | [app/Http/Controllers/Logistics/LogisticsController.php:200](../../../app/Http/Controllers/Logistics/LogisticsController.php#L200) | LogisticsWorkflowTest; DashboardNavigationTest |
| dispatch | [app/Http/Controllers/Logistics/LogisticsController.php:892](../../../app/Http/Controllers/Logistics/LogisticsController.php#L892) | LogisticsWorkflowTest; ParcelWeightLoadTest |
| ridercreate | [app/Http/Controllers/Logistics/LogisticsController.php:57](../../../app/Http/Controllers/Logistics/LogisticsController.php#L57) | RiderActivationTest; RegistrationReviewTest |
| rideraccept | [app/Http/Controllers/Courier/AssignmentAcceptanceController.php:15](../../../app/Http/Controllers/Courier/AssignmentAcceptanceController.php#L15) | RoadmapFeaturesTest |
| pickup | [app/Http/Controllers/Courier/CourierController.php:120](../../../app/Http/Controllers/Courier/CourierController.php#L120) | LogisticsWorkflowTest; HubTransferFlowTest |
| delivery | [app/Http/Controllers/Courier/CourierController.php:412](../../../app/Http/Controllers/Courier/CourierController.php#L412) | LogisticsWorkflowTest; FinancialLedgerWorkflowTest |
| fail | [app/Http/Controllers/Courier/CourierController.php:495](../../../app/Http/Controllers/Courier/CourierController.php#L495) | LogisticsWorkflowTest |
| code | [app/Services/DeliveryCodeService.php:36](../../../app/Services/DeliveryCodeService.php#L36) | LogisticsWorkflowTest; audit code-lock probe |
| exception | [app/Services/LogisticsExceptionService.php:15](../../../app/Services/LogisticsExceptionService.php#L15) | LogisticsWorkflowTest; ModuleRevisionTest |
| scope | [app/Services/HubOrderScope.php:19](../../../app/Services/HubOrderScope.php#L19) | LogisticsWorkflowTest; HubTransferFlowTest |
| tracking | [app/Http/Controllers/Logistics/LogisticsController.php:1555](../../../app/Http/Controllers/Logistics/LogisticsController.php#L1555) | LogisticsWorkflowTest |
| audit | [app/Services/AdministrativeAuditService.php:11](../../../app/Services/AdministrativeAuditService.php#L11) | RoadmapFeaturesTest; WorkflowRevisionTest |
| moderation | [app/Http/Controllers/Admin/AdminModerationController.php:19](../../../app/Http/Controllers/Admin/AdminModerationController.php#L19) | WorkflowRevisionTest; LogisticsWorkflowTest |
| approval | [app/Http/Controllers/Admin/RegistrationReviewController.php:12](../../../app/Http/Controllers/Admin/RegistrationReviewController.php#L12) | RegistrationReviewTest |
| schedule | [routes/console.php:12](../../../routes/console.php#L12) | RoadmapFeaturesTest; inspect absence of buyer auto-completion |
| session | [config/session.php:35](../../../config/session.php#L35) | runtime-settings.json; WebModuleSessionTest; TabSessionIsolationTest |
| inventory | [routes/web.php:73](../../../routes/web.php#L73) | Route/controller/model inventory: no matching route or persistence found for missing feature |
| account14 | [app/Http/Controllers/AccountController.php:39](../../../app/Http/Controllers/AccountController.php#L39) | PartialAccountIntegrationTest; TeacherReauditProbeTest |
| email14 | [app/Services/BuyerEmailChangeService.php:17](../../../app/Services/BuyerEmailChangeService.php#L17) | PartialAccountIntegrationTest; TeacherReauditProbeTest |
| draft14 | [app/Services/ProductDraftService.php:14](../../../app/Services/ProductDraftService.php#L14) | PartialCatalogIntegrationTest; ApplicantDraftWorkspaceTest |
| catalog14 | [app/Http/Controllers/Seller/ProductController.php:22](../../../app/Http/Controllers/Seller/ProductController.php#L22) | PartialCatalogIntegrationTest; TeacherReauditProbeTest |
| sku14 | [app/Services/ProductVariationEditorService.php:13](../../../app/Services/ProductVariationEditorService.php#L13) | PartialCatalogIntegrationTest |
| discover14 | [app/Services/ProductDiscoveryService.php:9](../../../app/Services/ProductDiscoveryService.php#L9) | PartialBuyerDiscoveryTest; PartialCatalogIntegrationTest |
| wish14 | [app/Services/ProductWishlistService.php:9](../../../app/Services/ProductWishlistService.php#L9) | PartialBuyerDiscoveryTest |
| voucher14 | [app/Services/VoucherScheduleRules.php:8](../../../app/Services/VoucherScheduleRules.php#L8) | PartialVoucherIntegrationTest; TeacherReauditProbeTest |
| eligibility14 | [app/Services/VoucherEligibilityService.php:7](../../../app/Services/VoucherEligibilityService.php#L7) | PartialVoucherIntegrationTest |
| sellerorders14 | [app/Http/Controllers/Seller/SellerOrderController.php:29](../../../app/Http/Controllers/Seller/SellerOrderController.php#L29) | PartialSellerReportingTest |
| sales14 | [app/Services/SalesReportService.php:45](../../../app/Services/SalesReportService.php#L45) | PartialSellerReportingTest |
| sellerreviews14 | [app/Http/Controllers/Seller/SellerReviewController.php:11](../../../app/Http/Controllers/Seller/SellerReviewController.php#L11) | PartialReviewIntegrationTest |
| review14 | [app/Services/ReviewService.php:17](../../../app/Services/ReviewService.php#L17) | PartialReviewIntegrationTest; TeacherReauditProbeTest |
| enquiry14 | [app/Http/Controllers/DirectMessageController.php:73](../../../app/Http/Controllers/DirectMessageController.php#L73) | PartialProductEnquiryTest |
| available14 | [app/Services/RiderWorkAvailabilityService.php:18](../../../app/Services/RiderWorkAvailabilityService.php#L18) | RiderWorkAvailabilityTest; TeacherReauditProbeTest |
| courier14 | [app/Http/Controllers/Courier/CourierController.php:40](../../../app/Http/Controllers/Courier/CourierController.php#L40) | CourierWorkPresentationTest; DeliveryRetryGuidanceTest |
| compensation14 | [app/Services/CourierCompensationService.php:15](../../../app/Services/CourierCompensationService.php#L15) | CourierWorkPresentationTest |
| delivery14 | [app/Services/DeliveryProgressService.php:7](../../../app/Services/DeliveryProgressService.php#L7) | DeliveryRetryGuidanceTest; LogisticsQueueTrackingTest |
| groups14 | [app/Services/BuyerOrderGroupingService.php:29](../../../app/Services/BuyerOrderGroupingService.php#L29) | DeferredWalletOrderTest; TeacherReauditProbeTest |
| pay14 | [app/Services/WalletOrderPaymentService.php:12](../../../app/Services/WalletOrderPaymentService.php#L12) | DeferredWalletOrderTest; PrepaidPaymentVerificationTest |
| demo14 | [app/Services/DemoWalletFundingService.php:14](../../../app/Services/DemoWalletFundingService.php#L14) | DemoWalletFundingTest; DeferredWalletOrderTest |
| queue14 | [app/Services/LogisticsQueueService.php:8](../../../app/Services/LogisticsQueueService.php#L8) | LogisticsQueueTrackingTest |
| correction14 | [app/Services/ParcelCorrectionService.php:15](../../../app/Services/ParcelCorrectionService.php#L15) | ParcelCorrectionTest |
| inspection14 | [app/Services/ReturnInspectionService.php:14](../../../app/Services/ReturnInspectionService.php#L14) | ReturnWorkflowRevisionTest |
| return14 | [app/Services/ReturnWorkflowService.php:12](../../../app/Services/ReturnWorkflowService.php#L12) | ReturnWorkflowRevisionTest; LogisticsQueueTrackingTest |
| zone14 | [app/Services/ZoneReadinessService.php:13](../../../app/Services/ZoneReadinessService.php#L13) | ZoneReadinessTest |
| remittance14 | [app/Services/RemittanceReportService.php:11](../../../app/Services/RemittanceReportService.php#L11) | RemittanceReportTest |
