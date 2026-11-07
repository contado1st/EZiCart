# All modules: revision and update audit

Audit date: **7 November 2026**. Scope: buyer, seller, rider, sorting center, marketplace administrator, logistics administrator, shared messaging/analytics, and buyer API integration.

The existing suite passes, but isolated checks reproduce workflow and reporting defects outside its current coverage. This report recommends revisions; application fixes have not been applied as part of this audit. Existing uncommitted cart/checkout work was preserved.

## Priorities

P1 means a broken customer operation or incorrect physical-custody workflow. P2 means a functional gap, misleading information, or operational backlog risk. P3 means tooling/maintenance work. These are revision priorities, not security vulnerability ratings.

| ID | Priority | Affected modules | Finding |
| --- | --- | --- | --- |
| F01 | P1 | Buyer API/mobile integration | Order details load a nonexistent relationship |
| F02 | P1 | Sorting center, rider | Origin-hub intake rejects parcels destined for another hub |
| F03 | P1 | Marketplace admin, buyer, seller, rider | Return approval immediately records physical transit |
| F04 | P1 | Logistics admin, sorting center, rider | Suspended transport drivers do not enter recovery handling |
| F05 | P2 | Rider, sorting center, messaging | Hub transport drivers lack destination contact and parcel access |
| F06 | P2 | Seller | Dashboard net sales omit seller-funded discounts |
| F07 | P2 | Seller | Dashboard rating is hard-coded to 5.0 |
| F08 | P2 | Rider, analytics | Transport-only assignments disappear from rider analytics |
| F09 | P2 | Buyer API/cart | Mixed-shop quote succeeds before purchase rejects it |
| F10 | P2 | Rider, logistics dispatch | Outgoing-load guard allows expired licenses |
| F11 | P2 | Logistics admin | Application queue hides older records beyond its first 25 |
| F12 | P3 | Logistics UI, developer tooling | Load-planning template contains invalid UTF-8 |

## Confirmed findings and acceptance criteria

### F01 — Buyer API order details fail

**Evidence:** [OrderApiController](../app/Http/Controllers/Api/OrderApiController.php), `show()` around line 71, requests `courier`. [Order](../app/Models/Order.php) instead defines pickup and delivery courier relationships. Calling the controller for an owned order reproduced `RelationNotFoundException`.

The same response also references absent `total_cents`, `subtotal_cents`, shipping/discount cent fields, and item `price`; actual order values use decimal amount fields and item `unit_price`/`item_total`. Tracking descriptions reference `description`/`remarks` rather than `notes`. These mappings need review after the relationship exception is repaired.

**Revision:** Map the response to current relationships, financial fields, item snapshots and event notes. Preserve ownership authorization. Avoid exposing internal exception messages in checkout API responses.

**Acceptance:** An authenticated buyer can retrieve their order with accurate totals, products, assigned riders and tracking notes; another buyer cannot retrieve it. Add response-contract coverage, including an unassigned order.

### F02 — Origin hub cannot receive an onward-transfer parcel

**Evidence:** [LogisticsController](../app/Http/Controllers/Logistics/LogisticsController.php), `receiveParcel()` around lines 449–473, and ownership rules in [OrderTransitionService](../app/Services/OrderTransitionService.php), around line 541. Receiving requires the hub to own the buyer's destination area. The isolated source-hub pickup scenario returned **403: “This parcel destination belongs to another sorting center.”** The order remained `PICKED_UP`.

Existing transfer fixtures that begin with a sorted parcel do not prove that upstream intake works.

**Revision:** Represent origin-hub intake separately from destination routing. Authorize intake through the assigned pickup/custody relationship, then require the existing transfer workflow for onward transport. Retain protection against unrelated hubs receiving parcels.

**Acceptance:** Complete seller pickup → origin intake → sorting → transfer assignment/release → destination receipt → local delivery, with scans and history. An unrelated hub must still be rejected.

### F03 — Return authorization is treated as physical transit

**Evidence:** [AdminDisputeController](../app/Http/Controllers/Admin/AdminDisputeController.php), `resolve()` around lines 36–64, transitions approved refund/replacement cases directly to `RETURN_IN_TRANSIT`. [OrderTransitionService](../app/Services/OrderTransitionService.php) reuses the prior delivery rider in assignment lifecycle handling; its outgoing vehicle checks do not include this transition.

The probe approved a return from a completed order: status became `RETURN_IN_TRANSIT`, an active parcel load was reserved, and the previous rider's vehicle was **not verified**. Refund status was `AUTHORIZED`; this does **not** establish that money was paid out.

**Revision:** Separate return authorization from return pickup, rider acceptance, verified capacity and physical possession. Record transit only after the appropriate custody evidence. Keep financial authorization separate from execution.

**Acceptance:** Approval alone does not imply collection. Actual return movement requires an eligible assigned rider, available capacity and pickup evidence; repeated actions remain safe and audited.

### F04 — Transport-driver suspension misses recovery exceptions

**Evidence:** [LogisticsExceptionService](../app/Services/LogisticsExceptionService.php), `openForRiderSuspension()` around lines 12–20, searches pickup/delivery rider fields but not `parcel_transfers.carrier_rider_id`. Suspending an assigned driver with an `IN_TRANSIT` transfer produced **zero recovery exceptions**. Existing planned-transfer cancellation/release controls do not provide an in-transit replacement-driver workflow.

**Revision:** Include transport custody when evaluating suspension. Provide a controlled recovery/reassignment process with replacement-driver eligibility, capacity, handover evidence and administrator history. Preserve the real parcel location rather than resetting its state.

**Acceptance:** Suspending a carrier with active transfer custody creates an actionable exception. An authorized operator can recover the parcel without losing history or creating simultaneous custody.

### F05 — Hub drivers lack destination chat and parcel evidence access

**Evidence:** [DirectMessagingService](../app/Services/DirectMessagingService.php), contacts and order authorization, and [Order](../app/Models/Order.php), `messageParticipants()` around line 172, derive participants from buyer/seller/local riders/current hub. Transfer carriers are omitted.

An active carrier could contact the source hub but not the destination hub. Order-linked authorization returned **403**. The load-planning view avoids exposing some unavailable links, but the assigned transport driver still lacks this operational context.

**Revision:** Include authorized transfer participants and source/destination contacts for the relevant job. Limit parcel details and conversation access to operational need, with an explicit policy for completed transfers.

**Acceptance:** Assigned carriers can contact both involved hubs and see the permitted parcel journey; unrelated riders remain excluded.

### F06 — Seller net sales are overstated when seller discounts apply

**Evidence:** [SellerController](../app/Http/Controllers/Seller/SellerController.php), lines 14–16, calculates fulfilled net sales as `subtotal - commission_fee`. Seller report/financial services account for seller-funded discounts.

With subtotal **100**, seller-funded discount **20** and commission **10**, the dashboard showed **90**, while recorded seller payable was **70**.

**Revision:** Reuse consistent financial definitions. Distinguish earned sales, refunds, seller payable and settled cash; account for platform-funded discounts separately.

**Acceptance:** Dashboard, seller report and ledger reconcile for discounted, platform-subsidized and refunded orders without labeling unsettled earnings as paid cash.

### F07 — Store rating is fictional

**Evidence:** [SellerController](../app/Http/Controllers/Seller/SellerController.php), line 23, supplies `'rating' => '5.0'`. The dashboard labels it as customer feedback. A store whose only review was **1 star** still displayed **5.0**.

**Revision:** Calculate the rating from eligible reviews and display the review count. Use an unrated state for a new shop.

**Acceptance:** One 1-star review displays 1.0; multiple products aggregate consistently; an empty review set does not display an invented rating.

### F08 — Rider analytics exclude hub transport

**Evidence:** [AnalyticsService](../app/Services/AnalyticsService.php), around lines 58–76, scopes rider orders and rider filters through pickup/delivery courier IDs. A carrier-only transfer assignment was absent from the result.

**Revision:** Include assigned transfer work and distinguish transport legs from customer deliveries. Report relevant parcel count, cargo weight, elapsed time and transfer status without counting linehaul as COD collection.

**Acceptance:** Transport-only riders see their own current/historical work; local-delivery totals are not duplicated and other riders' work remains private.

### F09 — API cart preview contradicts checkout restrictions

**Evidence:** [CartApiController](../app/Http/Controllers/Api/CartApiController.php), quote handling around lines 18–40, returns a successful quote for products from different sellers. [PlacePurchaseService](../app/Services/PlacePurchaseService.php) correctly rejects the subsequent mixed-shop purchase.

The probe returned **200 / success** for the quote, followed by the one-shop validation message at purchase. The server's purchase restriction works; preview consistency is the defect.

**Revision:** Apply the same one-shop eligibility rule before issuing a checkout quote and expose a clear selection error to API consumers. Preserve other shops' saved cart items.

**Acceptance:** Multiple items from one seller quote and purchase successfully. Mixed-shop selection is rejected during preview as well as placement; a direct request cannot bypass the restriction.

### F10 — License expiry is absent from the outgoing-load guard

**Evidence:** Registration/profile rules validate license dates, but [ParcelLoadService](../app/Services/ParcelLoadService.php), around lines 83–93, checks approval, duty state and verified capacity without rechecking `license_expires_at`. An approved rider with an expired license and verified vehicle passed `assertFits()`.

**Revision:** Define and enforce license validity at new assignment/dispatch, with renewal review and expiry reminders. Decide how legacy records without dates are handled explicitly. Keep safe receipt/unloading and recovery possible.

**Acceptance:** Expired or otherwise ineligible riders cannot receive new outgoing work; renewed approved details restore eligibility, while physically held parcels remain recoverable.

### F11 — Older logistics applications fall out of the approval page

**Evidence:** [LogisticsAdminController](../app/Http/Controllers/Admin/LogisticsAdminController.php), line 29, uses `latest()->limit(25)->get()` without application pagination. With **26 pending applications**, only 25 appeared and the oldest was missing. Records remain stored; the page lacks navigation to them. Open exceptions have a similar fixed limit worth reviewing.

**Revision:** Add searchable, paginated application review, full pending counts and an age indicator. Consider oldest-first priority rather than hiding older work behind new arrivals.

**Acceptance:** Every pending application is reachable through the UI, including record 26 and later pages; filtering and approval preserve the operator's place.

### F12 — Invalid UTF-8 blocks the UI source scan

**Evidence:** A strict encoding scan checked **412 source/document files** and found one invalid UTF-8 file: [load-planning.blade.php](../resources/views/logistics/load-planning.blade.php), line 9, byte offset 1072. The Impeccable layout detector stopped on this file.

**Revision:** Normalize the template to UTF-8 while preserving intended text, then rerun the source detector and browser checks. Do not blindly rewrite all files or mistake an aborted scan for a clean result.

**Acceptance:** The template decodes as UTF-8, renders its intended labels and allows a complete source scan. Actual responsive layout still needs visual verification.

## Coverage by module

| Module | Reviewed areas | Required revisions |
| --- | --- | --- |
| Buyer | Cart/checkout boundaries, order-detail API, return states | F01, F03, F09 |
| Seller | Dashboard earnings/rating, returns and pickup handoff | F03, F06, F07 |
| Rider | Pickup/transport/delivery identity, load checks, messaging, analytics | F02–F05, F08, F10 |
| Sorting center | Intake ownership, onward transfer, driver recovery, load-planning source | F02, F04, F05, F10, F12 |
| Marketplace admin | Dispute approval and its custody/financial consequences | F03 |
| Logistics admin | Applications, rider suspension, dispatch eligibility | F04, F10, F11 |

This is a risk-focused audit, not proof that every state, route, browser size or concurrency scenario is defect-free.

## Additional improvements to consider

These are recommendations from inspection, not additional reproduced defects:

- **Buyer support:** Provide an explicit escalation route for parcels stuck before delivery. Dispute creation currently requires delivered/completed states even though “Non-Delivery” exists as a reason. Agree the policy before automating any refund.
- **Pickup scheduling:** Allow an audited reschedule before rider acceptance, with notifications and protection against changing an accepted physical handoff silently.
- **Administrator staff lists:** Move all-record loading and PHP filtering toward scoped database searches and pagination as the user base grows.
- **Review submissions:** Exercise concurrent duplicate submission; convert unique-constraint collisions into a useful response rather than an unhandled failure.
- **Operational health:** Add visible queue/scheduler health and failure monitoring. Listing scheduled commands does not prove their worker or scheduler is running.
- **Visual QA:** Check table actions, frame spacing, keyboard navigation, confirmation dialogs, reduced motion and loading transitions on mobile and desktop once browser access is available. No accessibility compliance score is assigned without those checks.

## Verification and limits

| Check | Result | Evidence / limit |
| --- | --- | --- |
| `php artisan test --compact` | **Passed: 342 tests, 3,136 assertions** | Approximately 407 seconds; result in `storage/logs/modules-audit-tests.json` |
| Isolated controller/service probes | **Completed; defects reproduced** | `php scripts/audit-module-revision-probes.php`; result captured in `storage/logs/module-revision-probes.json` |
| Route inventory | **Passed** | `php artisan route:list --except-vendor --json`; six modules and shared/API routes enumerated |
| Schedule inventory | **Passed** | `php artisan schedule:list --no-interaction`; pruning, seller deadlines, aging alerts, commerce automation and promotions configured |
| Strict UTF-8 scan | **Completed: one invalid file / 412 checked** | `storage/logs/modules-encoding-audit.json` |
| Layout source detector | **Blocked/incomplete** | Invalid UTF-8 in load-planning template; no clean layout result claimed |
| Authenticated browser / responsive review | **Unavailable** | Browser/native integration unavailable in this session |
| Live SMTP, queue processing, multi-tab login, physical delivery | **Not tested in this audit** | No claim of live delivery or production health |

The probe script verifies that its database is SQLite `:memory:` before migrating disposable fixtures. Notifications are faked and mail uses the array transport. It does not seed, reset or change the configured MySQL application data. These direct controller/service checks do not replace HTTP middleware/authorization tests or a real browser walkthrough. Diagnostic JSON files are local artifacts; the reproducible script and this report are the durable audit outputs.

## Recommended implementation order

1. Repair buyer API order details, origin-hub routing and return custody separation (F01–F03). Add end-to-end regression coverage for each corrected path.
2. Complete transport-driver integration: suspension recovery, participant access and analytics (F04, F05, F08), then enforce outgoing license eligibility (F10).
3. Reconcile seller metrics and ratings (F06–F07), align API quote restrictions (F09), and paginate approvals (F11).
4. Normalize the affected template (F12), rerun source checks and conduct authenticated responsive/keyboard review across all six modules.

Avoid a broad redesign or state/schema rewrite before these specific behavior gaps have defined regression checks.
