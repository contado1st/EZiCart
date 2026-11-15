# Web operations user guide

See the [all-module revision audit](all-modules-revision-audit.md) for implemented revisions, original findings, verification results and remaining limits. The [implementation plan](module-revision-implementation-plan.md) records the design sequence prepared before application changes.

## Project location and local commands

The repository contains `EZiCart-web` (Laravel) and `ezicart_buyer_mobile` (Flutter). Run PHP, Composer and npm commands from `EZiCart-web`. Use the installed dependencies and the existing `.env` in that folder. Opening `EZiCart.code-workspace` in VS Code gives each application its own project root. Do not edit generated PHP files under `storage/framework/views`; edit their Blade source under `resources/views`.

## Dashboard sections and numbered pages

Use the section tabs below each dashboard's summary to open the work you need. Rider queues separate available pickups, accepted pickups, hub handoffs, deliveries, returns and failed attempts; **My rider badge** opens the handoff QR without scrolling through the work tables. Hub tabs separate arrivals, sorting, dispatch, failed deliveries, exceptions and the lifecycle guide. The next actionable rider/hub queue opens by default unless you follow a direct link or choose a different tab.

Seller and marketplace-admin tabs separate orders from tools or approvals. Logistics administrators can jump directly to parcels, applications, service areas, hubs/riders or exceptions. Buyers can choose **All orders**, **Preparing to ship**, **To receive** or **Closed orders**; these filter the full purchase history, including records on later pages. Finance, storage and routing-area pages also separate their records from other tasks.

Operational tables show 25 records per page, with existing 20–30 record limits retained where already appropriate. Use the page numbers at the bottom; the range tells you how many matching records exist. Paging keeps your filters and chosen dashboard section. Queue badges show the full matching total, not just the displayed page. Routing-area dropdowns still list every eligible choice even when the table has multiple pages.

Use Left/Right arrows, Home or End while a dashboard tab is focused. On narrow screens, scroll the tab strip horizontally. Your chosen section stays in the URL so reload and browser Back can restore it. Existing workflow links open the relevant panel automatically. Without JavaScript, all panels remain readable. Browser printing includes the currently loaded records from every panel; use the existing report exports for a complete report across all pages.

See the [dashboard navigation assessment and implementation plan](dashboard-navigation-revision-plan.md) for coverage and verification limits.

## Saved cart and selected checkout

Use **Add to cart** to save products to your buyer account, including after logout. In the cart, select several products from **one shop** or use that shop's checkbox before choosing **Check out**. Selecting another shop switches your selection. Only selected products from that shop are purchased; everything else stays saved. Mixed-shop checkout is blocked on the server. **Buy now** checks out one product/variation separately, and **Cancel Buy Now** returns to that product without adding it to the cart. Checkout supports saved addresses, eligible vouchers and COD.

See the [cart and checkout revision audit](cart-checkout-revision-audit.md) for implementation details, verification results and the buyer walkthrough.

## Parcel progress, notifications and required actions

- Dashboard pages have a **Next steps** panel. It lists your current tasks and links to the relevant order, hub queue or administrator review page. Buyer, seller, rider, sorting hub and both administrator modules receive tasks within their own permissions.
- Order detail pages and **Journey & evidence** show the parcel stages, who acts next, and whether you need to act or wait. Pickup distinguishes scheduling, rider assignment/acceptance, arrival, seller handover and the rider's possession scan.
- Action counts and order progress refresh every 15 seconds while the tab is visible, and when it regains focus. Relevant action buttons get a badge from the current task state. Completing an action clears its badge; reading a notification does not clear an unfinished task.
- When another participant changes the workflow, the progress message updates. Select **Workflow updated: refresh to load current controls** to reload operational forms. The page does not automatically discard unsaved input.
- Pickup scheduling, assignment, acceptance, arrival, handover, collection, hub release and delivery notifications explain the next step. Notification text records the step at the time of the update; open the order to see its current state. Cancellation holds, hub transfers, failed deliveries, returns and disputes have separate guidance rather than encouraging an invalid fulfillment action.
- Network failures preserve the last displayed counts and show a retry message. **Refresh next steps** retries immediately. No new websocket server or background process is required for the on-page indicators; the existing queue worker still handles queued notification channels.

## Rider vehicle choices

Vehicle choices include motorcycles/scooters (2 wheels), tricycles (3 wheels), and cars, vans, pickup trucks and light trucks (4 wheels). The vehicle type determines its wheel category. New riders use this catalog, and subsequent profile/vehicle changes still require logistics administrator approval. Existing stored vehicle labels remain readable. The shared catalog is `config/rider-vehicles.php`.

## Profile changes and administrator review

1. Sellers, riders and sorting-center staff open **Account & profile**. Submit contact/address/business or applicable vehicle/license changes with **Request profile update**, or photos, banners and display information with **Request appearance update**.
2. Verified details and images stay active until approval. Only one request may be pending per account; wait for its decision before submitting another form.
3. Marketplace administrators review seller requests. Logistics administrators review rider and sorting-center requests. Each administrator opens **Profile change approvals** in the sidebar or the in-app notification.
4. The dedicated table compares current and requested values, including private photo/banner previews. Approve to apply the values, or reject with a reason. Use the status filter to view earlier decisions.
5. Users can see their own request history and rejection reasons on **Account & profile**. Existing coverage vehicle proposals use this same review gate. Login email remains read-only and password changes keep their existing security flow.

**Staff profiles** pages are administrator-only and limited to the accounts each administrator manages. Buyers, sellers, riders and sorting centers cannot open these pages, including by typing their URLs. Parcel journeys still show the assigned rider's name without linking non-admin users to a restricted staff page.

## Parcel journey, QR and event photos

1. Open **Journey & evidence** or the link on an order's detail page.
2. Scan/paste an `EZP:` parcel reference or enter the order number. Viewing a reference does not change parcel status.
3. Read the current stage, responsible rider/hub, next required action, tracking transitions and accepted/rejected scan history.
4. Perform the existing authorized receiving/sorting/release/delivery action in its normal module. The existing transition service remains authoritative.
5. The person who recorded an accepted scan or tracking event can attach up to three photos to that event. Re-uploading identical bytes to the same event does not create another photo.
6. Photos are private. Only involved parcel participants and relevant administrators can read them. Uploads cannot attach to another person's event or a rejected scan.
7. Final delivery retains the existing buyer-code, COD and mandatory delivery-proof workflow. Recorded delivery attempts and proof downloads appear in the journey view.

Historical scan rows that did not record a status retain their original data. Evidence derives the status from the last recorded tracking event at that time; it does not invent historical transitions. Photos are optional evidence for intermediate events, attached after the event is recorded.

Web QR scanning supports built-in laptop cameras and connected desktop/USB webcams. Open the website over HTTPS (or localhost for local testing), choose **Use camera** / **Start QR camera**, and allow camera access when your browser asks. After access is granted, a **Camera** selector appears when more than one camera is available. Hold the entire parcel or rider-badge QR code in view. QR frames are decoded locally, including on browsers without built-in QR detection; only the captured reference is submitted through the existing verification form. Batch scanning adds each unique parcel reference once and still requires manifest confirmation. Closing the scanner, switching away from the page, or submitting its form releases the webcam. If access is blocked, allow this site in browser/device camera settings; if the camera is busy, close other apps using it. Scanner-gun and manual-entry options remain available.

## Vouchers

1. A seller creates a voucher with its existing discount/total redemption settings plus **Maximum unique buyers** and **Uses per buyer**.
2. The seller sees claimed recipients, maximum recipients and remaining recipients. These counts are separate from total redemptions.
3. Buyers open **My vouchers**, claim an available offer, then use its code at checkout. Entering an available code in web checkout also saves the claim after pricing validation.
4. A repeated claim by the same buyer reuses the existing record. Another buyer cannot claim the last reserved slot. Limits are checked again during the locked purchase transaction.
5. A successful checkout increments redemption counters once. Replaying its checkout token returns the same purchase without another claim, redemption or stock deduction.
6. Sellers can increase/change limits and expiry from **Edit recipient limits**. They cannot lower recipient limits below existing claims or per-buyer usage below already-recorded redemptions.
7. Removing a claimed voucher disables it and preserves its history.

Legacy vouchers keep their existing unlimited-per-buyer behavior when the new limit is null. New seller vouchers default to one use per buyer. Recipient claims and individual uses are intentionally different counters.

When an older voucher first uses the new claim ledger or has its limits edited, its recorded historical orders initialize buyer/redemption history under the voucher lock. Multiple seller orders from one purchase count as one redemption. Old voucher codes reused by a later voucher are separated by the voucher's creation date.

## Private chat attachments

Use the attachment control in the existing full inbox or right-hand message panel. Messages may contain text, files, or both. Up to three files can be sent at once. Supported extensions are JPG/JPEG, PNG, WebP, PDF, DOC/DOCX, XLS/XLSX and TXT; both the extension and detected content type are validated.

The composer shows filenames, sizes, image previews and sending feedback. Attachment history remains within the private two-person conversation. Downloads and image previews require a participant's authenticated session.

The displayed file limit follows the smaller of the configured 5 MB ceiling and the running PHP upload limit. The existing `scripts/serve-media.ps1` launcher can run the development server with the larger upload/post limits already needed for product videos. Changing PHP limits requires restarting that server. No PHP configuration was changed automatically.

## Seller promotions

1. The seller opens **Promotions**, selects an optional approved product or active voucher, writes a title/message and sets the start/expiry times.
2. One campaign can be created per seller per day. Eligible recipients are approved buyers following that shop who permit promotions.
3. The existing Laravel scheduler runs `commerce:deliver-promotions` every five minutes. Ensure the normal scheduler process (`php artisan schedule:work` in local development) is running.
4. Each campaign reaches a buyer at most once; each buyer receives at most three seller promotions over a rolling seven-day period.
5. Buyers find these under **Shop promotions** in Notifications, clearly labeled **ADVERTISEMENT**, and can switch promotions off in that sidebar.
6. Sellers and marketplace administrators can stop future campaign deliveries. Expired/stopped promotions do not open stale advertising links.

The feature sends database/in-app notifications only. It uses the existing text notification presentation; no new promotional banner-upload or external email/SMS/push channel is added.

## Activity and shop availability

Activity indicators use successful approved-account web login and meaningful page/action requests. Writes are throttled to once per five minutes. Background counters, embedded GET refreshes and private image/download requests do not keep an account active. Sending a message is activity. “Active recently” is an approximation, not a guarantee of a live connection.

Seller shop/product pages display availability and last recorded activity. A configurable warning starts after 14 days of inactivity; accounts without a recorded timestamp are labeled accordingly rather than falsely marked inactive.

Sellers open **Availability** to select Open, Temporarily closed, On vacation or Temporarily unavailable. An optional future start schedules the pause; an expected return time automatically makes the shop open again once that time passes. New purchases are checked on the server, including final checkout. Existing orders continue through their established fulfillment process.

## Product categories and variations

The twelve requested category choices come from `config/web-commerce.php`. Seller forms, shop settings, marketplace browsing, search and analytics use that catalog. Known legacy category aliases are recognized by filters and display labels. Existing records are not deleted or mass-renamed. Ambiguous/unmapped categories such as legacy **Fashion** remain discoverable and editable on their existing products.

In the seller product form, enter variation rows or use **Generate combinations** with two named attributes and comma-separated options. Each actual combination has its own optional SKU, price adjustment, stock and optional image. At most 100 combinations are allowed. The displayed parent stock is their sum. Effective prices must remain positive and combinations/SKUs must be unique within the submitted product configuration.

Buyers choose an actual combination on the product page. Price, stock limit and optional image update with the selection; unavailable choices are disabled. Cart/checkout still validate product ownership of the variation, its active state and inventory on the server. Purchased variation identifiers/details remain in orders. Removed options are retired, preserving order and stock-restoration history. Retiring every option leaves no purchasable variation until an active option is added again.

## Separate browser-tab accounts

Use **Account → Sign in another account** in the dashboard sidebar. It opens a fresh login tab without signing out the current account. Repeat for buyer, seller, rider, sorting center and either admin. You can also open a new tab and enter the application's login URL directly.

Refresh and ordinary navigation retain the account assigned to that tab. Duplicating a tab or opening a copied account URL creates a separate login context when the original is open. Pasting a different account's URL into an existing tab retains that tab's own account. Embedded messages inherit the parent tab's account. Logging out affects only the selected tab's session.

After installing this fix, reload existing tabs once so they load the new session script. Use a current browser with JavaScript, session storage, and Web Locks (HTTPS/localhost) or BroadcastChannel enabled. A browser-wide legacy “remember me” cookie is ignored in isolated tabs.

The tab middleware now clears a cached session-store binding and authentication guard after selecting a tab cookie, preventing identity reuse when a Laravel application container handles multiple requests. Existing CSRF, session regeneration, approval checks and server role restrictions remain in place.

**Domain/device troubleshooting:** “Opening your tab’s account” is a brief initialization page, not an error by itself. If it stays visible, verify that `/js/tab-sessions.js` loads from the website's own origin. Leave `ASSET_URL` blank when assets should follow the requested host; pointing it at `localhost` breaks access from other devices. The critical session script uses a same-origin path independently of that override. Tunnel HTTPS headers are handled through `config/trustedproxy.php`, after environment configuration loads. Local mode defaults to loopback proxy addresses; other deployments must configure their actual trusted proxy IPs/CIDRs through `TRUSTED_PROXIES`. Clear configuration with `php artisan config:clear` after changing these settings, then refresh the affected device. Do not disable tab isolation to work around an asset or proxy configuration issue.

## Mobile boundary

The combined branch includes both web and mobile work. This audit-driven revision does not edit Flutter/Dart files or add native screens. It does correct buyer API order values and enforce the existing one-shop rule during API quote preview; therefore API controllers are not described as untouched. Existing response field names are preserved. Shared purchase services continue to enforce recipient/per-buyer caps, shop closures and one-shop checkout for every caller.

The native category endpoint keeps its current options. Private SKU and seller activity fields remain hidden from product responses. Public variation `image_path` is intentionally available to the existing mobile image parser; it is not a private document path. Order details now return the recorded discount, purchased unit/line totals, purchased product name and tracking notes rather than stale or missing property names.

## Parcel weight and vehicle load planning

Weights are entered in kilograms with up to three decimal places and stored as integer grams. Product weight is per unit. Checkout saves the unit weight on each order item, so later product edits do not change an existing order's estimate. The actual packed parcel weight is a separate scale measurement and must include packaging; it is never invented from the estimate.

1. **Seller:** enter the unit weight when creating or editing a product. Prepare the order, open its parcel journey, and use **Record packed parcel weight** with a measurement note before requesting pickup.
2. **Logistics admin:** open **Staff profiles**, choose the approved rider, and verify their current vehicle's maximum cargo capacity in kg with a verification note. Use its documented cargo allowance, not a guessed limit based on wheel count. Approving a different vehicle type or plate clears the old verification.
3. **Seller, rider, hub or logistics admin:** open **Weight & load planning** in the sidebar. Review parcel weights, vehicle limits, combined reservations, remaining capacity and blocked loads before arranging transport.
4. **Hub:** assign a vehicle with sufficient remaining capacity. Assigned pickups, delivery/return loads and in-transit hub transfers share its capacity. Assignments reserve space before physical loading; the same parcel is counted once. Unloading, completing a handoff or releasing an assignment removes its applicable reservation.
5. **Seller and rider:** complete the normal arrival, handover and parcel scan steps. Weight and capacity are checked again before pickup. **Hub and delivery rider:** use the normal release scans and delivery-start action; these check the current load again, including changes made after assignment.
6. **Hub transfers:** the source hub must select a registered driver belonging to that hub. The driver needs verified capacity for the combined transfer and other reserved loads. Destination receipt releases that transport reservation.
7. **Reweighing:** sellers can correct a packed weight before pickup; the current hub can reweigh before release when no hub transfer is active. Each measurement is audited and relevant operators receive an in-app notification. A truthful measurement is retained even when it reveals an overload; reassign or reduce the load before moving it. Refresh the planning board after changes.

Missing parcel weights, unverified vehicle capacity, unknown weights on other reserved parcels, and combined loads above the limit block assignment, pickup and dispatch. Existing records remain unknown after migration: weigh old parcels and verify current vehicles before continuing their outgoing movements. Receiving/unloading existing parcels remains available. No shipping-price calculation or fabricated historical weights were added.

## Dedicated hub transport demo

Logistics administrators select a rider's **Driver duty** in **Staff profiles** while verifying capacity. Dedicated hub-to-hub drivers are excluded from local pickup and delivery assignments. They sign in to **Assigned hub transfers**, see their own transport jobs and destination details, and present parcels for destination-hub receipt. Hub staff remain responsible for the release and receiving scans.

See the [hub-to-hub flow audit and demo guide](hub-to-hub-flow-audit.md) for the seeded accounts, two ready-to-run parcels, exact steps, safety checks, test results and a rollback-only local database checker.

## Origin hub to destination hub

1. The assigned pickup rider collects the parcel using the existing arrival, seller handover and possession scan steps.
2. The rider's assigned origin hub opens **Intake** and records physical receipt, even when another hub owns the buyer's destination area. An unrelated hub cannot claim the parcel.
3. The origin hub sorts to the actual destination area. This does not transfer ownership of that area's service mapping.
4. A logistics admin plans the hub transfer. The source hub selects a verified dedicated transport driver and scans the parcel for release.
5. The destination hub scans physical receipt, sorts locally, and assigns a local delivery rider. Origin intake does not permit final delivery dispatch into another hub's area.
6. The destination rider accepts, receives the scanned hub release, starts delivery, records COD and proof with the buyer's delivery code, and the buyer confirms receipt.

## Approved returns and physical collection

**Approved return pickups** is available in dashboard navigation. Buyers, sellers and riders see only their related orders; hubs see their own parcels, and relevant administrators can review the process.

1. A marketplace admin approves refund/replacement through the dispute page. The parcel stays delivered/completed while awaiting collection. Refund authorization does not send money, reserve the former rider or prove physical movement.
2. The current hub or logistics admin opens **Approved return pickups** and assigns an eligible local rider from the parcel's destination hub. Recorded parcel weight, verified vehicle capacity, duty and license eligibility are checked. The assignment reserves capacity, while custody remains with the buyer.
3. The assigned rider opens the same page and accepts. An accepted pickup cannot be silently reassigned from that page.
4. When physically giving the parcel to that rider, the buyer selects **Confirm physical handover to rider**. Confirm only at the actual handover.
5. The rider scans/enters the parcel's `EZP:` label using **Confirm scanned possession**. Acceptance, buyer confirmation, the correct label and current vehicle checks are required. Only then does the order become `RETURN_IN_TRANSIT`.
6. Continue the existing seller return process: the rider records physical handover, then the seller scans that rider's badge to confirm receipt. Return inspection and authorized financial execution remain separate actions.

Workflow messages, required-action counts and in-app notifications explain each step. Pending return riders can contact the related buyer/seller through private messaging. Approval notifications link to the return page rather than implying that collection already occurred.

## Suspended transport driver recovery

An in-transit driver's suspension opens a recovery exception and alerts operations. The transfer remains in transit; its history and original destination are preserved.

1. The logistics admin opens **Hub transfers** and finds the suspended driver's job.
2. Arrange a real physical handover to a different approved source-hub transport driver with sufficient verified vehicle capacity and valid recorded license.
3. Select that driver, scan the parcel label at handover and record the recovery details. **Record replacement handover** changes custody, transfers the reservation and resolves the associated exception. A wrong scan or ineligible driver is rejected.
4. The replacement continues to the original destination. Destination receipt still requires its own scan and releases the new reservation.

The transfer page links to **Journey & evidence** and **Messages**. Recorded transfer carriers and involved hubs can contact each other and view related evidence; this grants no access to unrelated parcels. The analytics transport section reports assigned legs, hubs, cargo and transit timestamps separately from customer delivery and COD work.

## License expiry and renewals

A recorded license expiry blocks new assignment, pickup or dispatch starting the day after expiry. The expiry date itself is valid through that day in the application timezone. Existing receipt/unloading operations remain available so parcels are not stranded in a vehicle.

Vehicle displays warn about expired licenses and renewals due within 30 days. Logistics administrators also receive a required-review task. Submit renewed license details through the existing profile-change approval process; do not merely change the visible page text. Legacy records without expiry dates are labeled for review and are not given invented dates; their existing approved status and verified-capacity requirement continue to apply until corrected.

## Seller figures and logistics review queues

The seller dashboard displays actual customer review averages/counts and **Unrated** for an empty review set. **Accrued seller earnings** deducts seller-funded discounts and commission, uses recorded finance snapshots where present and accounts for executed refunds. It is neither profit nor settled cash. Analytics net sales has its documented completion/remittance recognition timing; use financial settlement records to establish paid cash.

Logistics administrators can search applications by name, business or email. Results are oldest-first, display waiting dates, show matching totals and provide page links. Filtering starts a fresh result list; pagination retains the current query. Open recovery exceptions also paginate, so older work is not silently hidden beyond 25 records.

## Wallet, funded promotions and remittance batches

Open **Wallet and balances** (or **Platform treasury** for e-commerce administrators) for available, pending, promotion reservations, withdrawal holds and transaction statements. Funds are sandbox accounting credits, not live bank/e-money deposits. Explicit local/testing enablement and a signed mock-provider event are required for funding. Never treat a browser redirect as payment confirmation.

New seller/platform vouchers require a finite redemption limit and sufficient available sponsor funds for maximum liability. Percentage offers also require a maximum discount. Creation reports the required budget, available balance and shortfall. Pausing or expiry releases unused funds; redeemed amounts remain traceable. Platform promotions use shared treasury, not seller funds.

Buyer checkout supports COD or a full sandbox wallet payment. Wallet orders pass the existing scanning, capacity, delivery-code and proof controls with no COD collection. Seller proceeds remain pending until existing completion, seven-day hold, funding/dispute and payout approval checks pass. Personal withdrawals require the responsible administrator's review; sandbox execution does not send actual money.

Riders use **Cash remittance batches** to submit unsubmitted collections to their receiving hub. The hub records physically counted cash and receipt evidence. Exact cash verifies; shortages/excesses require logistics-admin investigation and evidenced reconciliation. Discrepancies do not automatically reduce rider earnings. Do not submit the same collection through both individual and batch remittance.

Logistics administrators use **Delivery tariffs** for effective-dated drafts and independent approval. Activation requires approved ordinary-route coverage. **Shipping breakdowns** retains original buyer/seller/platform funding and verified cost allocations. Hub measurements need evidence and treasury approval for additional funding; original buyer payment remains unchanged. Tariff examples are fictional training data until replaced with approved operating costs.

See the [financial implementation audit and sandbox walkthrough](wallet-remittance-delivery-fee-implementation-audit.md) for setup commands, role responsibilities, money formulas, evidence requirements, statement exports and limitations. The [research](wallet-remittance-shipping-research.md) records external sources; it does not assert an industry-wide rider/hub percentage split.

## Buyer account verification revision — 13 November 2026

Open **My Account → My profile → Verify new email**. Enter the proposed email and your current password, send the code, then enter the six-digit code received at the new address. Your existing email remains the login until successful verification. Codes expire after ten minutes and five incorrect attempts; wait one minute before resending. Resending replaces the pending challenge. An address already used by any role cannot be selected. Successful verification signs other web sessions for the same buyer out; tabs signed into other accounts remain independent.

First and last names require two or more characters, and contacts require a Philippine `09XXXXXXXXX` or `+639XXXXXXXXX` mobile number. New/changed profile locations and saved delivery addresses need a street or clear location description; a house number is optional. Unchanged legacy locations can be retained during an unrelated profile edit. Seller/rider official updates still require their administrator's approval. Password changes must use a different password from the current one.

Profile avatars support up to 5 MB at the application level; banners retain 2 MB. Existing dimension and safe-image rules remain. If the web process uses PHP's default 2 MB limit, larger avatars require its upload limits to be increased/restarted; the existing `scripts/serve-media.ps1` launcher supplies the limits already used for product media. See the [first-package implementation audit](partial-features-implementation-audit.md) for verification and remaining phases.

## Seller catalog revision — 13 November 2026

In **Inventory → Add New Product**, choose **Save private draft** to keep incomplete work and its media. Open **Inventory → Private drafts** to continue or delete saved work. Draft files are available only to their owner. Complete the required fields and choose **Submit for compliance review** to create the listing. Submitting again does not duplicate that draft's product. The normal inventory workspace requires an approved seller; pending applicants have the restricted workspace described below.

Use **Pause sales / Resume sales** in Inventory to temporarily stop or restart new purchases. Existing orders continue. Paused items remain in buyers' saved carts with an unavailable message. Resuming sales does not bypass compliance or restore an archived listing. Use Edit for changes to a submitted product; protected detail/media changes still require re-review.

Variant SKUs must be unique within your whole store, including retired variants. Case and surrounding spaces do not create a new SKU. Other stores may use the same code. Buyer pages show the available variant price range and switch to the exact price when an option is selected. Order detail pages retain the image recorded for new purchases even after the live listing's photo changes; older orders without a snapshot are labeled accordingly.

## Pending seller preparation — 14 November 2026

After verifying your email, sign in with your pending seller account to open **Private drafts**. You may save incomplete product details/media, continue your own drafts and delete unwanted work. This restricted workspace expires after 30 minutes; saved work remains. Sign in again when it expires. Your account cannot publish products, use operational pages or access messages/orders until approved.

After an administrator approves the application, sign in again to use **Inventory → Private drafts**, finish the saved product and submit it for compliance review. Approval does not automatically publish your drafts. Password/security changes or suspension revoke the restricted workspace. Use the existing logout control before handing the tab to someone else; other accounts' tab sessions remain separate.

## Scheduled vouchers and cancellation feedback — 14 November 2026

Sellers open **Vouchers**; e-commerce administrators open their platform voucher page. Choose an optional start date/time and later expiry using the application's displayed timezone. Set a finite total redemption limit; uses per buyer cannot exceed it. Percentage offers require a maximum discount. The budget preview shows maximum required funding, available sponsor funds and shortfall. Funding is reserved immediately, even for a future start. Frozen or insufficient funds prevent creation; the server makes the final decision. Once claimed, an offer cannot be rescheduled.

Buyers open **Vouchers → Available and upcoming** to see offers and start times. **Saved vouchers and history** retains your previously claimed offers, including ended/expired entries. These history entries do not imply the offer can still be used. Checkout explains minimum-spend shortfall, scheduling, expiry and redemption/funding restrictions.

Ending an offer stops future use and returns unused reserved funds; existing order discounts remain recorded. While cancellation is pending, stock and voucher use remain reserved. Approval releases eligible stock/use once and returns any recorded supported sandbox wallet payment. An uncollected COD payment has no refund. Check saved vouchers afterwards: expired or manually ended offers stay unavailable, and returned discount funds must cover all remaining capped liability before an exhausted funded offer automatically resumes. The original sponsor may need to explicitly reactivate the offer and replenish its reserve; cancellation never silently takes more sponsor money.

See the [updated implementation audit](partial-features-implementation-audit.md) for passing automated checks, preserved teacher differences, browser verification limits and the remaining reporting/rider/logistics packages.

## Seller order filters and financial reconciliation — 14 November 2026

In **Orders**, search an order number, buyer name or recipient name and optionally choose placement dates and status. **In Packing** includes confirmed and preparing orders. Tab counts use the same search/date filters; selecting a tab retains them. Use **Reset** to clear the filters. Lists show 25 orders per page.

In **Sales & Financial Report**, select placement dates. Summary cards cover all matching orders, while the table shows 25 per page. **Export CSV** and **Download PDF** retain the date cohort; **Print this page** prints the displayed rows and full-cohort summary with that scope stated. The itemized report and analytics use the same sales arithmetic. Different date bases or filters will produce different cohorts.

Gross merchandise activity retains completed/delivered-refunded activity. Net seller sales deducts seller-funded discounts, executed merchandise refunds, platform subsidy reversals and recognized commission. Fully refunded, cancelled or void records contribute no eligible completed-sale count; partial refunds retain the completed count. A refund authorization is not an executed refund. Sales figures are not profit or available wallet cash. Pending/settled payout amounts show recorded requests separately and do not change historical order prices.

Use **Review order payout eligibility** for an order's payout request; completion, the seven-day delivery hold, verified funds, disputes and administrator checks remain. Repeating that order request uses its existing record. After settlement to your wallet, use **Wallet → Withdrawals** to reserve available funds for a withdrawal request. Correcting a validation error retains the same request key and entered details. A repeated request with that key does not reserve twice; changing its amount requires a new request. Withdrawals remain sandbox accounting operations with separate administrator review/execution.

## Purchase groups, product discovery and wishlist — 14 November 2026

Open **My purchases** and choose **Preparing to ship**, **To receive**, **Completed**, **Cancelled** or **Returns & refunds**. Every order has one primary group. Open disputes/refund activity and return movement appear in Returns & refunds; a pending cancellation alone stays in preparation until approved. Cancelled history takes precedence over stale dispute records. Other historical statuses remain under **Other history** when present. Status filters apply to the counts and lists and remain when switching tabs; **Reset** clears them. Lists show 25 orders per page. Current COD and paid wallet purchases do not have a separate To Pay group.

Search still shows literal keyword results. If none match, **Did you mean** may offer up to three close available product titles; choose one to search again with your current filters. Suggestions are bounded and may not cover every large-catalog title. **Best selling** in search and shop sorting ranks units on completed orders without executed refunds, with stable product-ID tie breaking. Cancelled, void and refunded orders do not boost this ranking. This is separate from financial sales/partial-refund reports.

On a product, select the heart **Save to wishlist**. Open **My wishlist** from buyer navigation to review or remove saved products; 25 appear per page. Saving is private to your account, survives logout, and does not add items to the cart or reserve price/stock. Choose options and check out through the normal product/cart workflow. Unavailable entries remain removable but cannot bypass product visibility or checkout restrictions. Newly private product details are not displayed there.

Guest **Sign in to save** and **Sign in to follow** return to that product/shop after login. Select the save/follow button again to confirm; login or opening a link alone does not write a wishlist/follow entry. Product saving and following an entire shop remain separate features. An unapproved/suspended seller's products are hidden from marketplace discovery and direct public access, while existing order history remains available to its participants.

## Review photos, editing and seller feedback — 14 November 2026

After confirming receipt, open **My purchases** or the completed order detail page. Choose stars and optional feedback, attach up to five JPG/PNG/WebP photos, and select **Publish review**. Review photos support 5 MB each and at most 4096 × 4096 pixels at application level; the web host's upload/post limits must also permit them. Your public name is masked. Every applicable purchased variant appears under one review for that product/order; submitting again does not add another review.

Use **Edit review / private history** on the purchase to change stars/text, add photos or remove current ones. **Private history** shows recorded versions, 25 per page. A removed photo no longer appears publicly; it remains in private history. If another tab changed the review, refresh and check the current version before editing again. Existing feedback remains reachable after a return; new reviews still require completed purchases. Earlier changes made before history recording are not reconstructed. Rating aggregates use the current review once, rather than counting every edit.

Sellers open **Customer reviews** in navigation to filter by rating, text, photos and original posting dates. Results show 25 per page and include historical purchases of retired products. Seller access is read-only; it does not grant buyer editing/history permissions. Public product pages have a **With photos** review filter. Removed photos are unavailable to guest/seller viewers; the owning buyer and relevant commerce administrators retain access to the private retained files.

## Product questions in private chat — 14 November 2026

From a product, choose **Chat now**. The composer identifies the selected product; type your question and/or attach supported files, then send. Opening or reopening the conversation alone sends nothing. The seller receives a product card with the title, advertised price and product image reference, your text and any uploaded attachments. These are enquiry details, not a binding checkout price or stock reservation; checkout still recalculates normally.

After sending, replies use the normal composer without automatically repeating the card. Historical cards retain their original title/price/image reference after listing changes. Ordinary chat uploads remain private to the conversation; product images are the listing's marketing images, accessed through the participant-checked message view. Order-related chat, files and unread notifications continue as before. A product from a different seller or a hidden listing cannot be substituted into the enquiry.

## Rider work availability and hub reactivation — 14 November 2026

Riders, including dedicated hub-transfer drivers, open **Work availability** in navigation. Local riders can also expand **New work** on the dashboard. Choose **Available for new work** or **Unavailable for new work**, then save. This is your willingness to accept new jobs, separate from login presence, account approval, licence expiry and verified cargo capacity. A conflicting change in another tab requires a refresh. Saving does not change your official profile or another rider's choice.

**Preparation mode is currently active:** choices are saved but do not yet restrict new jobs. Seven approved riders had not chosen when the migration was checked. No choices were supplied on their behalf. Logistics must announce when enforcement will start; the page explains the current mode.

After enforcement starts, not-chosen and unavailable riders cannot receive or accept new pickup, delivery, hub-transfer or buyer-return work. Choosing unavailable returns only safely unaccepted offers to logistics, with tracking/audit records and participant notifications. An accepted pickup/delivery/return, or an in-transit hub load, stays assigned. Continue the normal scans, seller/buyer handover, delivery-code/proof and return procedures. Suspension, expired licence, missing verified vehicle capacity and overweight loads still block the applicable operations independently. Missing historical acceptance/custody evidence requires review; it is not silently repaired by availability reconciliation.

In **Logistics → Riders**, read the separate account-state and new-work labels. Reserved/held parcel count and weight cover pickup, delivery, transfer and buyer-return reservations. The local active-job count includes failed deliveries still requiring handling; its configured maximum is separate from the vehicle's verified kg capacity. Today's completion count uses recorded delivery time, not order placement or settlement. These counts are operational workload, not proof of money earned. Lists retain their existing 20-row pagination.

The original suspending hub can reactivate an approved rider it suspended, subject to current hub scope. An administrator suspension, another hub's suspension or an old suspension without recorded hub provenance requires the relevant administrator's review. Reactivation restores account approval but leaves new-work availability **Unavailable** until the rider chooses. Existing vehicle verification, cargo capacity, accepted parcels and exception recovery remain in place. During preparation mode, the availability label itself does not restrict assignment.

### Availability activation for operators

1. Deploy the migration before these readers/writers. Back up the configured database. Keep `RIDER_WORK_AVAILABILITY_ENFORCED=false` while riders choose; new accounts initially have **Not chosen**, never an inferred online/available state.
2. Run `php artisan riders:availability-audit --json` for read-only counts. Run `php artisan riders:availability-audit --check --json` as the readiness gate: exit 1 means choices or ambiguous records still require attention. The [read-only schema probe](../scripts/verify-rider-availability-revision.php) also checks fields, hub foreign key and current-mode candidate query. No identities are printed.
3. Collect choices and inspect any flagged missing acceptance record, unclaimed pickup with handover/arrival evidence, or unaccepted buyer return with custody evidence. Resolve these through the existing documented exception/recovery process using actual evidence. Do not fabricate acceptance or handover records to make counts pass. Readiness is a snapshot; repeat it immediately before activation and coordinate a short pause in new assignments if needed.
4. Announce the start time and explain that Not chosen will block new jobs while accepted work stays assigned. Only then set the deployment's `RIDER_WORK_AVAILABILITY_ENFORCED=true`. Rebuild the deployment's configuration cache and restart long-running queue/scheduler workers so every process uses the same flag. Clear the short-lived logistics dashboard cache through the deployment's normal cache procedure.
5. Preview `php artisan riders:reconcile-availability` without `--apply`; it is read-only. After activation, run `php artisan riders:reconcile-availability --apply` to release safely unaccepted offers for unavailable/unchosen riders. Each release is audited and notified; retries do not release an accepted job or add another release event. The existing scheduler invokes the same reconciliation every minute only while enforcement is enabled. Ensure the scheduler and normal notification workers run.
6. Recheck readiness and inspect dispatch queues, notifications and accepted-work continuity. If enforcement needs to pause, set the flag false and refresh configuration/workers consistently. This does not restore already withdrawn offers: assign them explicitly through normal logistics controls. Retain schema/provenance/history when rolling back code; older writers can bypass enforcement, so use a compatible version or pause new-work writes while fixing forward.

Activation and `--apply` were **not** run against the configured database during this implementation. All tests that exercise enforcement/reconciliation use isolated fixtures. Browser visual/responsive review remains pending because browser access is unavailable.

## Rider profile, pickup records and compensation history — 14 November 2026

Open **My rider profile** in rider navigation, including for dedicated hub-transfer drivers. Use its **Official profile**, **Vehicle & capacity** and **Private documents** tabs. The page shows current official values, assigned hub/service areas, licence expiry and recorded capacity verification. A pending-change notice links to **Account → Profile change requests**; proposed values do not replace official details until logistics administration approves them. An approved vehicle/plate change can invalidate capacity verification, as before. Missing/expired licence information and unverified capacity are shown explicitly.

Private documents show **File available**, **Recorded file is unavailable**, or **Not submitted**. File presence is separate from approval and expiry. Use **View document** or **Download** for an available file; these use the existing authenticated own-document endpoint. Ask logistics administration about missing files. This page cannot upload a replacement, approve a profile change or change another rider's details. Existing account appearance and administrator review pages remain available.

For pickup, accept the assignment, record arrival, wait for the seller to confirm physical handover, then scan/enter the actual parcel label. Use **EZP:** followed by the label code, or the label code alone. The order number is not that code. Manual entry and camera/handheld requests share the same server checks; neither bypasses seller handover. A matching possession confirmation records pickup once, updates the participant order views and sends existing status notifications. Repeating confirmation cannot record another pickup or earnings credit.

On **Collected parcels heading to the hub**, choose **Pickup record and compensation** to review acceptance, arrival, seller handover and possession timestamps and the next hub-intake step. The same record links to **Work history**. Successful pickup alone does **not** credit wallet earnings.

In **Delivery history / Work history**, search an order number or filter parcel stage. Lists show 20 orders per page with stable ordering and retained search/filter values. Each order appears once, including your hub-transfer work and recorded allocations. Expand **View work & compensation** for pickup, last-mile, return-to-sender and individual transfer-leg components that apply to your work. Parcel stage/activity describe the overall parcel; your component dates and attempt notes describe your own work. Another rider's recipient notes are not displayed to a transfer-only participant.

Compensation labels mean:

- **Estimated:** the recorded approved rate has a positive component; qualifying work has not yet been recorded.
- **Pending settlement:** work is recorded, but no verified allocation credit has posted. Funding, buyer confirmation, holds, disputes and administrator settlement checks still apply; this label is not a payout authorization.
- **Held:** review is required, including an affected return/refund/dispute, a settled shipment without your corresponding credit, or an allocation whose posting cannot be verified.
- **Settled:** the allocation matches a logistics-earnings transaction for this order/component and the credit to your available wallet. Current wallet cash can differ after withdrawals and other transactions.
- **Reversed:** the original posting has a recorded reversal; its contribution to this history's net settled amount is zero.
- **No paid component:** the recorded approved schedule explicitly sets that component to PHP 0.00.
- **Unconfigured:** the original shipment snapshot, approved schedule or component amount is unavailable. No rider percentage or payment is invented for legacy flat shipping.

Estimates use the purchase's recorded tariff or latest approved shipment adjustment, not today's replacement tariff. A proposed adjustment does not change them. Positive estimates remain separate from settled cash; missing original shipment snapshots require administrator review. The total above the history table covers **net settled credits on the displayed page only**. It excludes estimated, pending, held, reversed and zero-paid components and is not the wallet balance.

**Earnings** retains the available-balance link and 25-row allocation list, now with original allocated amount, net settled amount, posting state and transfer-leg labels. Use **Wallet and statements** for actual account activity and existing withdrawal rules. Opening profile/history/earnings does not create an account, allocation, tracking event or ledger credit.

To replay the isolated profile/work-history reference fixtures, set `HASH_DRIVER=bcrypt` in the test shell and run `php vendor/bin/phpunit tests/Feature/CourierWorkPresentationTest.php`. Tests create temporary SQLite records and fake private files; they do not insert persistent demo accounts or replace real names, licences or submitted papers with teacher examples. Browser appearance and real device-camera operation remain unverified while browser access is unavailable.

## Delivery steps, payment indicators and retry proposals — 14 November 2026

On a rider's delivery record, **Delivery progress and next step** shows the attempt number, failed-attempt count, acceptance deadline and scheduled start. Accept an offer before its deadline using the existing action; then wait for logistics to verify physical hub handoff. Start delivery only after that handoff and any scheduled retry time. Account approval, availability under the active rollout mode, licence, coverage and verified vehicle/cargo limits still apply. A displayed next step is a snapshot, not permission to bypass a server check. If a historical acceptance record is missing, ask logistics to review it.

The payment indicator distinguishes:

- **Cash on Delivery:** collect the exact displayed order amount and record it on completion.
- **Verified wallet payment:** collect PHP 0.00. Recipient confirmation, the buyer's valid delivery code and delivery proof are still required. This is the existing sandbox wallet flow, not a newly connected bank/GCash provider.
- **Payment verification hold:** do not dispatch or collect cash. Ask administration to review the payment or held wallet. A payment-method name alone does not prove payment. Unsupported providers remain on hold.

Complete delivery with the buyer code, recipient confirmation, required photo/PDF proof and exact cash amount for COD. The application proof limit remains 4 MB; the host's upload/post limits must permit the file. If a code is expired/locked/used, ask the buyer to use their order-page refresh/review process. The rider screen does not reveal the secret code. Delivery records and cash collection are recorded once; completion does not directly credit logistics earnings. Existing buyer receipt, financial verification, holds and settlement approvals remain.

For a failed delivery:

1. Record the actual reason while the order is out for delivery. The attempt number and failure history remain attached to the parcel. Keep physical custody until the recorded handover/recovery process is completed; payment/account holds may require administration's review before further actions.
2. Bring the parcel back to its owning hub. Logistics must scan the parcel reference **and** an active Returns/Exception location through **Parcel intake**. This records recovery, releases the old active assignment and stages the parcel as Sorted. A status or timestamp without the current accepted intake/placement records is insufficient for a rider retry proposal.
3. Below the configured attempt limit, open **Delivery history / Work history**, find the order (Sorted is now a filter option), and expand **View work & compensation**. The rider of the latest failed attempt can choose **Propose retry to logistics**, enter a future preferred time in the displayed application timezone and give a reason/recipient availability. This is optional: logistics can still dispatch independently.
4. One proposal can be recorded per failed attempt. An identical repeat has no additional effect; a different repeat cannot overwrite history. Rejected/closed proposals remain visible. Contact logistics if your preferred time changes. The proposal does not reserve a rider, create an attempt, change custody or credit money. A rider may propose work for logistics to allocate to another eligible rider; it is not self-assignment.
5. In **Logistics → Rider dispatch → Retry proposals**, the owning hub reviews the reason. **Schedule and assign** uses the existing dispatch route, rechecks current scanned custody/attempt limit and all rider/coverage/capacity rules, and requires a future confirmed time. The hub may choose a different rider or time. Alternatively, **Reject proposal** requires a reason and notifies the requester. A failed review leaves the proposal pending and the parcel unchanged.
6. A scheduled proposal still requires the assigned rider's acceptance and physical hub handoff. The rider cannot start before the confirmed time. The existing scheduled attempt becomes the actual attempt when its result is recorded; proposing or reviewing alone does not count as another failure. For example, a first failed attempt followed by a scheduled retry displays **Attempt 2**.

Proposal outcomes are **Pending**, **Scheduled**, **Rejected**, or **Superseded**. Independent dispatch supersedes an outstanding proposal rather than leaving two pending instructions. Actual source-hub transfer release also supersedes a pending local proposal and notifies its requester; follow the destination hub's dispatch afterwards. Planning a transfer blocks local retry approval until the routing/custody decision is resolved. Request origin and completed review history cannot be edited or deleted through these modules.

In **Rider dispatch → Return required**, the list shows parcels at or above the configured failed-attempt limit (default three). Each row retains all recorded failure reasons and current parcel stage. This is an **attention queue**, not a claim that the parcel has physically returned. A third failure alone does not set Return in transit, restore stock, refund payment or pay earnings. After real hub intake, assign an eligible return rider, verify hub handoff and use **Start seller return**. Another buyer-delivery attempt remains blocked. Existing courier seller-handover, seller receipt and separate restock inspection remain required.

Dispatch tabs retain the normal parcel/manifest actions and show pending proposal/return counts. Both new tables show 25 rows per page with independent pagination. History remains 20 rows per page. The proposal table is scoped to the owning hub; a different hub cannot review it. Proposal decisions produce administrative audit and parcel-timeline records without pretending an administrative decision is a physical handover.

Deployment: back up the configured database and apply `2026_11_14_050000_create_delivery_retry_requests_table` before deploying its readers/writers. The [read-only retry probe](../scripts/verify-delivery-retry-revision.php) checks the schema, unique failed-attempt constraint, hub queue index and empty-owner SQL without creating a proposal. Retain the table/history if rolling back after it has been used; pause retry writes or keep compatible code while fixing forward. Existing physical dispatch, payment and return guards remain authoritative. The following continuation delivers guided seller receipt/restock and existing-wallet RTS authorization. External provider integration, browser/device-camera checks and availability activation remain outstanding.

## Seller return receipt, inspection and wallet RTS review — 14 November 2026

Open the seller's order and choose **Review return receipt and inspection** or **Inspect returned items and review progress**. Buyers have **View return progress** on their own returning order. Related accounts and relevant administrators can also use **Financial records → Review return workflow**. The four tabs are **Receipt**, **Inspection**, **Refund** and **Logistics costs**. Opening a tab records no receipt, stock, account, refund or money movement.

1. **Courier handover:** the assigned return courier scans/enters the actual parcel code and records physical seller handover through the existing return action. An order number is not the parcel code. Seller receipt cannot precede this handover.
2. **Seller receipt:** after receiving the physical parcel, the seller opens **Receipt**, scans/enters the assigned rider's badge, and confirms receipt. A mismatched badge fails. Receipt changes the parcel stage and records evidence; it does not restore stock or refund payment.
3. **Inspection:** the owner seller enters sellable quantities for every purchased item/option, from zero up to the purchased quantity. All fields start at zero. Explain excluded, damaged, missing or unsuitable units in the inspection notes. For example, of three returned units, enter two if only two are sellable; one remains excluded. The original product and variation receive only the approved quantity. Missing catalog items/options must stay at zero and be referred to administration; do not substitute another product.
4. **Confirm once:** review the decision popup before recording inspection. It is one atomic decision across all items; quantities cannot be overwritten or applied twice. Long orders have groups of 25 items in expandable sections, all inside the same form. Notes and the recorded breakdown are visible to the owning seller and commerce administrators. Buyers/hubs see the stage rather than private inspection notes. All-excluded inspection is still a completed inspection, with no stock restoration.

Historical inspections without an item breakdown are labeled as legacy; the application does not reconstruct quantities. A historical Returned status with incomplete scan evidence requires administrator review for the new RTS money authorization. The established legacy inspection endpoint remains compatible with its previous all-or-none submissions. Ordinary cancellation restoration remains unchanged.

For an **undelivered prepaid order paid with the existing sandbox wallet**, commerce administration opens **Refund** after recorded seller receipt:

1. Review the original full payment, verified staged funds, account holds and existing refund/payout state. Choose **Authorize RTS wallet refund** and give a reason. This creates an authorization, holds an existing unsettled seller payout and notifies the related buyer/seller/hub; **no money is sent**. It does not require sellable stock: damaged exclusions must not force restocking to obtain a buyer refund.
2. Separately choose **Credit approved wallet refund**, give the execution reason, and confirm. The existing funded wallet service credits the recorded full amount once and reverses pending seller proceeds/promotion funding through its established rules. It rechecks the RTS receipt, account, amount, undelivered state and unsettled payout. Repeated execution is rejected. Authorization retries retain the existing compatible refund; they do not create another one or a fictional dispute.
3. Buyers review their wallet statement and the execution stage. An Authorized label is not proof of payment. Returned sale proceeds cannot be requested as a normal seller payout. Delivered returns still use the existing marketplace dispute/refund decision; they do not use this dedicated undelivered RTS authorization.

Uncollected COD has no buyer cash refund to create. Unsupported external payment labels are not verified funds; external-provider integration remains pending. Settled payouts, historical partial/multiple refund records, missing/mismatched funding or missing receipt evidence require reconciliation rather than a guessed automatic refund.

**Logistics costs** remain separate. For supported funded returns, follow the existing buyer-refund execution, evidenced logistics cost proposal, independent commerce/treasury approval with sufficient funds, and logistics allocation actions under **Shipping financial breakdowns**. Existing rates, evidenced work and held components govern payment; receipt, inspection and authorization never invent rider/hub percentages or release seller sale proceeds. Uncollected-COD return-cost funding still needs separate finance reconciliation; this revision does not create a refund to unlock it.

Deploy the two additive migrations `2026_11_14_121032_create_return_inspection_items_table` and `2026_11_14_121033_add_rts_authorization_to_refund_executions_table` after a private backup and before their readers/writers. Run the [read-only schema/query probe](../scripts/verify-return-workflow-revision.php). Retain inspection and refund history after use; pause writes and use compatible code or fix forward. The RTS migration refuses to restore a mandatory dispute reference when dispute-free refund history exists. Do not drop inspection history as a live rollback. Browser layout, real scanning and external delivery still require acceptance checks.

## Logistics overview counts and tracking searches — 14 November 2026

**Operations overview** now displays its update time. Refresh to retrieve fresh records; the former short-lived card cache is no longer used. The four priority cards open their matching **Delivery tracking** queue. Assigned, active delivery, delivered-today and seller-return totals also link to their matching lists. Counts cover all matching records, including later pages; tracking shows 20 parcels per page and dashboard tables retain 25. Counts can change after another device records work: these are request-time snapshots, not cross-device push updates.

**Delivered today** includes Delivered and Completed parcels whose recorded delivery time is within the current application-calendar day, excluding the next midnight. It does not mean all completed orders were completed today. **Seller returns** includes both Return in transit and Returned to seller. Rider figures describe coverage/account candidates, not dispatch permission; licence, availability mode, capacity, workload and physical custody still determine actual eligibility. Exceptions use the same hub ownership rules as the parcel lists, including parcels without a recorded receiving hub.

In **Delivery tracking**, enter an order number, exact numeric internal record ID, exact parcel code with or without **EZP:**, buyer first/full name, or recorded recipient name. Numeric input is treated as an exact internal record ID, and EZP: input as an exact parcel reference; these modes do not also match name/order fragments. References and names remain inside your hub scope. Percent/underscore are literal search text rather than wildcards. Unknown/injection-like text does not expand the search to other hubs.

Choose **Dashboard queue** to follow a card's group. Other status, rider, destination area and updated-date filters narrow that group together. Clear filters to return to the default active/return stages. Delivered/Completed records require their explicit status or the delivered-today queue. The **Updated date** filters last update time; **Delivered today** separately filters delivery time. Results show the matching total, record ID, parcel reference and numbered pages, retaining the filters. Viewing/searching does not mutate parcel status, tracking, audits or financial records. For reasoned correction reviews, zone readiness and reconciliation exports, use the following sections.

## Correction reviews — 14 November 2026

The following continuation delivers the previously pending P7 correction, readiness and remittance-export revisions. It does not activate provider payments or rider-availability enforcement.

1. The owning sorting hub opens **Delivery tracking → Request correction review** on the parcel. Give a clear discrepancy/investigation reason and attach a JPG, PNG or PDF of up to 5 MB. The host's upload/post limits must also permit the file. Submission preserves the parcel/assignment snapshot and private evidence; it changes no physical stage, inventory, cash or earnings. Only one review can be open for a parcel.
2. Use **Correction reviews** in the hub/logistics-admin sidebar to find the case. Open reviews are the default; use status/order filters and numbered 25-row pages for history. **Request & comparison** shows the original, current and decision snapshots. Evidence is downloaded through authenticated access. If the file is missing or its checksum changed, the page flags it, prevents presenting altered bytes as the original and blocks resolution. History and the original checksum remain retained.
3. Logistics administration reviews the evidence independently. If the request is incorrect, use **Review decision → Reject with reason**. Rejection changes no parcel stage. The requester cannot approve their own request, including after a role change.
4. If work is needed, coordinate the existing guarded intake, sorting, assignment, delivery or return action first. On **Recorded workflow**, find the newer event ID for this same parcel and current stage. In **Review decision**, enter that ID and a resolution reason, then confirm **Resolve using recorded workflow**. The parcel/assignment snapshot must have actually changed since the request; an old/foreign event, a note-only no-op or a mismatched current stage cannot close the case as resolved.
5. Delivery/Completed review still requires actual proof, the used buyer code and verified wallet payment or exact recorded COD collection. Returned-to-seller review requires recorded physical seller receipt. A review form cannot mark Out for delivery as Delivered, collect cash, issue refunds, restore stock or pay earnings. Existing delivery/return actions remain authoritative.
6. The decision records reviewer, time, reason, linked event and before/after values; the requesting hub receives a notification. Logistics admins can open the linked activity history. Request origins and completed decisions have no edit/delete action. An identical decision replay creates no extra decision/audit; a conflicting replay is rejected. After closure, a later genuinely new discrepancy can have a new request, retaining the old one.

Other hubs, sellers, buyers, riders and commerce admins cannot read this dedicated review/evidence record. A physical transfer does not transfer the original case's review ownership: its requesting hub and logistics administration retain the case, without receiving private delivery-note/proof contents through the comparison table. The current snapshot is a request-time read and may change after another device records work.

Deploy `2026_11_14_125842_create_parcel_corrections_table` after a private backup and before deploying its readers/writers. The [read-only correction probe](../scripts/verify-parcel-correction-review.php) checks constraints, scoped queries and unchanged business counts. Once cases exist, retain the table/files/history and use compatible code or fix forward; its rollback refuses to discard populated review history. Backups have not been restore-tested against the configured database.

## Zone readiness and planning quotes — 14 November 2026

Open **Zone readiness** in the sorting-hub/logistics-admin sidebar, from Routing areas, or from Delivery tariff versions.

- **Setup & activation:** create/map an active destination area and assign it to an approved hub; also confirm the seller-origin pickup mapping. Logistics administration drafts reviewed costs/weight brackets, a different administrator approves the tariff, then the existing explicit activation action enables versioned pricing. The table identifies the four required route classes with effective approved versions. Drafts and future-effective versions do not satisfy current readiness. Sandbox tariffs require the sandbox environment setting. The current model has no tariff expiry field; replacements are versioned/effective-dated.
- **Parcel preview:** select a permitted area, enter origin/destination province, municipality and barangay, actual packed kg and dimensions in mm, and choose COD or the existing wallet. The result uses the same tariff calculation as checkout and distinguishes actual, volumetric and chargeable weight. Missing/mismatched mappings, inactive/unassigned/suspended hubs, unapproved/future rates and overweight measurements produce actionable configuration issues.
- Vehicle figures count approved local coverage vehicles with enough verified maximum capacity under the current availability mode and recorded licence-expiry check. They exclude dedicated hub-transfer drivers. These counts precede current cargo reservations, job limits, barangay assignment/override rules and physical handoff checks; they do not reserve vehicles or authorize dispatch. Inter-hub transport still follows the existing dedicated-driver/transfer process.

Mapping or approval alone leaves the legacy fee active until explicit activation. The page labels that legacy fee and unconfigured compensation rather than implying an approved tariff. Previewing writes no mapping, tariff, account, order, stock, assignment or money record. It uses a planning parcel; actual checkout still checks its products/address, stock, vouchers/payment and current tariff. Existing placed-order snapshots are retained. This implementation does not enable configured tariffs or choose real rider availability on behalf of staff.

## COD reconciliation CSV and Excel — 14 November 2026

Open **Remittance batches → Reconciliation report filters**. Choose submitted-from/to dates, batch status, rider ID and receiving-hub ID as needed, then **Apply filters**. The batch table, its summary totals and both **Download CSV** and **Download Excel** use the same role-scoped filtered dataset. Exports include all matching pages; the on-screen list remains 25 rows per page. The separate outstanding-collections queue remains an operational list rather than part of these report filters. Existing wallet/shipping CSV and analytics PDF/chart-print functions remain available in their original flows.

The Excel workbook contains **Summary**, **Batch details** and **Definitions**, with filter/date/timezone metadata, numeric PHP cells, text identifiers, frozen/filterable headings, banded rows, readable column widths and print settings. CSV includes corresponding metadata, details, totals and definitions. Formula-like text is protected; CSV leading-zero or over-15-digit numeric identifiers carry an apostrophe to preserve them in spreadsheet applications. XLSX identifiers remain typed text.

Expected cash and rider-declared cash are separate from a recorded physical receipt. Missing receipt/discrepancy amounts stay **Not available**, while totals sum the amounts actually recorded. The summary also counts batches awaiting a cash count. Negative discrepancy is shortage and positive is excess; net zero can hide offsetting differences, so inspect individual rows. Neither export verifies cash, reconciles a batch or triggers payout. Private evidence paths, credentials, recipient addresses and document contents are excluded.

An empty result downloads a valid statement with headings, filters, zero totals and an explicit no-data note; no sample batch is fabricated. Statements exceeding 10,000 matching batches are rejected before streaming; narrow the filters instead of accepting a partial report. Dates use `submitted_at` in the displayed application timezone, with an inclusive start and exclusive next midnight. The [read-only zone/report probe](../scripts/verify-p7-zone-remittance-revisions.php) checks empty MySQL queries and empty-workbook generation without business writes. Browser/spreadsheet-application visual acceptance remains unverified while the browser connector is unavailable.

## Prepaid payment holds and remaining gateway work

Custom-wallet payment verification now checks the original matching buyer debit and order-escrow credit, their transaction identity, amount and account availability. A payment-method label or order reference alone does not authorize fulfillment. Staged seller proceeds do not invalidate an otherwise valid original payment.

If an order displays a payment verification hold, stop preparation/dispatch and ask finance administration to reconcile the original payment entries and account holds. Do not collect COD for a prepaid parcel, ask for another payment automatically, create a top-up to clear the warning or manually mark it paid. Existing cancellation approvals, physical return receipt, refund authorization and separate execution remain required.

The user's 14 November decision replaces the provider-dependent work with an internal school/demo wallet. **The six revised internal-wallet implementations are delivered**, including administrator-reviewed demo credits/recorded demo deposits, To Pay reservations, payment/expiry, full cancellation refunds and existing prepaid delivery/RTS controls. Follow the [wallet demo walkthrough](internal-demo-wallet-user-guide.md) for explicit local sandbox activation, funding, administrator review, payment, voucher/cancellation and return-refund steps. Demo funding was enabled on 14 November 2026 at the user's request in the configured local/sandbox environment. No opening balances were created; requests still need independent administrator approval. See the [final 54-revision review](final-54-revisions-check.md).

External-provider checkout, card processing and real cash-in/cash-out remain unavailable. The earlier provider plan is retained as historical research and the teacher external-payment expectation remains a documented difference. See the [revised implementation plan](internal-demo-wallet-implementation-plan.md) and [change audit](partial-features-implementation-audit.md). Internal demo credits do not establish that real money was paid or received.

## Seller return responses and automatic completion (15 November 2026)

New web return/dispute submissions offer Seller first or direct Administrator assistance. The seller-first path gives the seller 72 elapsed hours to respond. Sellers open **Return requests** in their sidebar, review private evidence, and approve the request or reject it with a 10–300 character reason. The page has stage filters and numbered 25-row pagination. Buyers see the exact reason in the order/return progress and can escalate within 72 elapsed hours of rejection. An escalated case is In Dispute and no longer accepts seller decisions. Urgent admin takeover remains available while an eligible seller-first request is open.

An unanswered seller request becomes approved for further admin authorization when its 72-hour deadline is processed. An un-escalated rejection closes when the buyer's 72-hour escalation window expires. Exact deadlines are expired; valid late response/escalation attempts also enforce these rules. Request approval does not mean pickup, returned stock or a paid refund. Continue the existing admin authorization, rider acceptance, buyer handover, physical receipt, inspection and separate funded refund workflow. Historical admin-led cases keep their existing handling.

New verified deliveries at/after the configured rollout cutoff enroll for completion after 168 elapsed hours. Existing Delivered orders do not enroll retroactively. Buyer receipt remains available earlier when evidence/payment and open-case checks pass. Completion is paused for active returns/disputes/refunds, open recovery/correction cases, payout holds, missing proof/code or inconsistent payment/collection records. The buyer order page explains the hold. Completing an order does not automatically release earnings; finance must still verify funds and approve/execute payout.

Operators can preview without changing data:

```powershell
php artisan returns:process-deadlines
php artisan orders:complete-delivered
```

The registered scheduler runs seller deadlines every minute and completion every 15 minutes with `--apply` and overlap protection. Confirm that the deployment scheduler is actually running. Preview eligible/held records before applying manually. A reviewed historical delivery can be enrolled individually with `orders:complete-delivered --enroll-order=123 --reason="Reviewed delivery proof and collection records"`; this previews unless `--apply` is added, requires valid evidence, records the opt-in and does not itself complete the order. Review the completion preview again afterward.

The default `AUTO_COMPLETION_ROLLOUT_AT` is `2026-11-15T00:00:00+08:00`; existing null enrollment timestamps remain unchanged when configuration changes. See the [Batch 4 audit](missing-56-batch-4-audit.md) for case IDs, verification, local rollout and remaining acceptance limits.

## Automatic dispatch and parcel incidents (15 November 2026)

Hubs, couriers and logistics admins can open **Automatic dispatch**. A hub must confirm the actual seller/hub pickup coordinates in Pickup pins; pins are tied to the recorded approved address. Riders choose Available on the existing work board, then press **Share location for offers** and grant browser permission over HTTPS/localhost. No background tracking starts automatically. A device-reported position must be recent and accurate within 250 m; it is usable for five minutes and removed after 24 hours by scheduled dispatch processing. Raw rider coordinates are not shown to buyers/sellers. If location permission, accuracy or confirmed pickup pins are unavailable, use manual assignment.

For an unassigned seller pickup or first-attempt Sorted parcel, the hub can start an explicit offer cycle in Ready work. Candidates need approval, availability, local duty/area coverage, fewer than three active/reserved jobs and verified spare cargo capacity. Dedicated hub-transfer drivers keep using Hub transfers. Offers rank by approximate straight-line distance to the seller pickup/hub, not by buyer destination. No route or arrival-time guarantee is implied.

The rider has 60 seconds to accept or decline. Acceptance assigns work but does not prove physical possession. Continue the existing arrival, badge/parcel scans, handover, storage retrieval and dispatch actions. Three distinct declines/expiries stop the cycle in **Unassigned**, with separate candidate outcomes. Resolve the reason before starting another cycle, or cancel provisional offers and use manual assignment. The scheduler does not restart Unassigned work by itself. Provisional offers count toward the vehicle's load; expiry/decline releases only that reservation, while accepted physical cargo retains its normal recovery rules.

Operators can preview `php artisan dispatch:process`; `--apply` processes existing cycles and prunes old device points. The registered scheduler uses `--apply` every minute with overlap protection. Acceptance enforces expiry immediately even before that next scheduler run. Confirm actual scheduler operation after deployment. No pickup pins or GPS points were seeded into configured accounts by this revision.

Use **Parcel incidents & claims** to track Lost/Damaged reports. Hubs/logistics admins can report incidents; damage requires a private photo. A report preserves the recorded physical stage, current custody, manifest and load and starts claim review. Buyers, sellers and involved staff receive updates and can view their relevant incident/evidence. Open incident holds block movement, receipt, completion, stock restoration and payout. Status labels show Lost/Damaged while the original physical stage remains recorded separately.

Commerce admins record the funding assessment and next steps without issuing money. Uncollected COD cannot create a cash refund. Existing paid/prepaid funds, prior refunds or settled proceeds require their own authorized financial/recovery review; reporting/assessing an incident does not invent compensation, seller debt or wallet credit. Irrecoverable loss remains under review; no automatic loss-compensation payment engine was added.

An independent logistics admin can reconcile recovered or incorrectly reported custody using the current physical parcel label, a supporting photo and a reason. Clearing a hold does not change the physical stage, restore stock, mark Delivered or pay money. Original evidence and claim/audit history remain recorded. Continue the appropriate guarded workflow afterward.

The final three policy cases use the existing internal demo-wallet reopening/retry behavior and evidence-bound correction review. There is no external gateway callback or reason-only Delivered override. See the [final seven-case audit](missing-56-final-seven-audit.md): 53 revisions implemented and three explicit scope reconciliations address the 56-case inventory, with original teacher expectations/flags preserved.
