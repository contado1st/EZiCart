import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import vm from 'node:vm';

// Audit artifacts only. No application writes, database mutation, or child processes.
const root = process.cwd();
const previous = 'docs/audits/2026-11-13';
const output = 'docs/audits/2026-11-14';
const revision = process.argv[2];
if (!/^[a-f0-9]{40}$/.test(revision ?? '')) throw new Error('Pass the inspected git revision as argv[2].');
const read = p => JSON.parse(fs.readFileSync(path.join(root, p), 'utf8'));
const write = (p, value) => fs.writeFileSync(path.join(root, p), typeof value === 'string' ? value : JSON.stringify(value, null, 2) + '\n');
const old = read(`${previous}/case-assessments.json`);
const oldChecklist = read(`${previous}/checklist-assessments.json`);
const sources = read(`${previous}/teacher-sources.json`);
const runtime = read(`${output}/runtime-settings.json`);
const evidence = {};
const unresolved = [];
function ev(key, file, needle, tests = '') {
    const lines = fs.readFileSync(path.join(root, file), 'utf8').split(/\r?\n/);
    const index = lines.findIndex(line => line.includes(needle));
    if (index < 0) { unresolved.push({key, file, needle}); return; }
    evidence[key] = {file, line:index + 1, anchor:needle, tests};
}
// Re-resolve historical anchors against CURRENT source; do not rerun the historical audit.
const declarations = fs.readFileSync('scripts/build-teacher-audit.mjs', 'utf8').split(/\r?\n/).filter(line => line.startsWith('ev(')).join('\n');
vm.runInNewContext(declarations, {ev});
ev('account14', 'app/Http/Controllers/AccountController.php', 'function update(', 'PartialAccountIntegrationTest; TeacherReauditProbeTest');
ev('email14', 'app/Services/BuyerEmailChangeService.php', 'function request(', 'PartialAccountIntegrationTest; TeacherReauditProbeTest');
ev('draft14', 'app/Services/ProductDraftService.php', 'class ProductDraftService', 'PartialCatalogIntegrationTest; ApplicantDraftWorkspaceTest');
ev('catalog14', 'app/Http/Controllers/Seller/ProductController.php', 'class ProductController', 'PartialCatalogIntegrationTest; TeacherReauditProbeTest');
ev('sku14', 'app/Services/ProductVariationEditorService.php', 'function prepare(', 'PartialCatalogIntegrationTest');
ev('discover14', 'app/Services/ProductDiscoveryService.php', 'class ProductDiscoveryService', 'PartialBuyerDiscoveryTest; PartialCatalogIntegrationTest');
ev('wish14', 'app/Services/ProductWishlistService.php', 'class ProductWishlistService', 'PartialBuyerDiscoveryTest');
ev('voucher14', 'app/Services/VoucherScheduleRules.php', 'class VoucherScheduleRules', 'PartialVoucherIntegrationTest; TeacherReauditProbeTest');
ev('eligibility14', 'app/Services/VoucherEligibilityService.php', 'class VoucherEligibilityService', 'PartialVoucherIntegrationTest');
ev('sellerorders14', 'app/Http/Controllers/Seller/SellerOrderController.php', 'function index(', 'PartialSellerReportingTest');
ev('sales14', 'app/Services/SalesReportService.php', 'function row(', 'PartialSellerReportingTest');
ev('sellerreviews14', 'app/Http/Controllers/Seller/SellerReviewController.php', 'class SellerReviewController', 'PartialReviewIntegrationTest');
ev('review14', 'app/Services/ReviewService.php', 'function rules(', 'PartialReviewIntegrationTest; TeacherReauditProbeTest');
ev('enquiry14', 'app/Http/Controllers/DirectMessageController.php', 'function store(', 'PartialProductEnquiryTest');
ev('available14', 'app/Services/RiderWorkAvailabilityService.php', 'function enforced(', 'RiderWorkAvailabilityTest; TeacherReauditProbeTest');
ev('courier14', 'app/Http/Controllers/Courier/CourierController.php', 'class CourierController', 'CourierWorkPresentationTest; DeliveryRetryGuidanceTest');
ev('compensation14', 'app/Services/CourierCompensationService.php', 'class CourierCompensationService', 'CourierWorkPresentationTest');
ev('delivery14', 'app/Services/DeliveryProgressService.php', 'class DeliveryProgressService', 'DeliveryRetryGuidanceTest; LogisticsQueueTrackingTest');
ev('groups14', 'app/Services/BuyerOrderGroupingService.php', 'function expression(', 'DeferredWalletOrderTest; TeacherReauditProbeTest');
ev('pay14', 'app/Services/WalletOrderPaymentService.php', 'class WalletOrderPaymentService', 'DeferredWalletOrderTest; PrepaidPaymentVerificationTest');
ev('demo14', 'app/Services/DemoWalletFundingService.php', 'class DemoWalletFundingService', 'DemoWalletFundingTest; DeferredWalletOrderTest');
ev('queue14', 'app/Services/LogisticsQueueService.php', 'class LogisticsQueueService', 'LogisticsQueueTrackingTest');
ev('correction14', 'app/Services/ParcelCorrectionService.php', 'class ParcelCorrectionService', 'ParcelCorrectionTest');
ev('inspection14', 'app/Services/ReturnInspectionService.php', 'class ReturnInspectionService', 'ReturnWorkflowRevisionTest');
ev('return14', 'app/Services/ReturnWorkflowService.php', 'class ReturnWorkflowService', 'ReturnWorkflowRevisionTest; LogisticsQueueTrackingTest');
ev('zone14', 'app/Services/ZoneReadinessService.php', 'class ZoneReadinessService', 'ZoneReadinessTest');
ev('remittance14', 'app/Services/RemittanceReportService.php', 'class RemittanceReportService', 'RemittanceReportTest');
const updates = new Map();
const labels = {A:'Aligned', P:'Partial', D:'Different', M:'Missing', U:'Unverified', O:'Optional'};
function update(id, state, actual, refs) {
    if (updates.has(id) || !old.some(row => row.id === id)) throw new Error(`Invalid/duplicate case ${id}`);
    updates.set(id, {status:labels[state], actual, evidence:refs.split(',')});
}

// Independently compare every originally Partial case with the full expected result.
update('SEL-PROF-002','A','Cleared official identity/contact fields are rejected; changed pickup locations require every location component. Unchanged incomplete legacy addresses are retained on unrelated edits. Valid official changes still require admin review.','account14,staff');
update('SEL-PROF-011','A','Pending applicants have a restricted private draft workspace; publishing remains blocked until approval. Drafts never enter the public catalog.','draft14,roles');
update('SEL-PROD-008','D','Positive decimal kg and optional supplied dimensions are validated and 0.01kg persists exactly. Checkout still uses PHP50 flat in the configured system; no required whole-kg charge rounding.','catalog14,shipping');
update('SEL-PROD-010','A','Incomplete drafts survive failed submission and show required-field errors; complete submission uses catalog validation and idempotently creates a pending-review product.','draft14,catalog14');
update('SEL-PROD-011','A','Seller sales pause/resume is independent of compliance approval. Paused products are unavailable in discovery, cart and checkout without deleting saved lines/media/stock; resuming does not bypass compliance.','catalog14');
update('SEL-PROD-015','A','New order items snapshot the purchased image as well as name/price. Replacing or archiving a product retains referenced media. Legacy purchases without an image snapshot cannot be reconstructed.','catalog14,media');
update('SEL-VAR-006','A','Normalized SKU uniqueness is scoped across a seller store, including retired variants, and backed by a database constraint. Another store may use the same SKU.','sku14');
update('SEL-VAR-007','A','Cards/detail page use the stocked active-variant price range; choosing a row shows its exact price. Sold-out options are excluded from the purchasable range.','discover14,sku14');
update('SEL-INV-003','D','Inventory restoration is exactly-once on actual cancellation. A paid buyer cancellation remains a pending seller decision/24-hour timeout, so stock does not immediately return when the buyer merely requests it. Unpaid demo reservations cancel immediately.','cancel,stock,pay14');
update('SEL-VOU-001','A','A funded ongoing fixed seller voucher is claimable on store/product surfaces with minimum-spend rules. Sponsor budget must exist first; simulated funding is available in the current local system.','voucher14,eligibility14,budget');
update('SEL-VOU-007','D','Zero/invalid usage, per-buyer above total, invalid date sequence and zero cap are rejected. Capped 100% discounts are explicitly accepted, whereas this case requires rejecting 100%.','voucher14,voucher');
update('SEL-VOU-010','A','Ended/paused claims remain in unavailable history but are no longer offered for use; pricing rejects them. Orders retain their original discount snapshot.','eligibility14,claim');
update('SEL-ORD-001','A','Seller packing/order tabs, filtered counts and lists share seller/status/date/buyer-search predicates, stable ordering and pagination.','sellerorders14');
update('SEL-ORD-005','A','Seller cancellation of a paid internal-wallet order reverses the exact payment once, restores stock/eligible voucher usage and voids seller liability. Real provider refund initiation is not integrated.','pay14,cancel,finance');
update('SEL-RET-007','D','Executed refunds now reconcile across screen/export/analytics; net sales and commission are reduced correctly. Original merchandise gross remains visible with a separate refund contra amount, unlike the teacher requirement to exclude the refunded sale from gross.','sales14');
update('SEL-REV-001','A','Own seller review page lists stars, comment, private-photo delivery, purchase variant and timestamp; rating/text/media/date filters are implemented. Replying is a separate missing feature.','sellerreviews14,review14');
update('SEL-EARN-003','D','Cancelled/VOID rows do not add recognized gross/net/commission; full executed refunds do not count as completed sales or net earnings. Refunded merchandise remains in historical gross with a separate refund deduction, so the exact teacher gross rule differs.','sales14');
update('SEL-EARN-007','P','Wallet withdrawal retries reuse a stable request key and reserve available funds once. The separate Request seller payout action remains per order and creates one liability, not an immediate decrease in wallet Available; the two payout concepts must not be conflated.','withdraw,payout');
update('RID-PROF-001','A','Dedicated rider profile shows official identity/licence/expiry/vehicle/plate, assigned hub, coverage and real verification/document state. Existing licence and OR/CR files open through authorized private routes; missing files are disclosed rather than invented.','courier14');
update('RID-PROF-006','A','Suspended accounts cannot choose available, accept work or enter approved-rider dispatch lists, even while availability rollout is disabled.','available14,dispatch');
update('RID-PICK-002','D','Scanning enforces assigned custody and records pickup/timestamps/notifications; cancellation is blocked after handover. Compensation remains an approved-tariff estimate pending verified settlement, not immediate fixed PHP30 earnings.','courier14,compensation14');
update('RID-PICK-003','A','Raw manual parcel code and scanned EZP code share the same custody transition/notifications. Use the actual parcel label code; an order reference/order_number is intentionally not an accepted parcel credential.','courier14');
update('RID-DLV-001','D','Sequential release/delivery, photo, delivery code, exact COD collection and role timelines work. Delivery compensation is pending/verified under tariff settlement, not an immediate fixed PHP50 credit.','courier14,delivery,compensation14');
update('RID-DLV-006','A','Verified internal-wallet payments show paid/no COD at pickup and delivery. Readiness verifies original balanced ledger evidence and refuses missing/mismatched/reversed payment evidence; no cash collection is added.','pay14,courier14');
update('RID-FAIL-004','A','Visible attempt context and retry request/approval preserve the prior failed attempt. Actual hub recovery and another valid dispatch are required before retry, rather than a silent state reset.','delivery14');
update('RID-EARN-001','A','Paginated own-job history includes dates, parcel reference, result and compensation state/amount, including relevant transfer legs once. Unconfigured rates display unavailable instead of invented wages.','courier14,compensation14');
update('BUY-ACCT-001','A','Profile name and header persist; separate appearance save supports a 5MB avatar with private re-encoding. Ordinary profile/appearance saves leave email/phone unchanged when not edited.','account14,appearance');
update('BUY-ACCT-005','A','Password change validates current password and complexity and now rejects reuse before saving/revoking sessions.','account14');
update('BUY-ACCT-008','A','Email-change request canonicalizes case/whitespace, rejects already-used email before sending, and rechecks uniqueness at confirmation.','email14');
update('BUY-ADDR-003','A','Saved-address street is now required alongside recipient/contact/province/municipality/barangay, with PSGC/manual resolution. Failed validation does not save a new address.','address,account14');
update('BUY-SRCH-004','A','Visible-catalog typo suggestion is offered for sufficiently close no-result terms; unmatched gibberish shows the empty state. Literal search remains SQL/HTML safe.','discover14');
update('BUY-SRCH-009','A','Best-selling uses fulfilled non-refunded unit totals; price/rating/newness sorts have stable ties/pagination. Stocked variation ranges participate in price ordering/filtering.','discover14');
update('BUY-WISH-005','A','Wishlist/follow require authentication; guest requests redirect to login without creating data, and opening a login-target URL does not implicitly add a favorite.','wish14,follow');
update('BUY-VOU-004','A','Pricing rejects 299.99 with the PHP0.01 remaining-spend message and accepts the 300.00/300.01 boundary with the correct fixed discount.','eligibility14,pricing');
update('BUY-VOU-005','A','Not started, expired, inactive, exhausted, unknown and underfunded offers have distinct eligibility/rejection messages and do not apply a discount.','eligibility14');
update('BUY-VOU-012','D','Once cancellation is approved/executed, eligible funded offer usage and buyer entitlement are restored once. A pending cancellation request does not immediately restore the voucher; expired/paused/underfunded campaigns are not revived.','cancel,eligibility14');
update('BUY-PAY-002','D','Real To Pay reservation and exact once-only To Ship payment/reference now exist for internal simulated wallet credits. External e-wallet/card charge, gateway settlement and provider reconciliation remain absent.','demo14,pay14');
update('BUY-TRK-001','D','Orders now have mutually exclusive To Pay/ship/receive/completed/cancelled/return groups and matching counts. READY_FOR_PICKUP already groups as To receive, earlier than the workbook Picked Up boundary.','groups14');
update('BUY-CAN-003','D','Paid internal-wallet cancellation waits for seller decision/24-hour timeout; approval reverses the exact payment and restores eligible voucher use once. Teacher immediate cancellation/provider refund does not match this policy.','cancel,pay14');
update('BUY-RATE-001','A','A completed purchased item can receive stars/text/up to five photos; public review includes frozen variant context and masked buyer name. Private media routes enforce review/photo association.','review14');
update('BUY-RATE-003','A','Duplicate review creation is blocked; owner sees versioned Edit on the existing review. A unique order/product constraint backs the service guard. Edit window/number of edits is a separate difference.','review14');
update('BUY-CHAT-001','A','Product enquiry opens an authorized private conversation with product card. Explicit text/photo sends preserve order; opening the chat does not itself send a message.','enquiry14,chat');
update('LOG-DASH-001','A','Eight fresh hub queue counts and their linked lists use shared scoped predicates, including delivered-today semantics. No stale twenty-second cache is used for these counts.','queue14,hub');
update('LOG-RIDER-005','A','The suspending hub can reactivate its own suspended rider; only then may the rider choose availability. Restoration does not bypass unrelated/admin suspensions or silently set available.','available14,ridercreate');
update('LOG-RIDER-006','D','Work availability and current workload/today totals are displayed, but dispatch maximum remains 10 plus verified cargo capacity, not 3. Availability enforcement is disabled in the current runtime.','available14,dispatch');
update('LOG-DISP-003','P','Suspended accounts are blocked. Manual unavailable-rider filtering and server rejection work when enforcement is enabled in tests, but current runtime enforcement is OFF and 7 approved riders have not chosen availability. Offline exclusion is not active in this installation.','available14,dispatch');
update('LOG-TRK-001','A','Scoped tracking accepts exact order ID, EZP/raw parcel label code, escaped order reference, and buyer/recipient name, with safe unknown/empty results and pagination.','tracking,queue14');
update('LOG-TRK-003','D','Reason/evidence-bound correction requests have immutable actor/before-after audit records and separate admin review. No arbitrary Out for Delivery to Delivered override is allowed; actual required custody/proof events remain necessary.','correction14,audit');
update('LOG-RTS-001','D','Three failures/refusal produce a visible computed Return required queue with attempt reasons. They do not automatically change stored shipment status to Return to Sender; physical recovery/return actions remain mandatory.','delivery14,queue14');
update('LOG-RTS-003','D','Seller receipt and per-item restock inspection are separate. Stock only returns for explicitly restockable quantities; receipt alone does not restore every unit as the teacher case expects.','inspection14,stock');
update('LOG-RTS-004','D','Prepaid RTS is surfaced for exact internal-wallet refund after physical return, verified staged funds and explicit admin authorization/execution. It does not automatically start a refund solely on RTS.','return14,finance,pay14');
update('LOG-RATE-004','D','Zone-readiness preview shows mapping/capacity/rate requirements but does not activate service or tariffs. Approved tariff count is 0; adding a zone alone cannot produce the teacher priced checkout.','zone14,tariff,shipping');
update('LOG-COD-004','A','Dedicated remittance CSV and true XLSX use the same authorized filters/query/totals as the screen, all matching pages up to an explicit 10000-row limit; excessive ranges are rejected rather than silently truncated.','remittance14');
update('LOG-COD-005','A','Empty remittance CSV/XLSX returns valid headers/summary/definitions and no data rows, without an error or made-up collection values.','remittance14');

// Adjacent improvements/differences beyond the original 54, discovered in this recheck.
update('SEL-PROF-008','D','Avatar now permits 5MB but banner remains 2MB; WebP and dimension restrictions differ from the uniform teacher 5MB JPG/PNG rule. Reviewed official appearance changes are preserved.','appearance,staff');
update('SEL-PROD-009','A','Private name-only/empty drafts and resume/edit/delete now exist; draft publication still requires full validation and listing review.','draft14');
update('SEL-INV-002','A','Internal-wallet To Pay placement now reserves stock before payment; failed later payment leaves the reservation until cancel/expiry. Provider/card processing remains outside this implementation.','pay14');
update('SEL-INV-004','D','Scheduled unpaid internal-wallet expiry restores inventory/voucher budget once, but the reservation deadline is 30 minutes, not the teacher one hour.','pay14,schedule');
update('BUY-ACCT-002','P','One-character names, malformed phones and >5MB avatars are now rejected. Digits remain allowed in names and max name length is 255; not every teacher field rule is met.','account14,appearance');
update('BUY-ACCT-006','A','Current-password-protected staged email change sends a challenge and swaps identity only after verification; sessions/security version are invalidated appropriately. Real SMTP delivery was not tested.','email14');
update('BUY-ACCT-007','D','Wrong codes are rejected and attempts/expiry enforced; code expires in 10 minutes rather than the teacher five-minute expiry.','email14');
update('BUY-ACCT-009','D','Phone format is now restricted to local09/+639, but buyer phone changes remain immediate with no phone OTP/canonical duplicate workflow.','account14');
for (const id of ['BUY-WISH-001','BUY-WISH-002','BUY-WISH-003']) update(id,'A','Persistent owner-scoped product wishlist supports add/remove and repeated requests without duplicates; retired/out-of-stock favorites remain visible as unavailable history.','wish14');
update('BUY-PAY-003','D','Insufficient demo-wallet payment leaves a real To Pay reservation and permits retry before its deadline. No external gateway declined-transaction/error-return implementation exists.','pay14,demo14');
update('BUY-PAY-006','D','Unpaid demo reservations expire via the scheduled command, restoring stock/eligible voucher usage exactly once. Deadline is 30 minutes, not one hour; scheduler must be running.','pay14,schedule');
update('BUY-PAY-007','D','Pay/cancel are serialized and payment is blocked at the deadline, but both 59- and 61-minute teacher attempts are already expired under the 30-minute demo reservation.','pay14');
update('BUY-RATE-004','D','Stars1-5, five photos and 5MB checks exist, including WebP; comment max remains 1000, so the teacher 501-character rejection fails.','review14');
update('BUY-RATE-005','D','Owner/versioned review edits and revision history now exist, but multiple edits are permitted without a 30-day completion deadline. A fresh probe edited twice after 40 days.','review14');
for (const id of ['RID-AVAIL-001','RID-AVAIL-002','RID-AVAIL-003']) update(id,'P','Rider-controlled versioned available/unavailable choice exists and can preserve accepted custody while withdrawing unaccepted offers. Its dispatch filtering is staged OFF in the current installation; availability is manual, not GPS online presence.','available14');
update('RID-AVAIL-004','D','Explicit manual work availability is separate from informational browser presence. Logout/session timeout does not automatically change the saved work-availability choice; enforcement is also staged OFF.','available14,auth');
update('LOG-DASH-002','A','A fresh request now recomputes queue totals immediately with the same predicates as the linked lists; status mutations invalidate related counts. This is refresh-based consistency, not a claim of live WebSocket push.','queue14');

const cases = old.map(row => ({...row, ...(updates.get(row.id) ?? {}), previous_status:row.status,
    assessment_date:'2026-11-14', teacher_case_executed:false,
    review_basis:updates.has(row.id) ? 'Reassessed against current implementation and related regressions/probes' : 'Retained after current-source delta review and complete web regression; exact teacher scenario not replayed'}));
const partials = cases.filter(row => row.previous_status === 'Partial');
if (partials.length !== 54 || partials.some(row => !updates.has(row.id))) throw new Error('Not all 54 original partials reassessed.');
const checklistUpdates = new Map([
    ['1.7.1', ['Aligned','Role-scoped platform/seller reports and analytics include seller and category filters/groupings with exports. Category grouping uses current product records; historical category snapshots are unavailable.','reports,sales14']],
    ['2.2.5', ['Aligned','Edit, archive and independent pause/resume work; existing order/media snapshots survive. Teacher unfinished-order deletion semantics are separately Different in the seller workbook.','catalog14']],
    ['2.4.1', ['Aligned','Seller financial screen/export/analytics reconcile net sales, discounts, refunds and recognized commission using one cents-based report contract. Gross/refund presentation and release policy differ in the stricter workbook.','sales14']],
    ['3.1.2', ['Aligned','Buyer profile/photo and owner-scoped multiple address CRUD/default selection work. Strict name alphabet, phone verification, address ceiling and default-delete rules remain separate workbook differences.','account14,address']],
    ['3.2.8', ['Aligned','Product wishlist and shop follow both persist, are owner scoped and require authentication.','wish14,follow']],
    ['3.3.3', ['Aligned','Seller/platform and shipping-target vouchers validate schedules, usage, spend and invalidity reasons with budget accounting. One voucher per checkout remains a stricter workbook stacking difference.','eligibility14,pricing']],
    ['3.3.5', ['Partial','COD and a funded internal demo wallet work; external e-wallet/card/bank providers are not integrated. Current local sandbox is enabled; credits are simulated.','demo14,pay14']],
    ['3.3.6', ['Partial','Failed demo payment preserves a retryable unpaid reservation and expiry/cancel releases stock/budget. Hosted gateway cancel/closed-browser/provider callback cases remain unimplemented.','pay14']],
    ['3.4.6', ['Partial','Product reviews now include photos, masked names, purchase variants and versioned edits. Separate seller/rider ratings and the strict teacher edit window remain absent.','review14']],
    ['5.1.1', ['Partial','Login/logout and a manual work-availability toggle exist; work-availability eligibility is staged OFF, and logout does not automatically mark unavailable.','available14,auth']],
    ['5.2.8', ['Aligned','Own delivery/transfer job history shows result, dates and recorded estimate/pending/verified settlement values; missing approved rate data is clearly unavailable. Fixed immediate wages are not promised.','courier14,compensation14']],
    ['1.7.3', ['Partial','Fresh hub queues/counts and seller filtered totals now reconcile. Full browser acceptance and every role dashboard/fixture metric were not replayed; these improvements do not prove all dashboard metrics.','queue14,sales14']],
    ['4.3.3', ['Partial','Delivery analytics and dedicated remittance CSV/XLSX reports exist with exact role/date/status/rider/hub filters. The remittance query has no seller filter; all per-seller report requirements are not established by this export alone.','remittance14,reports']],
]);
const checklist = oldChecklist.map(row => {
    const change = checklistUpdates.get(row.id);
    return {...row, ...(change ? {status:change[0], actual:change[1], evidence:change[2].split(',')} : {}), previous_status:row.status,
        assessment_date:'2026-11-14', teacher_checkbox_executed:false,
        review_basis:change ? 'Current source and applicable regression reassessed' : 'Retained after source delta/regression review; browser checkbox not executed'};
});
if (unresolved.length) throw new Error(`Resolve source anchors: ${JSON.stringify(unresolved)}`);
for (const row of [...cases, ...checklist]) for (const key of row.evidence) if (!evidence[key]) throw new Error(`Unknown evidence ${key}`);
for (const rows of [cases, checklist]) if (new Set(rows.map(x => x.id)).size !== rows.length) throw new Error('Duplicate criteria');
if (cases.length !== 319 || checklist.length !== 127) throw new Error('Incomplete source coverage');

const counts = rows => Object.fromEntries(Object.values(labels).map(status => [status, rows.filter(row => row.status === status).length]));
const roles = Object.fromEntries([['Seller','SEL'],['Buyer','BUY'],['Rider','RID'],['Logistics','LOG']].map(([role,prefix]) => [role, counts(cases.filter(row => row.id.startsWith(prefix + '-')))]));
const verifiedSources = [sources.checklist, ...sources.workbooks].map(source => {
    const file = path.join('C:/Users/Admin/Downloads', source.file);
    const actual = crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex').toUpperCase();
    if (actual !== source.sha256.toUpperCase()) throw new Error(`Source changed: ${source.file}; re-extract before auditing.`);
    return {file:source.file, sha256:actual, matches_13_November_snapshot:true};
});
const observations = fs.readFileSync(`${output}/probe-observations.jsonl`, 'utf8').trim().split(/\r?\n/).filter(Boolean).map(line => JSON.parse(line));
if (observations.length !== 8) throw new Error('Expected eight completed isolated probe observations; clear duplicate runs first.');
const checks = {laravel:{result:'passed',tests:723,assertions:6205,log:'storage/logs/teacher-reaudit-2026-11-14-final.xml'},
    frontend:{result:'passed',tests:63,log:'storage/logs/teacher-reaudit-frontend-2026-11-14.txt'},
    audit_probes:{result:'passed',tests:8,assertions:36,log:'storage/logs/teacher-reaudit-probes-2026-11-14.xml'},
    not_executed:['Literal 319 teacher workbook scenarios/fixture data','Browser/device acceptance','Live SMTP/SMS/provider payment delivery','MySQL timed races','Production deployment verification']};
for (const name of ['laravel', 'audit_probes']) {
    const check = checks[name];
    const xml = fs.readFileSync(check.log, 'utf8');
    const suite = xml.match(/<testsuite\s[^>]+>/)?.[0];
    for (const [attribute, expected] of Object.entries({tests:check.tests, assertions:check.assertions, errors:0, failures:0, skipped:0})) {
        if (!suite?.includes(`${attribute}="${expected}"`)) throw new Error(`Unexpected ${name} ${attribute}`);
    }
}
const summary = {date:'2026-11-14',scope:'Laravel web only; Flutter excluded',git_revision:revision,case_count:cases.length,checklist_count:checklist.length,
    cases:counts(cases),checklist:counts(checklist),roles,previous_partials:{total:54,current:counts(partials)},
    changed_case_assessments:updates.size,verification:checks,source_hashes:verifiedSources,
    caution:'Implementation alignment, not teacher Pass/Fail execution or a completion percentage. Historical 13 November evidence is retained separately.'};
write(`${output}/case-assessments.json`, cases);
write(`${output}/checklist-assessments.json`, checklist);
write(`${output}/audit-summary.json`, summary);
write(`${output}/source-verification.json`, verifiedSources);
write(`${output}/verification.json`, checks);
const md = text => String(text ?? '').replace(/\|/g, '\\|').replace(/\r?\n/g, '<br>');
const refs = row => row.evidence.map(key => {
    const item = evidence[key];
    return `[${key}](../../../${item.file}#L${item.line})`;
}).join(', ');
const preface = `Audited **14 November 2026**, commit \`${revision.slice(0,7)}\`. Laravel web only. **Implementation alignment, not execution of teacher Pass/Fail tests.** Full PHP/frontend regressions and eight isolated characterization probes passed. Historical findings were refreshed for the new implementations; unchanged rows retain their prior assessment after source delta/regression review. Exact teacher fixtures, browser/device acceptance, live email/payment delivery and MySQL races were not replayed.\n\n`;
for (const [role, prefix] of [['Seller','SEL'],['Buyer','BUY'],['Rider','RID'],['Logistics','LOG']]) {
    const rows = cases.filter(row => row.id.startsWith(prefix + '-'));
    let text = `# ${role}: teacher workbook re-audit\n\n${preface}| Criterion / source | Scenario / teacher expected result | Prior → current | Current implementation / difference | Current code |\n| --- | --- | --- | --- | --- |\n`;
    for (const row of rows) text += `| **${row.id}**<br>${md(row.sheet)}!I${row.row} | ${md(row.scenario)}<br><br>${md(row.expected)} | ${row.previous_status} → **${row.status}** | ${md(row.actual)} | ${refs(row)} |\n`;
    write(`${output}/${role.toLowerCase()}-test-case-audit.md`, text);
}
write(`${output}/role-checklist-audit.md`, `# Teacher role checklist re-audit\n\n${preface}All **127 numbered items** from the unchanged Word document. Workbook rules may be more specific than the broad checklist: an Aligned checklist capability can have Different exact workbook boundaries.\n\n| ID / Word paragraph | Teacher criterion | Prior → current | Current implementation / limitation | Code |\n| --- | --- | --- | --- | --- |\n` + checklist.map(row => `| **${row.id}** / ${row.paragraph} | ${md(row.text)} | ${row.previous_status} → **${row.status}** | ${md(row.actual)} | ${refs(row)} |`).join('\n') + '\n');
write(`${output}/previous-partials-recheck.md`, `# Independent recheck of the original 54 Partial cases\n\n${preface}All 54 have implementation revisions, but that is **not the same as 54 exact teacher expectations being met**. Classification considers the complete expected result, including retained policies and effective configuration. **${JSON.stringify(counts(partials))}**. An Aligned row covers implementation behavior with equivalent configured fixtures; it does not claim live browser execution of the original case.\n\n| Original Partial case / source | Teacher expected result | Reassessment | Observed current behavior / remaining difference | Code |\n| --- | --- | --- | --- | --- |\n` + partials.map(row => `| **${row.id}**<br>${md(row.sheet)}!I${row.row} | ${md(row.expected)} | **${row.status}** | ${md(row.actual)} | ${refs(row)} |`).join('\n') + '\n');
write(`${output}/evidence-index.md`, '# Current source and regression evidence\n\n' + preface + 'Named regression classes under tests/ were part of the fresh full run; they support their tested contracts, not literal teacher-case execution. The eight new TeacherReauditProbeTest characterizations deliberately confirm differences. Historical 13 November probes were not rerun unchanged, because fixed behavior would invalidate their old expectations. Other historical evidence labels below describe source checks rather than executed tests.\n\n| Key | Current source anchor | Related regression / probe |\n| --- | --- | --- |\n' + Object.entries(evidence).map(([key,item]) => `| ${key} | [${item.file}:${item.line}](../../../${item.file}#L${item.line}) | ${md(item.tests)} |`).join('\n') + '\n');

// Retain original assumption text/source cells, update notes affected by new code.
const assumptionChanges = {
    A3:['Partial','COD and internal-wallet reservation deduct stock at placement; cancel/expiry restore exactly once. Returns still require separate per-item restock inspection.'],
    A6:['Different','Buyer cutoff at Ready for Pickup remains; paid cancellation uses seller decision/24-hour timeout. Unpaid internal-wallet order expires after 30 minutes, not one hour.'],
    A10:['Different','True To Pay now exists for the demo wallet. READY_FOR_PICKUP is already To receive; physical hub/transfer states and reviewed corrections differ from the simple teacher lifecycle/override.'],
    A12:['Different','Completed item reviews now support five photos, purchase variants, masked names and versioned edit history. Text max1000, no30-day deadline/noonce-only edit; seller reply remains absent.'],
    A13:['Different','Three-attempt cap/refusal produce Return required context. Actual RTS still requires physical recovery/return actions, not an automatic stored status flip.'],
    A14:['Different','Manual work availability exists but enforcement is staged OFF. No nearest-rider cascade; offer15min, active delivery cap10 plus verified weight capacity, not60sec/3jobs.'],
    A16:['Different','Avatar/review photos support5MB and WebP; banner/product images remain2MB. Chat supports documents and2000chars; delivery proof4MB/PDF remains different from uniformJPG/PNG5MB.'],
    A18:['Aligned','Vacation blocks new purchase while retaining catalog; pending applicants can privately save incomplete drafts but cannot publish.'],
    A21:['Partial','Actual approved cancellation restores eligible funded usage/claims once, including exhausted budget restoration. Pending paid cancellation does not immediately restore it; expired/paused/underfunded campaigns stay unavailable.'],
    A27:['Partial','Ended offers are retained as unavailable history and cannot price new orders; existing order discounts stay frozen. No constrained post-claim voucher edit endpoint exists.'],
    A35:['Partial','Existing operational actions and separate reviewed corrections are audited with actors/reasons/before-after. Arbitrary override to Delivered remains deliberately unavailable.'],
    A36:['Partial','Dedicated remittance CSV/XLSX now reconcile with shared filtered screen totals, including empty ranges. Other financial statement exports remain mixed formats; not every statement is XLSX.'],
    A37:['Partial','New draft submission, wallet pay/expiry and withdrawal retries are protected. Normal product POST still duplicates in a fresh probe, so universal create/submit idempotency is not met.'],
};
let assumptions = fs.readFileSync(`${previous}/assumption-comparison.md`, 'utf8');
assumptions = assumptions.replace('# Teacher workbook assumptions compared', '# Teacher workbook assumptions rechecked, 14 November 2026');
assumptions = assumptions.split('\n').map(line => {
    const match = line.match(/^\| (A\d+)<br>/);
    if (!match || !assumptionChanges[match[1]]) return line;
    const cells = line.split(' | ');
    const [state,note] = assumptionChanges[match[1]];
    return `${cells[0]} | ${cells[1]} | **${state}** | ${note} |`;
}).join('\n');
write(`${output}/assumption-comparison.md`, assumptions + '\n\nCurrent runtime and residual differences are documented in [the re-audit](../../teacher-requirements-web-reaudit-2026-11-14.md). Assumptions remain comparison criteria, not authorization to change project policy.\n');
textSummary();
function textSummary() { process.stdout.write(JSON.stringify({cases:summary.cases, checklist:summary.checklist,original54:summary.previous_partials,updates:updates.size,roles:summary.roles,source_hashes_matched:verifiedSources.length},null,2)+'\n'); }
