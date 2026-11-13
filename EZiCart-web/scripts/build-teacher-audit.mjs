import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';

const root = process.cwd();
const dir = path.join(root, 'docs/audits/2026-11-13');
const sources = JSON.parse(fs.readFileSync(path.join(dir, 'teacher-sources.json')));
const cases = JSON.parse(fs.readFileSync(path.join(dir, 'cases.json')));
const checklist = JSON.parse(fs.readFileSync(path.join(dir, 'checklist.json')));
const status = {A:'Aligned', P:'Partial', D:'Different', M:'Missing', U:'Unverified', O:'Optional'};
const assessed = new Map();
const evidence = {};
function ev(key, file, needle, test = '') {
    const lines = fs.readFileSync(path.join(root,file),'utf8').split(/\r?\n/);
    const index = lines.findIndex(line => line.includes(needle));
    if (index < 0) throw new Error(`Evidence anchor missing: ${file}: ${needle}`);
    evidence[key] = {file,line:index+1,test};
}
ev('auth','app/Http/Controllers/Auth/AuthController.php','function login','LogisticsWorkflowTest; RegistrationFlowTest; WebModuleSessionTest');
ev('roles','routes/web.php',"Route::middleware('auth')",'WorkflowRevisionTest; LogisticsWorkflowTest; RegistrationReviewTest');
ev('profile','app/Http/Controllers/AccountController.php','function update(', 'ProfileChangeApprovalTest; LogisticsWorkflowTest; audit probes');
ev('appearance','app/Http/Controllers/ProfileAppearanceController.php','function update(', 'ProfileShopTest; ProfileChangeApprovalTest');
ev('staff','app/Services/StaffProfileService.php','function submit(', 'WebOperationsTest; ProfileChangeApprovalTest');
ev('address','app/Services/BuyerAddressService.php','function save(', 'BuyerAddressTest; audit probes');
ev('category','app/Services/ProductCategoryService.php','function validate(', 'WebOperationsTest; audit product probe');
ev('product','app/Http/Controllers/Seller/ProductController.php','function store(', 'ProductComplianceTest; ProductMediaTest; audit probes');
ev('delete','app/Http/Controllers/Seller/ProductController.php','function destroy(', 'ProductComplianceTest');
ev('media','app/Services/ProductMediaService.php','function rules(', 'ProductMediaTest');
ev('variation','app/Services/ProductVariationEditorService.php','function prepare(', 'WebOperationsTest');
ev('search','app/Http/Controllers/SearchController.php','function index(', 'SearchPageTest');
ev('pdp','app/Http/Controllers/HomeController.php','function showProduct(', 'ProductDetailPageTest; WebOperationsTest');
ev('follow','app/Services/ShopFollowService.php','function ', 'ProfileShopTest');
ev('cart','app/Services/BuyerCartService.php','function ', 'BuyerCartRevisionTest; CheckoutIntegrityTest');
ev('checkout','app/Services/PlacePurchaseService.php','function place(', 'CheckoutIntegrityTest; BuyerCartRevisionTest; FinancialLedgerWorkflowTest');
ev('checkoutform','app/Http/Controllers/Buyer/CheckoutController.php','function process(', 'CheckoutIntegrityTest; BuyerCartRevisionTest');
ev('voucher','app/Http/Controllers/Seller/SellerVoucherController.php','function store(', 'RoadmapFeaturesTest; FinancialLedgerWorkflowTest; audit probes');
ev('claim','app/Services/VoucherClaimService.php','function claim(', 'WebOperationsTest; RoadmapFeaturesTest');
ev('budget','app/Services/VoucherFundingService.php','function reserve(', 'FinancialLedgerWorkflowTest');
ev('discount','app/Models/Voucher.php','function calculateDiscount(', 'CheckoutIntegrityTest; FinancialLedgerWorkflowTest');
ev('pricing','app/Services/CheckoutPricingService.php','function quote(', 'CheckoutIntegrityTest; FinancialLedgerWorkflowTest');
ev('shipping','app/Services/DeliveryPricingService.php','function quote(', 'FinancialLedgerWorkflowTest');
ev('tariff','app/Services/DeliveryRateManagementService.php','function create(', 'FinancialLedgerWorkflowTest');
ev('stock','app/Services/InventoryRestorationService.php','function restore(', 'CheckoutIntegrityTest; WorkflowRevisionTest');
ev('lowstock','app/Http/Controllers/Seller/SellerController.php',"'low_stock'",'ModuleRevisionTest; StockSubscriptionService is buyer subscriptions');
ev('shop','app/Services/ShopAvailabilityService.php','function assertOpen(', 'WebOperationsTest');
ev('promotion','app/Services/SellerPromotionService.php','function deliver(', 'WebOperationsTest');
ev('sellerorder','app/Http/Controllers/Seller/SellerOrderController.php','function index(', 'LogisticsWorkflowTest');
ev('transition','app/Services/OrderTransitionService.php','private const TRANSITIONS','LogisticsWorkflowTest; HubTransferFlowTest');
ev('cancel','app/Services/OrderTransitionService.php','function requestCancellation(', 'RoadmapFeaturesTest; audit probes');
ev('cancelcutoff','app/Services/OrderTransitionService.php','function authorizeCancellation(', 'LogisticsWorkflowTest; audit probes');
ev('buyerorder','app/Http/Controllers/Buyer/BuyerController.php','function dashboard(', 'BuyerCartRevisionTest; LogisticsWorkflowTest');
ev('dispute','app/Http/Controllers/Buyer/DisputeController.php','function store(', 'WorkflowRevisionTest; audit probes');
ev('return','app/Services/ReturnPickupService.php','function ', 'ModuleRevisionTest; WorkflowRevisionTest');
ev('inspection','app/Http/Controllers/Seller/ReturnInspectionController.php','function store(', 'WorkflowRevisionTest');
ev('review','app/Http/Controllers/Buyer/ReviewController.php','function store(', 'ProductDetailPageTest; audit probes');
ev('chat','app/Http/Controllers/DirectMessageController.php','function store(', 'CommunicationCenterTest; OrderMessagingTest; WebOperationsTest');
ev('notifications','app/Services/TransactionAwareNotificationSender.php','function send(', 'LogisticsWorkflowTest; CommunicationCenterTest');
ev('notifychannels','app/Notifications/OrderStatusNotification.php','function via(', 'LogisticsWorkflowTest (fake mail transport)');
ev('finance','app/Services/OrderFinancialService.php','function initialize(', 'WorkflowRevisionTest; FinancialLedgerWorkflowTest');
ev('payout','app/Services/OrderFinancialService.php','function requestPayout(', 'WorkflowRevisionTest; FinancialLedgerWorkflowTest');
ev('withdraw','app/Services/WalletWithdrawalService.php','function request(', 'FinancialLedgerWorkflowTest; audit probes');
ev('wallet','app/Services/WalletFundingService.php','function assertSandbox(', 'FinancialLedgerWorkflowTest');
ev('settlement','app/Services/LogisticsSettlementService.php','function settle(', 'FinancialLedgerWorkflowTest');
ev('batches','app/Services/RemittanceBatchService.php','function receive(', 'FinancialLedgerWorkflowTest');
ev('reports','app/Services/AnalyticsService.php','function report(', 'AnalyticsTest; AnalyticsExportFormattingTest');
ev('exports','app/Http/Controllers/AnalyticsController.php','function export(', 'AnalyticsTest; AnalyticsExportFormattingTest');
ev('sellerreport','app/Http/Controllers/Seller/SellerReportController.php','function index(', 'LogisticsWorkflowTest; see refund/report consistency concern');
ev('hub','app/Http/Controllers/Logistics/LogisticsController.php','function dashboard(', 'LogisticsWorkflowTest; DashboardNavigationTest');
ev('dispatch','app/Http/Controllers/Logistics/LogisticsController.php','function assignRider(', 'LogisticsWorkflowTest; ParcelWeightLoadTest');
ev('ridercreate','app/Http/Controllers/Logistics/LogisticsController.php','function storeRider(', 'RiderActivationTest; RegistrationReviewTest');
ev('rideraccept','app/Http/Controllers/Courier/AssignmentAcceptanceController.php','function __invoke(', 'RoadmapFeaturesTest');
ev('pickup','app/Http/Controllers/Courier/CourierController.php','function claimPickup(', 'LogisticsWorkflowTest; HubTransferFlowTest');
ev('delivery','app/Http/Controllers/Courier/CourierController.php','function completeDelivery(', 'LogisticsWorkflowTest; FinancialLedgerWorkflowTest');
ev('fail','app/Http/Controllers/Courier/CourierController.php','function failDelivery(', 'LogisticsWorkflowTest');
ev('code','app/Services/DeliveryCodeService.php','function isUsable(', 'LogisticsWorkflowTest; audit code-lock probe');
ev('exception','app/Services/LogisticsExceptionService.php','function openForRiderSuspension(', 'LogisticsWorkflowTest; ModuleRevisionTest');
ev('scope','app/Services/HubOrderScope.php','function query(', 'LogisticsWorkflowTest; HubTransferFlowTest');
ev('tracking','app/Http/Controllers/Logistics/LogisticsController.php','function tracking(', 'LogisticsWorkflowTest');
ev('audit','app/Services/AdministrativeAuditService.php','function record(', 'RoadmapFeaturesTest; WorkflowRevisionTest');
ev('moderation','app/Http/Controllers/Admin/AdminModerationController.php','function index(', 'WorkflowRevisionTest; LogisticsWorkflowTest');
ev('approval','app/Http/Controllers/Admin/RegistrationReviewController.php','function show(', 'RegistrationReviewTest');
ev('schedule','routes/console.php','Schedule::','RoadmapFeaturesTest; inspect absence of buyer auto-completion');
ev('session','config/session.php',"'lifetime'",'runtime-settings.json; WebModuleSessionTest; TabSessionIsolationTest');
ev('inventory','routes/web.php','// 1. Public','Route/controller/model inventory: no matching route or persistence found for missing feature');

function put(prefix, rows, defaultEvidence) {
    for (const [number, item] of Object.entries(rows)) {
        const id = `${prefix}-${number.padStart(3,'0')}`;
        if (assessed.has(id)) throw new Error(`Duplicate ${id}`);
        assessed.set(id,{status:status[item[0]],actual:item[1],evidence:(item[2]||defaultEvidence).split(',')});
    }
}
function missing(prefix,count,note,ref='inventory'){put(prefix,Object.fromEntries(Array.from({length:count},(_,i)=>[i+1,['M',note]])),ref);}

put('SEL-PROF',{
1:['D','Official seller name/contact/address and appearance remain unchanged until marketplace-admin review; bio/display name differ from business name.','staff,appearance'],
2:['P','Some required fields are checked, but description/address are not all required as in the source; limits differ.'],
3:['A','TrimStrings/required rejects whitespace-only required business name.'],
4:['D','No unique business-name rule in profile revision; only contact/plate/license identity uniqueness.'],
5:['D','Business name is 2–255 characters on reviewed updates, not 3–30.'],
6:['D','Profile bio permits 1000 characters, not a 500-character store description.','appearance'],
7:['A','Reviewed seller contact requires 09 or +639 format and canonical duplicate checks.','staff'],
8:['D','Avatar/banner accept JPG/PNG/WebP up to 2 MB with dimension checks, not JPG/PNG up to 5 MB.','appearance'],
9:['A','Vacation keeps the catalog visible and blocks new cart/purchase operations.','shop'],
10:['A','Opening the shop clears closure dates and restores purchase eligibility.','shop'],
11:['P','Unapproved sellers are denied operational routes; a save-only draft workflow is absent.','roles,product'],
12:['A','Blade escapes profile/store text; no raw-HTML rendering found in these fields.','appearance'],
13:['A','Profile updates target the authenticated account; reviewed changes are owner/admin scoped.','profile,staff']},'profile');
missing('SEL-SCAT',13,'Seller-owned category CRUD, hierarchy, ordering, hiding, multi-category membership and Uncategorized migration are absent; only a shared product category string exists.','category');
put('SEL-PROD',{
1:['D','Creates pending_review for admin approval, not immediately Active; no separate store-category, brand or condition fields.'],
2:['D','Description and images are optional; upload retention and the teacher required-field set are not met.'],
3:['D','Name max255 with no min5; description nullable with no 20–3000 limit. Audit probe accepts one-character name/no description.'],
4:['D','Price min0.01, no 1.00 minimum/1m maximum/decimal precision constraint. Probe accepted 0.99.'],
5:['D','Stock integer/min0, no 99999 maximum; probe accepted 100000.'],
6:['D','Cover plus eight gallery images is supported, but zero images is accepted.','product,media'],
7:['D','Images use a 2 MB limit and include WebP; teacher accepts 5 MB JPG/PNG.','media'],
8:['P','Positive weight required; dimensions optional positive mm values. Actual-only whole-kg test tariff is not the active model.','product,shipping'],
9:['M','No Save as Draft action permitting name-only product creation.'],
10:['P','Normal creation requires fields and admin review, but there is no draft-to-publish workflow.'],
11:['P','Archiving/compliance moderation hides products; no equivalent seller deactivate/reactivate lifecycle.','delete'],
12:['M','No duplicate-to-draft product action.'],
13:['A','Cart and checkout re-read current product/variant price and reject stale reviewed totals.','cart,checkout'],
14:['D','Deletion does not explicitly reject unfinished orders; it archives when compliance history exists.','delete'],
15:['P','Order name/price snapshots persist, but historical image retention and unconditional soft delete are not guaranteed.','delete'],
16:['M','No bulk deactivate/delete operation with per-product skipped results.'],
17:['D','Two identical publish POSTs create two rows in the audit probe; no create idempotency key.'],
18:['A','Owner checks reject editing another seller product.']},'product');
put('SEL-VAR',{
1:['D','Rows use one type/value pair with price adjustment, SKU, stock and image. No two-axis Cartesian-product generation.'],
2:['D','No distinct two-type ceiling; request supports up to 100 variation rows.'],
3:['D','No 20-options-per-type constraint.'],
4:['A','Required type/value and normalized duplicate pair checks reject blank/duplicate row values.'],
5:['D','Uses base price plus adjustment and broader price/stock ceilings, not A17 per-combination rules.'],
6:['P','SKUs are unique within one product, not proven unique across the entire store.'],
7:['P','Selected variant changes price; search/PDP base price does not implement the specified min–max price range.','pdp,variation'],
8:['A','Selected row image switches the product media.','pdp,variation'],
9:['A','Unavailable/out-of-stock rows are disabled and validated on the server.','cart,variation'],
10:['A','OrderItem captures variant text and paid unit price; editing catalog values does not rewrite it.','checkout'],
11:['A','Removed rows are retired; saved cart lines remain marked unavailable.','cart,variation'],
12:['D','Rows are retired rather than deletion being blocked for unfinished orders.']},'variation');
put('SEL-INV',{
1:['A','Atomic order placement decrements product/variant stock.','checkout'],
2:['D','No pending unpaid e-wallet order; wallet debits atomically or the entire order rolls back.','wallet,checkout'],
3:['P','Approved cancellation restores stock once; a buyer request alone leaves stock reserved.','cancel,stock'],
4:['M','No one-hour unpaid gateway-order expiration workflow.','schedule'],
5:['A','Only the ordered variation quantity is decremented.','checkout'],
6:['A','Locked/conditional inventory deductions and checkout concurrency tests prevent overselling.','checkout'],
7:['A','Zero stock is unavailable to cart/checkout and displayed as sold out.','cart,pdp'],
8:['D','Seller dashboard uses stock <10; buyer subscription alerts use state changes around5. No specified per-variant seller alert once per crossing.','lowstock'],
9:['D','Manual product stock update lacks a 99999 ceiling.','product'],
10:['A','Cart retains a shortage notice and checkout revalidates available stock.','cart,checkout']},'checkout');
missing('SEL-DISC',10,'No scheduled product-price sale engine, start/end price switching, overlap validation or sale-price checkout. SellerPromotion sends advertising notifications; it does not change unit prices.','promotion,pricing');
put('SEL-VOU',{
1:['P','Claimable fixed offers exist, but creation additionally requires a funded maximum-liability budget.'],
2:['A','Percentage cap is applied and seller campaigns are budget-backed.','discount,budget'],
3:['M','No code-only/hidden visibility property; active store vouchers are listed publicly.'],
4:['D','Uppercases codes but permits up to50 characters without 4–12 alphanumeric restriction; probe accepts spaces/punctuation.'],
5:['A','Codes are uppercase-normalized and globally unique on seller creation.'],
6:['D','No discount<=minimum-spend constraint; discounts are capped at eligible amounts. Audit probe creates value10/minimum0.'],
7:['P','Amount/usage/expiry are checked, but start date and the full teacher field set are absent.'],
8:['M','Vouchers target a seller and merchandise/shipping; no selected-product whitelist.'],
9:['M','No voucher edit endpoint for extending claimed-voucher expiry/usage limits.'],
10:['P','Pause blocks future use/releases unused budget and retains old orders, but claimed-wallet entries are retained rather than deleted.','voucher,claim,budget'],
11:['A','Order use increments usage once and approved cancellation restores it for funded offers.','budget,checkout']},'voucher');
missing('SEL-FLASH',8,'No flash-sale slot/stock engine, flash-versus-store-price split, quantity bundle discounts or Buy X Get Y pricing. Advertising notifications are a separate feature.','promotion,pricing');
put('SEL-ORD',{
1:['P','Status tabs/counts and pagination exist; seller order list lacks the requested search/date filters.'],
2:['A','Confirmed/Preparing/Ready stages, weight recording and printable parcel labels implement this flow with scanned custody.','sellerorder,transition'],
3:['A','Central transition map blocks skipped seller stages.','transition'],
4:['A','Seller cancellation before custody needs a reason and restores inventory/funded campaign usage.','cancelcutoff,stock'],
5:['P','Sandbox wallet cancellation refunds; unsupported live gateway tenders are not implemented.','wallet,finance'],
6:['A','Physical handover/pickup prevents cancellation.','cancelcutoff'],
7:['A','Order item, price, address, note and payment snapshots are seller scoped; literal fixture/rate amounts require equivalent setup.','checkout,sellerorder'],
8:['A','Another seller cannot open the order.']},'sellerorder');
put('SEL-RET',{
1:['D','Buyer opens an admin-led dispute with one optional file; no seller response deadline or multi-photo return inbox.'],
2:['D','Seller does not approve the refund request; marketplace admin decides, followed by scanned return and inspection.','return,finance'],
3:['M','No seller reject-return request action with 10–300 reason validation.'],
4:['M','No seller-first rejection/escalation workflow.'],
5:['M','No one-time buyer escalation from seller rejection.'],
6:['M','No seller three-day response/auto-approval timer.','schedule'],
7:['P','Ledger payout/refund eligibility and analytics account for executed refunds; legacy seller report sums completed orders without refund exclusion.','finance,reports,sellerreport']},'dispute');
put('SEL-REV',{
1:['P','Buyer product page filters ratings/comments; seller dashboard has aggregates but no dedicated seller review management/filter table.','pdp,review'],
2:['M','No seller review-reply endpoint or persistence.'],
3:['M','No seller reply/edit-once model.'],
4:['A','No seller review delete/hide endpoint; public reviews remain buyer-authored.'],
5:['A','Related buyer/seller direct conversations support private attachments, ordering and timestamps.','chat'],
6:['A','The specific 5.1MB image, EXE and renamed-EXE inputs are rejected by size/content/extension validation. Additional supported document types differ under A16.','chat'],
7:['D','Text limit is2000; attachment-only messages are allowed, not the 1–1000 text contract.','chat']},'review');
put('SEL-EARN',{
1:['D','Default/runtime commission10%, not5%; only one voucher/no mixed-shop checkout; prefunded seller discounts are not charged twice.','pricing,finance'],
2:['D','Availability requires completion plus seven-day delivery hold, verified funding/no disputes and approved settlement.','payout'],
3:['P','Analytics/ledger exclude or reverse executed refunds; legacy seller reports can include a refunded COMPLETED order.','reports,finance,sellerreport'],
4:['A','Seller reports validate ordered date ranges and include boundary dates.','sellerreport'],
5:['D','Order payout requests use each eligible order amount. Wallet withdrawals permit less thanPHP100; audit probe accepted99.99.','payout,withdraw'],
6:['D','No single global pending-withdrawal rule; probe creates two pending requests.','withdraw'],
7:['P','An order-level payout is unique per order and wallet request key is idempotent; it is not the teacher balance-payout screen.','payout,withdraw']},'payout');

put('BUY-ACCT',{
1:['P','Profile and appearance update independently; exact field/media limits differ from A16.','profile,appearance'],
2:['D','Buyer update permits one-character names and invalid mobile text; photo limit2MB, not5MB. Audit probe confirms contact validation gap.','profile,appearance'],
3:['A','Current-password verified change stores a hash and rejects the old login.','profile'],
4:['A','current_password validation blocks the wrong old password.','profile'],
5:['P','New password requires8–128, mixed case, number, symbol and confirmation, but no rule forbids reuse of the current password.','profile'],
6:['M','No staged email-change/verify-new-email workflow.','profile'],
7:['M','No email-change OTP state/expiry workflow.','profile'],
8:['P','Registration canonical duplicate protection exists, but account email-change function itself is absent.','profile'],
9:['D','Buyer contact update is immediate, with no OTP/mobile-format/canonical duplicate validation; probe confirms invalid text saves.','profile']},'profile');
put('BUY-ADDR',{
1:['A','Own address creation preserves an existing default.'],
2:['A','PSGC cascade clears dependent selections; service resolves hierarchy on the server.'],
3:['P','Recipient/location rules exist, but street/house/postal details are not all required as in the source.'],
4:['A','Address contact regex accepts only09 and+639 mobile formats.'],
5:['D','No ten-address ceiling; probe saved11.'],
6:['A','Locked default switch and checkout own-address selection maintain one default.'],
7:['D','Default deletion is permitted and automatically chooses the oldest replacement; teacher requires explicit replacement first.'],
8:['A','Order address is copied at placement, separate from saved-address edits/deletes.','checkout'],
9:['A','Queries use authenticated buyer relationship and reject other buyers records.']},'address');
put('BUY-SRCH',{
1:['A','Shared product categories are filtered, including legacy aliases.','category,search'],
2:['M','No seller-owned hierarchical store categories.','category'],
3:['A','Trimmed case-insensitive literal name/description/store search exists.'],
4:['P','No fuzzy spelling suggestion/typo matching; empty-state handling exists.'],
5:['A','Bound SQL parameters, escaped LIKE wildcards and Blade escaping support literal search.'],
6:['A','Blank search displays a normal listing.'],
7:['A','Price limits are inclusive; negative/reversed ranges are validated.'],
8:['A','Category, price, location and rating filters combine on the same query.'],
9:['P','Price/latest sorts and stable pagination exist, but the requested best-selling sort is not accepted; rating is offered instead.'],
10:['A','Marketplace scope excludes archived/unapproved/inactive-seller products.'],
11:['A','Vacation products remain visible and purchasing is blocked.','shop']},'search');
put('BUY-PDP',{
1:['D','A single combined variation row is selected, not two mandatory option-axis selectors.','variation'],
2:['A','Selected variation supplies its price adjustment, stock and image.','variation,pdp'],
3:['A','Zero-stock variation selection is disabled and rejected server-side.','variation,cart'],
4:['A','Buyer cart quantities are capped at20 and actual stock, with integer/min validation.','cart'],
5:['M','No lifetime per-buyer product purchase cap.','product,checkout'],
6:['M','No dynamic sale/flash countdown, allocated flash quantity or sale-price promotion.','promotion'],
7:['A','Buy Now uses an isolated checkout context without adding/copying the saved cart.','cart,checkout'],
8:['A','Unavailable product scope aborts/not-found without purchase actions.','pdp']},'pdp');
missing('BUY-WISH',3,'No product wishlist/favorite storage or toggle route; following shops and stock alerts are separate capabilities.');
put('BUY-WISH',{
4:['A','Shop follow/unfollow uses a unique relationship and reflected count.','follow'],
5:['P','Following is authenticated; wishlist action is absent.','follow']},'follow');
put('BUY-CART',{
1:['A','Product/variation key merges quantity into one saved line.'],
2:['A','Different variation IDs have separate keys/prices.'],
3:['A','Cart view groups lines by seller.'],
4:['D','Selecting a new shop clears the other shop selection, so intermediate mixed-shop subtotals differ. Final P-01+P-02 from one shop can check out.','checkout'],
5:['A','Quantity1–20 and actual stock are validated on updates.'],
6:['A','Saved cart retains IDs/quantity; displayed prices are refreshed from catalog.'],
7:['A','Unavailable lines remain visible/disabled while valid selected lines can check out.'],
8:['A','Buyer-scoped persisted cart survives logout and loads on a later authenticated session/device.'],
9:['A','Removal persists through buyer cart mutation.'],
10:['A','Cart mutations always use the authenticated buyer cart; arbitrary buyer IDs are not accepted.']},'cart');
put('BUY-VOU',{
1:['A','Unique buyer claims retain voucher rules/usage limits.','claim'],
2:['A','Single seller voucher is scoped and amount is capped/reconciled in checkout.','discount,pricing'],
3:['D','Only one voucher ID is applied; store+platform+shipping stacking and mixed shops are not supported.','checkout,pricing'],
4:['P','Minimum boundary is checked but a dedicated Spend0.01-more message is not implemented.','discount'],
5:['P','Inactive/expired/exhausted codes are blocked, but no starts_at and messages can combine causes.','discount'],
6:['A','Per-buyer redemption counters and claim limits are enforced.','claim'],
7:['A','Invalid voucher is removed and checkout requires review rather than retaining discount.','checkoutform'],
8:['A','Percent vouchers respect max_discount at eligible totals.','discount'],
9:['A','Shipping-target voucher is capped at shipping, and merchandise is unchanged.','pricing'],
10:['A','Discount never exceeds eligible subtotal; shipping remains separately funded.','discount,pricing'],
11:['M','No product sale engine for the assumed already-discounted price.','promotion'],
12:['P','Funded voucher usage is restored on approved cancellation; buyer request alone is not final cancellation.','budget,cancel']},'discount');
put('BUY-CHK',{
1:['D','One-shop checkout rejects the teacher multi-store worked example; no voucher stacking.','checkout,pricing'],
2:['D','Active runtime isPHP50 flat, no approved tariffs; configurable versioned actual/volumetric rules differ from A20 table.','shipping'],
3:['D','Legacy flat fee ignores1kg boundary; approved bracket/volumetric model differs from rounding actual weight to whole kg.','shipping'],
4:['D','No configured destination area can become null at web checkout; rejection is deferred to operational sorting/coverage.','checkoutform'],
5:['A','Checkout note max200 and Order copies notes.','checkoutform'],
6:['A','Payment method is required and limited to COD/WALLET.','checkoutform'],
7:['A','Buyer/token lock and unique purchase key replay one order/stock effect.','checkout'],
8:['A','Reviewed quote hash mismatch blocks stale price until buyer reviews again.','checkout'],
9:['A','Atomic stock recheck prevents ordering sold-out quantity.','checkout'],
10:['A','Voucher validity and budget are rechecked under locks.','checkout,budget'],
11:['A','Server derives prices/total instead of trusting submitted amounts.','checkout']},'checkout');
put('BUY-PAY',{
1:['A','COD creates a placed order awaiting seller fulfillment and later exact cash collection.','checkout'],
2:['P','Full wallet accounting/reference works only in explicitly enabled sandbox; no live card/e-wallet gateway.'],
3:['D','Failed funding/insufficient wallet rolls back purchase; no persisted To Pay order or Pay Again gateway UI.'],
4:['M','No hosted gateway cancellation/To Pay return flow.'],
5:['M','No asynchronous live gateway payment workflow.'],
6:['M','No one-hour unpaid-order state/timer; wallet funding intent expiry is not order expiry.','schedule'],
7:['M','No59/61-minute unpaid order payment boundary.','schedule']},'wallet');
put('BUY-TRK',{
1:['P','All/To Ship/To Receive/Completed plus status filtering exist, but no To Pay workflow.','buyerorder'],
2:['A','Tracking events preserve stage times and show seller/logistics/rider updates in order.','transition'],
3:['D','Buyer receipt completes the order, but seller available money waits for funded, held and approved payout.','payout'],
4:['A','Transition service permits buyer completion only from Delivered.','transition'],
5:['M','No scheduled seven-day automatic order completion or automatic release.','schedule'],
6:['A','Buyer order retrieval checks ownership.','buyerorder']},'buyerorder');
put('BUY-CAN',{
1:['D','Buyer submits a pending request, seller decides or24h auto-approval; no immediate cancellation/stock restoration.','cancel'],
2:['D','Buyer reason is optional and receives a fallback reason; probe confirms.','cancel'],
3:['P','Approved sandbox wallet cancellation refunds/restores use; live gateway route absent.','cancel,wallet'],
4:['A','Buyer cancellation is rejected at Ready for Pickup; probe confirms actual cutoff.','cancelcutoff'],
5:['A','Pickup/handover/custody prevents cancellation.','cancelcutoff'],
6:['A','Pending request uniqueness and inventory_restored_at prevent duplicate restoration.','cancel,stock']},'cancel');
put('BUY-RET',{
1:['D','Admin-led dispute rather than seller return approval; no teacher seven-day/three-day deadline flow.'],
2:['A','Dispute creation rejects orders before delivered/completed.'],
3:['D','Completed orders remain disputable with no seven-day limit;20-day probe succeeds.'],
4:['D','Description20–2000, one optional JPG/PNG/PDF <=5MB, not10–500 plus1–5 mandatory photos.'],
5:['M','No seller rejection followed by Escalate; marketplace admin handles the case.']},'dispute');
put('BUY-RATE',{
1:['P','Product star/text review exists, but no photo/variant media review fields.'],
2:['A','Only completed orders may submit review.'],
3:['P','Second order/product review blocked; no Edit workflow.'],
4:['D','Comment max1000 and no review photos;501 accepted by audit probe.'],
5:['M','No once-only edit or30-day window; new review accepted after40 days in probe.'],
6:['A','Product averages/counts and rating query derive from stored reviews.','pdp,search']},'review');
put('BUY-CHAT',{
1:['P','Related seller conversation with attachments/order context works; automatic PDP product-card message context not found.','chat'],
2:['D','2000-character messages and PDF/Office/text/WebP attachments differ from1000-character JPG/PNG-only rule.','chat'],
3:['A','Order transition notifications are scoped and transaction-aware; page/count tests cover visibility.','notifications'],
4:['M','No flash-sale reminder subscription/scheduled slot engine.','promotion'],
5:['A','Single/all mark-read actions update unread counters.','notifications']},'chat');

put('RID-PROF',{
1:['P','Profile/vehicle/private documents exist; teacher seed names/licence/company are fixtures not installed or claimed as tested.','staff,approval'],
2:['D','Vehicle/contact changes require logistics-admin approval, keep old values visible; no OR/CR file replacement on this profile form.','staff'],
3:['A','Canonical plate uniqueness is checked on revision and approval.','staff'],
4:['D','license_expires_at uses after:today, so today is rejected rather than accepted.','profile'],
5:['D','Profile revisions do not accept replacement licence/ORCR upload; registration allows JPG/PNG/PDF5MB.','ridercreate'],
6:['P','Suspended riders cannot receive/perform assignments; separate Online toggle absent.','dispatch'],
7:['A','Staff/profile data is restricted to authorized self/admin/involved participants.','staff']},'staff');
missing('RID-AVAIL',4,'No rider-controlled Online/Offline availability toggle or offer-dispatch eligibility based on it. Recent web activity is informational presence, not work availability.','dispatch');
put('RID-JOB',{
1:['D','Manual area/capacity-based assignment and accept exist; no teacher fixedPHP30 quote/nearest-online offer mechanism.','dispatch,settlement'],
2:['D','Decline returns to hub dispatch for manual reassignment, not automatic next-nearest rider.','transition'],
3:['D','Acceptance deadline15minutes, not60seconds; expiry returns parcel to manual dispatch.','rideraccept'],
4:['M','No three-decline automatic offer cascade.','dispatch'],
5:['A','Order/assignment locks and ownership checks preserve a single assignee.','dispatch,transition'],
6:['D','Configured maximum is10 active deliveries plus verified weight capacity, not3 jobs.','dispatch'],
7:['A','Cross-hub/company ownership checks reject unrelated assignment acceptance.','dispatch,rideraccept']},'dispatch');
put('RID-PICK',{
1:['A','Seller pickup/contact and order/parcel details are scoped to assigned rider.','pickup'],
2:['P','Scan custody and synchronization work; pickup earnings are configured and released at settlement, not immediatelyPHP30.','pickup,settlement'],
3:['P','Manual parcel-code entry uses the scan path; earnings differ from the fixed immediate teacher rule.','pickup,settlement'],
4:['A','Wrong parcel code or another order reference is rejected.','pickup'],
5:['A','Single physical transition/ledger keys prevent double pickup or earnings; text may differ.','pickup,settlement']},'pickup');
put('RID-DLV',{
1:['P','Sequential delivery/proof/COD work; driver earnings post at eligible settlement with configurable rates, not immediatePHP50.','delivery,settlement'],
2:['A','Transition guards reject direct delivery before Out for Delivery.','transition'],
3:['A','Both specific attempts (missing proof and5.1MB proof) are rejected. Other accepted types/4MB limit differ under A16/A32.','delivery'],
4:['D','Five failures lock the code until buyer refresh; no15-minute automatic unlock. Probe confirms.','code'],
5:['A','COD must match order amount exactly in centavos.','delivery'],
6:['P','Verified sandbox WALLET shows prepaid/no collection; other unconfigured non-COD tenders remain on hold.','delivery,wallet'],
7:['A','Delivery mutation checks assigned rider/approved status.','delivery']},'delivery');
put('RID-FAIL',{
1:['A','Failure reasons/attempt counters and role notifications are recorded; no earned delivery credit for a failed attempt.','fail,settlement'],
2:['A','failure_reason is required and enumerated.','fail'],
3:['D','recipient_refused records Delivery Failed; no immediate automatic RTS trigger.','fail'],
4:['P','Retry is supported after physical hub return/intake, not a direct rider reschedule action.','transition'],
5:['D','Three-attempt cap exists, but RTS requires scanned hub recovery/reassignment rather than automatic status flip.','dispatch,transition']},'fail');
put('RID-EARN',{
1:['P','Delivery history and settled earning allocations are separate screens; history is not a per-job pay statement.','settlement'],
2:['D','Compensation uses approved tariff components and settlement evidence, not fixed30/50 daily total210.','settlement'],
3:['A','COD finance rows/batches retain each collection and expected amount.','finance,batches'],
4:['A','Pending/verified remittances distinguish cash custody until independent confirmation.','batches'],
5:['D','Shortage remains disputed/unverified until exact evidenced reconciliation; it is not confirmed as a partial handover.','batches'],
6:['A','Rider cannot verify their own submitted cash.','batches']},'finance');

put('LOG-DASH',{
1:['P','Scoped live counters exist, but sections/status definitions differ and counters may be cached20seconds.'],
2:['D','Counters use a20-second cache/page refresh; no immediate push count change guarantee.'],
3:['D','Dashboard has today-specific metrics, not a start/end date control; analytics/reports have ranges.','hub,reports'],
4:['A','HubOrderScope limits queues/metrics to own custody/origin/destination.','scope']},'hub');
put('LOG-RIDER',{
1:['D','Logistics creates/invites riders; final account approval is logistics-admin responsibility, no hub approval step.','ridercreate,approval'],
2:['D','No hub pending-rider rejection; logistics-admin reviews and rejects.','approval'],
3:['D','Suspension opens custody recovery exceptions; it does not automatically unassign every pre-pickup job.','exception'],
4:['D','Suspension reason is required/max500 but no minimum10; nine characters are accepted.','moderation'],
5:['P','Hub can restore only its own suspensions; Online mode is absent.','moderation'],
6:['P','Status/workload counts exist; no work Online/Offline or3-job limit contract.','hub,dispatch'],
7:['A','Multi-hub rider ownership restricts management.','scope,dispatch']},'ridercreate');
put('LOG-DISP',{
1:['M','No automatic nearest-online-rider matching/offer engine.'],
2:['A','Manual approved-rider assignment produces timeline/notifications and preserves area/capacity constraints.'],
3:['P','Suspended/unapproved riders are blocked; Offline is not a dispatch eligibility state.'],
4:['D','Limit is10 plus verified cargo weight, not3 active jobs.'],
5:['A','Pre-handoff reassignment records old/new assignee and releases former assignment.'],
6:['A','Physical hub handoff/pickup prevents ordinary reassignment; recovery has separate evidence controls.'],
7:['D','No assign-five/skip-two workflow at3 jobs; batch scan release operates on previously assigned manifests.'],
8:['A','Only sorted/assigned parcels can receive delivery assignment; premature requests are rejected.']},'dispatch');
put('LOG-HUB',{
1:['A','Scanned intake creates At Sorting Center event and shared timeline.','transition'],
2:['A','Destination province/municipality mapping resolves one routing area and sorted event.','scope,transition'],
3:['A','After hub scanned release, the assigned rider starts Out for Delivery and the buyer code is issued, matching the listed steps.','dispatch,delivery'],
4:['A','Duplicate intake does not duplicate custody transition.','transition'],
5:['A','Unknown parcel scan rejects without creating a shipment.','transition'],
6:['A','Status/custody guards reject out-of-sequence intake.','transition'],
7:['A','Unrelated hub cannot mutate another hub parcel; transfer acceptance is separately authorized.','scope']},'hub');
put('LOG-TRK',{
1:['P','Tracking search matches escaped order_number only; no generic buyer-name/order-ID match for this field.','tracking'],
2:['M','No arbitrary hub override to Delivered with reason; proof/code/custody service remains mandatory.','transition'],
3:['P','Existing status/assignment/rate actions are audited, but the specified override action is absent.','audit,transition'],
4:['M','No Lost shipment state/automatic loss claim refund.','transition'],
5:['M','Damage can be a failure reason/evidence, but no dedicated Damaged state with mandatory photo/claim trigger.','fail,transition']},'tracking');
put('LOG-RTS',{
1:['P','Failure limit and return/recovery queues exist, but three attempts do not automatically become RTS.','fail,transition'],
2:['D','Physical returned-to-seller status is retained; buyer order is not automatically relabeled Cancelled.','transition'],
3:['P','Stock waits for separate seller restock inspection and can be excluded as damaged, not automatically on handover alone.','inspection'],
4:['P','Approved wallet return/refund is supported, but requires admin authorization and executed refund; not auto-triggered on RTS.','finance,return']},'return');
put('LOG-RATE',{
1:['D','Tariffs require different logistics-admin approval/activation, not immediate hub edit; quotes revalidated and old orders frozen.','tariff,shipping'],
2:['D','Rates are integer-cent bracket components with positive total; zero component values are allowed, unlike a single-rate field.','tariff'],
3:['D','Missing routing map can pass web placement then fail at sorting; no-service checkout block is incomplete.','checkoutform'],
4:['P','Routing zone/mapping exists; a priced serviceable zone is not automatically created just by adding the area.','hub,tariff']},'tariff');
put('LOG-COD',{
1:['A','Collection/batch snapshots retain expected, submitted, received and differences.','batches'],
2:['A','Shortage/excess explicitly remains Discrepancy with independent investigation.','batches'],
3:['A','Hub verifies its batches, logistics-admin verifies individual remittance/reconciles, all audited.','batches,finance'],
4:['P','Role analytics CSV/XLSX exports exist; dedicated financial remittance statement is CSV only, not matching XLSX screen export.','exports,batches'],
5:['P','Empty analytics exports are handled; exact financial XLSX export does not exist.','exports']},'batches');

for (const c of cases) if (!assessed.has(c.id)) throw new Error(`Unassessed source case ${c.id}`);
for (const id of assessed.keys()) if (!cases.some(c=>c.id===id)) throw new Error(`Unknown case ${id}`);

// Checklist decisions are explicit, separate from the black-box tests.
const check = {};
function ck(id,s,note,refs){if(check[id])throw new Error('Duplicate checklist '+id);check[id]={status:status[s],actual:note,evidence:refs.split(',')};}
const checkRows = [
['1.1.1','A','Credential validation, role login and isolated logout exist.','auth'],['1.1.2','A','Role middleware/admin-domain permissions reject other roles.','roles'],['1.1.3','P','Reset OTP works; configured idle lifetime120min differs from workbook30min. Real idle browser timeout not replayed.','auth,session'],
['1.2.1','A','Moderation lists/searches role-scoped sellers/stores.','moderation'],['1.2.2','A','Private Valid ID/business permit review is implemented.','approval'],['1.2.3','A','Pending approve/reject is audited and notifies account; reject needs reason.','approval,notifications'],['1.2.4','P','Suspend/reactivate hide/restore marketplace availability; no distinct permanent store-ban lifecycle.','moderation,search'],
['1.2.5','M','No exclusive one-category-per-seller assignment on approval.','category,approval'],['1.2.6','M','No global category ownership/uniqueness enforcement.','category'],['1.2.7','M','No administrative category reassignment/migration workflow.','category'],['1.2.8','M','No category deletion interface or assigned-category relationship.','category'],['1.2.9','D','One global commission basis-point setting; no seller/category-specific rate administration.','pricing'],['1.2.10','P','Store and scoped analytics/order reports exist; not a single seller-admin drilldown for every requested dimension.','reports,moderation'],
['1.3.1','D','Two administrators split commerce buyers/sellers versus logistics hubs/riders.','moderation,roles'],['1.3.2','P','Logistics admin approval/suspension supported; no routine hard-delete-account action.','approval,moderation'],['1.3.3','A','Commerce administrator controls buyer suspension/reactivation.','moderation'],['1.3.4','P','Reviewed profile edits and user password reset exist; no unrestricted admin password replacement for all users.','staff,auth'],
['1.4.1','A','Marketplace product compliance review handles submitted listings.','product'],['1.4.2','A','Compliance reject/archive suppresses marketplace availability.','delete,product'],['1.4.3','M','Categories are configured labels, not administrative hierarchy CRUD.','category'],['1.4.4','D','Purchase/order relationship exists but new checkout is one shop; no multi-seller parent-order workflow.','checkout'],['1.4.5','A','Sales/analytics reporting supports seller/status/date filters.','reports'],['1.4.6','P','Controlled cancellation/refund exists; no arbitrary status override bypass.','cancel,finance,transition'],
['1.5.1','A','Integer-cent commission snapshots are calculated per seller order at configured global rate.','pricing,finance'],['1.5.2','P','Balanced ledger/pending/available/history pass tests; sandbox/live-provider limitations remain.','finance,wallet'],['1.5.3','P','Seven-day eligibility, request/approval/execution exist; no teacher minimum100/single pending or live payout provider.','payout,withdraw'],['1.5.4','A','Open disputes/refunds hold payout and eligibility is rechecked.','finance'],['1.5.5','P','Wallet/sales/analytics statements export, but dedicated payout schedule/statement coverage differs.','exports,payout'],
['1.6.1','A','Platform funded vouchers and announcements/banners are implemented.','voucher,promotion'],['1.6.2','O','Seller promotions are owner-scoped and capped; no optional separate pre-approval. Confirm whether teacher requires it.','promotion'],['1.6.3','A','Marketplace-admin dispute/refund decision workflow exists.','dispute,finance'],['1.6.4','P','Account suspension/reason/notifications exist; no separate warning points or financial penalty engine.','moderation'],
['1.7.1','P','Platform/seller reports exist; order analytics lacks explicit category filter/report grouping.','reports'],['1.7.2','P','Role delivery/order analytics PDF/XLSX exists; full user-account-list export not found.','exports'],['1.7.3','P','Metric tests pass; logistics counters have20s cache and seller low-stock threshold differs from5.','hub,lowstock'],['1.7.4','A','Append-only UI administrative audit records include before/after and actor.','audit'],
['2.1.1','A','Seller applies with required ID/permit and identity validation.','auth,approval'],['2.1.2','A','Pending account cannot access operational seller routes.','auth,roles'],['2.1.3','A','In-app/mail notification dispatch exists; real email receipt was not tested.','notifications'],['2.1.4','D','Seller changes wait for marketplace-admin review, as previously requested.','staff,appearance'],['2.1.5','D','Seller business category can be proposed for review; no exclusive immutable assigned category.','staff,category'],['2.1.6','A','Owner relationships and admin review isolate store data.','staff,product'],
['2.2.1','A','Product description/price/media/category/flat variation rows exist.','product,variation'],['2.2.2','D','Any supported category is accepted; no seller-category ownership check. Probe confirms.','category'],['2.2.3','D','All configured shared categories are selectable.','category'],['2.2.4','A','New/materially edited products require compliance review.','product'],['2.2.5','P','Edit/archive/delete exist; no equivalent complete seller active/inactive toggle.','delete'],['2.2.6','O','No optional product CSV/XLSX import found.','inventory'],['2.2.7','P','Stock updates/shortage display exist; seller low-stock once-per-crossing alert is missing.','lowstock'],['2.2.8','P','Basic invalid/negative/upload checks exist, but teacher length/price/count limits differ.','product,media'],
['2.3.1','A','Order placement notifies the order seller only.','checkout'],['2.3.2','A','Seller order shows its own captured items; mixed-shop placement is forbidden.','sellerorder'],['2.3.3','A','Confirmed/Preparing/Ready statuses are guarded transitions.','sellerorder,transition'],['2.3.4','A','Printable parcel label/waybill with QR/custody information exists.','sellerorder'],['2.3.5','P','Seller cancellation/inspection and admin-led disputes exist; no seller-first refund approval chain.','dispute,inspection'],['2.3.6','P','Private buyer messaging exists; seller review replies absent.','chat,review'],
['2.4.1','P','Ledger/dashboard payable calculates commission/promotion funding; legacy report can disagree on refunded completed orders.','finance,sellerreport'],['2.4.2','D','Available requires seven-day delivery hold and approved settlement, not immediate receipt confirmation.','payout'],['2.4.3','A','Eligible order payout requests and wallet transaction/payout history exist.','payout,withdraw'],['2.4.4','A','Seller analytics includes sales and product performance.','reports'],['2.4.5','P','Funded store vouchers exist; automatic product sales/flash/bundle discounts absent.','voucher,promotion'],
['3.1.1','P','Email registration/reset verification and login/logout exist; SMS phone verification absent.','auth'],['3.1.2','P','Profile/address CRUD exists; buyer profile phone update validation is weaker than registration.','profile,address'],['3.2.1','A','Shared catalog literal search/filter/sort supports all available sellers.','search'],['3.2.2','A','Public store page/following and shop-filtered listings exist.','pdp,follow'],['3.2.3','D','Category includes many sellers, not an exclusively assigned seller.','category'],['3.2.4','A','Product gallery/video, stock, flat variants, text reviews and seller info exist.','pdp'],['3.2.5','A','Cart stores items from multiple sellers, even though checkout must select one.','cart'],['3.2.6','A','Shop grouping, quantity update/remove and selection exist.','cart'],['3.2.7','A','Buyer cart is persisted independently of logout.','cart'],['3.2.8','P','Follow store exists; product wishlist absent.','follow'],
['3.3.1','D','New orders are limited to one shop per purchase.','checkout'],['3.3.2','A','Each selected seller shipment has a shipping snapshot; only one seller can be checked out at once.','shipping'],['3.3.3','P','Individual store/platform/shipping-target voucher works; simultaneous stacking absent.','pricing'],['3.3.4','A','Server authoritative cent totals and stale/tampered quote tests pass for supported checkout.','checkout,pricing'],['3.3.5','P','COD operational; wallet sandbox only; no live card/bank/hosted e-wallet payments.','wallet'],['3.3.6','P','Atomic wallet failure/reversal works, but no live gateway cancellation/To Pay state.','wallet'],['3.3.7','P','Confirmation/in-app/mail dispatch exists; SMS channel absent and real email delivery not verified.','checkout,notifychannels'],
['3.4.1','P','Each seller order has distinct identity/status; no simultaneous multi-seller purchase.','checkout,transition'],['3.4.2','P','Polling/refresh and transaction notifications synchronize stages; no push-realtime guarantee.','transition,notifications'],['3.4.3','A','Scoped timeline and assigned rider/contact information exist.','buyerorder'],['3.4.4','D','Mixed-shop purchase not supported; independent orders can each request cancellation.','cancel,checkout'],['3.4.5','P','Receipt and order dispute exist; window/approval process differs from teacher assumptions.','buyerorder,dispute'],['3.4.6','P','Product ratings only; no separate seller/rider rating or photo review/edit.','review'],['3.4.7','P','Related-user chat and direct admin dispute exist; no seller-rejection escalation step.','chat,dispute'],
['4.1.1','A','Sorting-center login/dashboard restricted by role.','roles'],['4.1.2','A','Owned hub pickup queue can include several sellers.','hub'],['4.1.3','A','Seller pickup schedule and logistics assigned rider/capacity flow exists.','pickup,dispatch'],['4.1.4','A','QR/manual parcel custody events are shared and notified.','transition'],['4.2.1','A','Strict multi-stage parcel lifecycle exists with exceptions/recovery controls.','transition'],['4.2.2','D','Separate parcels/orders exist; no mixed-seller parent checkout/consolidation.','checkout'],['4.2.3','A','Manual area-based dispatch, scheduling and dedicated transfer drivers exist.','dispatch'],['4.2.4','A','Failure attempts, physical hub recovery and reassignment are supported.','fail,exception'],['4.2.5','A','Scanned RTS and seller restock inspection exist.','return,inspection'],['4.3.1','P','Rate versions/approval implemented but runtime has no approved tariffs and still uses flat50.','tariff,shipping'],['4.3.2','A','Role analytics and workload/delivery metrics exist.','reports'],['4.3.3','P','Operational/analytics and remittance reports exist, but one per-seller combined report/export differs.','reports,batches'],
['5.1.1','P','Login/logout works; manual Online/Offline work switch absent.','auth,dispatch'],['5.1.2','A','Assigned pickup/delivery can be accepted/declined before physical release.','rideraccept,pickup'],['5.1.3','A','Owned assignment/parcel addresses and contact details are visible.','pickup,delivery'],['5.1.4','M','No map/directions action found in rider pages/controllers.','inventory'],['5.2.1','A','Seller/hub handovers require matching identities and parcel scans.','pickup,dispatch'],['5.2.2','P','Separate pickup orders/stops exist; no map-based multi-stop route itinerary.','pickup'],['5.2.3','A','Rider actions follow guarded parcel state transitions.','transition'],['5.2.4','A','Proof upload plus six-digit buyer delivery code required; no signature needed for the checklist OR condition.','delivery,code'],['5.2.5','A','Exact COD collection is recorded after proof/code.','delivery,finance'],['5.2.6','A','Enumerated failure reasons and notes support reported issues.','fail'],['5.2.7','A','Phone links and scoped private messaging exist.','chat,pickup'],['5.2.8','P','History and settled earnings exist; configured rates differ and no approved runtime tariff currently exists.','settlement'],
['6.1.1','D','Explicit single-shop checkout contradicts multi-seller splitting reference.','checkout'],['6.1.2','A','Existing full journey tests cover seller/pickup/hub transfer/delivery/receipt.','transition'],['6.1.3','A','Shared status/events and notifications are role-scoped and transaction-aware.','transition,notifications'],['6.1.4','P','In-app/mail dispatch supported, SMS absent and real external delivery not verified.','notifychannels'],['6.2.1','A','Stock deducted on supported placement, restored once on approved cancellation.','checkout,stock'],['6.2.2','A','Per-order commission snapshot uses configured global rate.','pricing,finance'],['6.2.3','A','Sponsor funding and seller payable reconcile without platform voucher charging seller.','budget,finance'],['6.2.4','A','Completion, seven-day hold, verified money and approval gate seller release.','payout'],
['6.3.1','D','Existing independent orders isolate cancellations, but no new multi-seller parent checkout.','checkout,cancel'],['6.3.2','P','Admin decision/return/refund ledger exists; seller-first response/escalation and live gateway reversal differ.','dispute,finance'],['6.3.3','A','Seller product/order/finance scope checks are enforced.','product,sellerorder,finance'],['6.3.4','D','Supported shared categories are not locked to a seller; probe confirms.','category'],['6.3.5','A','Route/domain and record ownership checks cover roles.','roles'],['7.1.1','U','Responsive CSS exists; physical desktop/tablet/mobile-browser acceptance was not exercised.','inventory'],['7.1.2','U','Server tests do not certify page performance, all links/images or external-device loading.','inventory'],['7.1.3','P','Validation/error handling exists, with concrete update/product boundary gaps in this audit.','profile,product'],['7.1.4','A','Hashed passwords, private documents/evidence and scoped access covered by tests; not a full penetration test.','auth,approval'],['7.1.5','A','Logout invalidates the active tab session; persisted buyer cart is intentionally retained.','auth,session']
];
for (const row of checkRows) ck(...row);
for (const c of checklist) if(!check[c.id]) throw new Error('Unassessed checklist '+c.id);
if(Object.keys(check).length!==checklist.length)throw new Error('Checklist count mismatch');

const assumptions = [
['A1','Different','Teacher demo accounts/products/vouchers are reference fixtures, not assumed present. No reseeding/reset was performed.'],
['A2','Different','One voucher per single-shop checkout; no store+platform+shipping stacking.'],['A3','Partial','Supported order stock deduction/cancel restore works; unpaid gateway reservation absent; returns require restock inspection.'],['A4','Missing','No sale-price engine to combine with voucher.'],['A5','Missing','No flash allocation or fallback price split.'],['A6','Partial','Buyer cutoff at Ready for Pickup aligns; cancellation is seller-approved/24h, no one-hour unpaid gateway timer.'],['A7','Different','Shared category string and up to100 flat variation rows, not two axes/20 options/two category levels/three category memberships.'],['A8','Partial','Sponsor ledger exists, but no sale/flash/bundle pricing, stacking, partial item refund allocation or new multi-store checkout.'],['A9','Different','Runtime/default10% global merchandise commission, not5%; configurable integer basis points.'],['A10','Partial','Strict lifecycle exists with richer scanned hub/transfer/handoff stages and no generic override; no To Pay workflow.'],['A11','Different','Buyer receipt supported; no seven-day auto-completion; completed/late disputes remain allowed.'],['A12','Different','One product review/order, completed required, text max1000; no photos/edit/reply/30-day window.'],['A13','Partial','Three failure attempts supported; refused/third failure requires physical recovery instead of automatic RTS.'],['A14','Different','No Online or nearest-offer cascade;15-minute acceptance;10 active deliveries with verified cargo capacity.'],['A15','Different','Completion+7day hold/funding/admin settlement; no100 minimum or one pending wallet withdrawal; order payout separate.'],['A16','Different','Image rules vary: most product/profile2MB+WebP; delivery4MB+PDF; chat5MB+documents; no review photos.'],['A17','Different','Product name/price/stock bounds differ; buyer cart caps20; zero-image product accepted.'],['A18','Partial','Vacation blocks purchases, but pending sellers have no name-only Save Draft.'],['A19','Different','Archiving/compliance history and retired variants replace the specified unfinished-order block and store-category delete semantics.'],['A20','Different','RuntimePHP50 flat; optional approved actual/volumetric bracket rates, not workbook whole-kg zone table.'],['A21','Partial','Funded cancellation restores usage after approval; post-delivery refund does not restore use. Legacy unbudgeted behavior needs separate review.'],['A22','Partial','Role/ownership tested; actual configured session lifetime120min, not30. Flutter/API client excluded from this web audit.'],['A23','Partial','Role notifications exist; email uses configured mail/queue, not a demonstrated catcher; exact every-recipient delivery not replayed.'],['A24','Partial','Independent exact remittance and batches exist; no end-of-day enforcement; discrepancy remains unverified.'],['A25','Different','Profile/product/chat/reason limits differ; see individual case rows and probes.'],['A26','Different','Uppercase/global code uniqueness, but max50 without4–12 alphanumeric check.'],['A27','Partial','Pause/end blocks future use and retains order snapshot; no limited post-claim edit endpoint and claims remain retained.'],['A28','Missing','No scheduled store-sale pricing.'],['A29','Different','Seller dashboard stock<10; buyer subscriptions use5; no specified seller/variant crossing alert.'],['A30','Different','No ten-address cap; default deletion automatically chooses replacement. Order snapshots align.'],['A31','Different','Admin-led dispute,20–2000 description, one optional evidence file; no seller3day/escalation chain or7day return limit.'],['A32','Different','Photo/code required, but4MB/PDF allowance and refresh-based lock recovery differ from5MB/15min automatic unlock.'],['A33','Different','Approved cost component allocations at settlement, not fixedPHP30/PHP50 immediately; zero approved runtime tariffs.'],['A34','Different','Manual area-based assignment and custody recovery exceptions; no nearest-online auto-routing.'],['A35','Partial','Actors/before-after/reasons logged for existing actions, but manual arbitrary overrides do not exist.'],['A36','Partial','AnalyticsCSV/XLSX/PDF exists; dedicated wallet/remittance/shipping statementsCSV only. Screen/report recognition scopes vary.'],['A37','Partial','Checkout/ledger/claim/scan/payout paths have locks/unique keys; product create duplicates demonstrated. Not every create endpoint is idempotent.']
];

const esc = v=>String(v??'').replaceAll('|','\\|').replace(/\r?\n/g,'<br>');
const link = key=>{const e=evidence[key];if(!e)throw new Error('Unknown evidence '+key);return `[${e.file}:${e.line}](../../../${e.file}#L${e.line})`;};
const tally = rows=>Object.fromEntries(Object.values(status).map(s=>[s,rows.filter(x=>x.status===s).length]));
const results=cases.map(c=>({...c,...assessed.get(c.id),teacher_case_executed:false}));
const checkResults=checklist.map(c=>({...c,...check[c.id],teacher_checkbox_executed:false}));
fs.writeFileSync(path.join(dir,'case-assessments.json'),JSON.stringify(results,null,2));
fs.writeFileSync(path.join(dir,'checklist-assessments.json'),JSON.stringify(checkResults,null,2));
for(const role of ['Seller','Buyer','Rider','Logistics']) {
    const list=results.filter(c=>c.file.includes(`_${role}_`));
    let md=`# ${role} teacher test case comparison\n\nDate: 13 November 2026. Laravel web only. Source: ${list[0].file}. All ${list.length} source cases are accounted for. Status is an implementation comparison, **not a claim that the teacher case was executed**. Exact fixture records, browser interactions and external payment/mail delivery were not replayed. Related regression tests passed separately.\n\n`;
    md+='| Case and source | Scenario | Teacher expected result | Assessment | Current behavior and difference | Code evidence |\n| --- | --- | --- | --- | --- | --- |\n';
    for(const c of list)md+=`| ${esc(c.id)}<br>${esc(c.sheet)}!I${c.row} | ${esc(c.scenario)} | ${esc(c.expected)} | **${c.status}** | ${esc(c.actual)} | ${c.evidence.map(link).join('<br>')} |\n`;
    fs.writeFileSync(path.join(dir,`${role.toLowerCase()}-test-case-audit.md`),md);
}
let md='# Teacher role checklist comparison\n\nDate: 13 November 2026. Source: Ecommerce_Role_Functionality_Checklist.docx. All127 numbered items are compared. Paragraph references use the supplied OOXML paragraph order. These assessments do not fill or sign the teacher Pass/Fail boxes.\n\n| Item and paragraph | Teacher criterion | Assessment | Current behavior and difference | Code evidence |\n| --- | --- | --- | --- | --- |\n';
for(const c of checkResults)md+=`| ${c.id}<br>P${c.paragraph} | ${esc(c.text)} | **${c.status}** | ${esc(c.actual)} | ${c.evidence.map(link).join('<br>')} |\n`;
fs.writeFileSync(path.join(dir,'role-checklist-audit.md'),md);
md='# Teacher workbook assumptions compared\n\nThe source explicitly says its limits were modeled on marketplaces, not extracted from EZiCart. A2–A8 require agreement before testing; A25–A37 also determine strict expectations. Do not silently treat those assumptions as approved changes.\n\n| Assumption | Teacher rule | Assessment | Actual rule or scope difference |\n| --- | --- | --- | --- |\n';
const assumed=sources.workbooks[0].sheets.find(x=>x.name==='Assumptions').rows;
for(const [id,s,note]of assumptions){const r=assumed.find(r=>r.cells[`A${r.row}`]===id);md+=`| ${id}<br>Assumptions!C${r.row} | ${esc(r.cells[`C${r.row}`])} | **${s}** | ${esc(note)} |\n`;}
fs.writeFileSync(path.join(dir,'assumption-comparison.md'),md);
md='# Evidence index\n\nCode anchors were resolved against the actual source, not guessed. Related test names identify regression coverage, not one-to-one execution of teacher cases. Missing features were searched across Laravel routes, controllers, services, models, migrations and Blade views. Flutter was not inspected for feature compliance.\n\n| Evidence key | Source | Related tests or verification scope |\n| --- | --- | --- |\n';
for(const [key,e]of Object.entries(evidence))md+=`| ${key} | ${link(key)} | ${esc(e.test)} |\n`;
fs.writeFileSync(path.join(dir,'evidence-index.md'),md);
const observations=fs.readFileSync(path.join(dir,'probe-observations.jsonl'),'utf8').trim().split(/\r?\n/).map(JSON.parse);
const uniqueObservations=[...new Map(observations.map(x=>[x.criteria,x])).values()];
fs.writeFileSync(path.join(dir,'probe-observations.json'),JSON.stringify(uniqueObservations,null,2));
const totals={date:'2026-11-13',scope:'Laravel web only',git_revision:execFileSync('git',['rev-parse','HEAD'],{encoding:'utf8'}).trim(),cases:tally(results),checklist:tally(checkResults),case_count:results.length,checklist_count:checkResults.length,roles:Object.fromEntries(['Seller','Buyer','Rider','Logistics'].map(role=>[role,tally(results.filter(c=>c.file.includes(`_${role}_`)))]))};
fs.writeFileSync(path.join(dir,'audit-summary.json'),JSON.stringify(totals,null,2));
console.log(JSON.stringify(totals,null,2));
