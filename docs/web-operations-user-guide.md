# Web operations user guide

See the [all-module revision audit](all-modules-revision-audit.md) for confirmed workflow gaps, priorities and verification results from 7 November 2026.

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

## Mobile boundary

Flutter/Dart files, mobile controllers, mobile middleware, `routes/api.php` and API response structures are untouched. New pages/actions are web routes. Shared model additions are additive. The shared purchase service enforces newly configured recipient/per-buyer caps and shop closures so another checkout caller cannot bypass them; legacy open shops and vouchers with null new limits retain their prior behavior. Mobile does not acquire new native profile-review, attachment, promotion or variant-editor screens from this web revision.

The native mobile category endpoint keeps its original options under the protected-API restriction. New web-only database fields are hidden from existing model serialization so they do not expand native product/profile response shapes.

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
