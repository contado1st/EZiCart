# Web operations user guide

## Staff profiles and review

1. Riders and sorting-center staff open **Staff profiles** in the sidebar, then their own profile.
2. Submit proposed names, mobile number, display information, profile photo and applicable vehicle/plate/license details. Only one request may be pending per account.
3. The logistics administrator receives an in-app notification and opens **Profile reviews**.
4. Compare the verified and proposed values. Approve to apply them or reject with a reason. The proposal, previous values, reviewer and decision remain in history.
5. Basic account edits and the existing coverage vehicle form use this same review gate. Changing appearance through the old account form redirects staff to their reviewed profile.

Related hubs and staff can see operational profiles. Buyers and sellers involved in a parcel can open its assigned rider's verified profile through the parcel journey. They do not see proposal history or license expiry. Unrelated users cannot open the profile or its private photo.

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
