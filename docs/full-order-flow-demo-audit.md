# Complete seller-to-buyer demonstration and audit

Prepared for the local EZiCart database on **2026-10-27**. This walkthrough starts at **seller product creation** and ends at **buyer receipt and review**. Use one newly created product and one newly placed COD order throughout the demonstration.

## 1. Accounts and shared location

The setup reuses these existing approved demo accounts. Their passwords are preserved; the local seed output confirms whether each still matches **`Password123!`**.

| Tab / role | Email | Starting page | Responsibility |
| --- | --- | --- | --- |
| Seller | `seller@ezicart.com` | `/seller/products` | Create listing, prepare parcel, schedule pickup, verify rider handover |
| Buyer | `buyer@ezicart.com` | `/` | Purchase, show delivery code, confirm receipt, review |
| Rider | `courier@ezicart.com` | `/courier/dashboard` | Seller pickup and final doorstep delivery |
| Sorting center | `sorting@ezicart.com` | `/logistics/dashboard` | Assign pickup, receive, sort, store, assign delivery, release custody |
| Marketplace admin | `admin@ezicart.com` | `/admin/compliance/products` | Approve the new product before the buyer can purchase |

All four operational demo profiles and the buyer's named demo delivery address use:

| Address field | Value |
| --- | --- |
| Region | Region IV-A (CALABARZON) |
| Province | Laguna |
| Municipality | Majayjay |
| Barangay | San Roque |
| House / street | 45 Market Road |
| Postal code | 4005 |
| Seller shop | Sarah Essentials Store |
| Routing area | Majayjay Demo Delivery Area (`EZ-DEMO-MAJ`) |
| Hub | EZiCart Central Sorting Hub, account `sorting@ezicart.com` |
| Primary barangay rider | Carlos Courier, account `courier@ezicart.com` |
| Staging bin | Demo Majayjay staging bin (`DEMO-STAGE-01`) |
| Staging scan text | `EZL:DEMO-STAGE-01` |

The rider belongs to this hub and has active service-area membership. The same approved rider handles both legs of this single-hub demo. These are demonstration addresses, not a claim that the people or businesses operate at this physical location.

## 2. Open the demonstration sessions

1. Open **five fresh blank tabs** using `Ctrl+T`. In each tab, type your running EZiCart site's address and sign in as one role above. Use the same running site/domain throughout.
2. A fresh direct navigation receives its own tab session. A tab opened from an existing in-app link can retain that account's session, so use a fresh blank tab when signing in as another account.
3. Keep the five tabs open and refresh a role's page after an action in another tab. Label or group the tabs by role if helpful.
   Keep the `__tab` value in the address when refreshing, bookmarking, or copying a link for that session. Use the page's navigation/sidebar to move around within a signed-in role. A bare site URL in a fresh blank tab starts a separate login session.
4. Use the application's normal buttons and confirmation dialogs. Track the new order number so older demo orders are easy to distinguish.
5. If local background processing is required, run `php artisan queue:work` and `php artisan schedule:work` in separate terminals alongside the existing web server. A failed queue job needs investigation; a running worker alone does not establish successful delivery of notifications. Order pages and tracking remain the evidence for each step below.

## 3. Product creation and compliance

### Step 1 — Seller: create a new listing

Open **Products → Add New Product** (`/seller/products/create`). Enter:

| Field | Demo input |
| --- | --- |
| Product name | `EZiCart Full Flow Tote - Demo 2026-10-27` |
| Category | Fashion |
| Price | 100.00 |
| Initial stock | 3 |
| Description | `Reusable tote bag for the local seller-to-buyer COD demonstration.` |
| Image | Optional JPEG/PNG/WebP, at most 2 MB |

Click **Save Product**. Record the product ID. The listing should have **`pending_review`** compliance status and be hidden from buyers. Using stock 3 and purchasing only one leaves the product available for checking the public review afterward. Use a distinct name for another run.

### Step 2 — Admin: approve the listing

Open **Product compliance** (`/admin/compliance/products`), locate the exact new product and seller, select **approved**, enter a review note such as `Reviewed for the local complete-order demonstration.`, and save the review.

Expected: the product becomes **`approved`** and is available in search/the seller's public shop. Product approval is required even though the seller account is already approved. Changing the listing's name, category, description, or image later sends it back for review.

## 4. Purchase and seller preparation

### Step 3 — Buyer: purchase one item

Search for the exact new product or visit Sarah Essentials Store's public shop. Add **quantity 1**. Review the cart and remove other items for this demonstration.

At checkout, select **Demo delivery — Majayjay**. Check that it shows **45 Market Road, San Roque, Majayjay, Laguna 4005**. Choose **COD**, apply no voucher, and place the order.

Expected: **`PLACED`**, quantity 1, product stock decreases from 3 to 2. With the standard PHP 50 shipping charge, PHP 100 merchandise totals **PHP 150**. Always use the actual checkout/order total if shipping settings have changed. Copy the real **order number**, **order ID**, and **parcel code** into the record at the end of this guide.

### Step 4 — Seller: accept and prepare

In **Orders** (`/seller/orders`), locate that exact order. Perform these actions in order, confirming the dialog each time:

1. **Accept Order** → `CONFIRMED`.
2. **Start Preparing** → `PREPARING`.
3. **Mark Ready for Pickup** → `READY_FOR_PICKUP`.

Open the order details and schedule pickup with a **future date/time**, for example tomorrow at 09:00, a valid window such as `Morning (8 AM–12 PM)`, and notes `Local full-flow demonstration parcel.` The schedule is recorded immediately so the remaining demo actions can follow; use a future value because past dates are rejected.

Open **Waybill** and display or print the actual parcel label. Its scan text is **`EZP:<actual parcel code>`**. Use the generated value throughout this order; neither the numeric order ID nor the product ID replaces the parcel scan text.

## 5. Seller pickup and hub intake

### Step 5 — Hub: assign the pickup rider

Open **Pickup requests** (`/logistics/pickup-requests`), find the recorded order number, select **Carlos Courier / `courier@ezicart.com`**, and assign pickup.

Expected: the order remains `READY_FOR_PICKUP`, with this rider assigned. Pickup assignment and physical possession are separate checkpoints.

### Step 6 — Rider: accept pickup and record arrival

On **My dispatch board** (`/courier/dashboard`), find the order under **Available seller pickups** and click **Accept assigned pickup**. In **Accepted pickups**, click **Record arrival at seller**.

Expand **Rider identification badge** near the bottom of the rider dashboard. Display its QR or copy the complete **`EZR:...`** text. Keep that current badge for both handovers; rotating it invalidates copies of the old badge.

### Step 7 — Seller: verify the rider and hand over

Refresh Seller Orders. For this parcel, enter/scan the assigned rider's **`EZR:...`** badge in **Scan assigned rider badge QR/code**, then click **Confirm rider handover**.

Expected: seller handover is recorded. The order remains `READY_FOR_PICKUP` until the rider scans and confirms possession.

### Step 8 — Rider: scan physical pickup

Refresh the rider dashboard. Under **Accepted pickups**, enter/scan this order's **`EZP:...`** parcel label and click **Confirm scanned parcel possession**.

Expected: **`PICKED_UP`**. The parcel appears under **Collected parcels heading to the hub**.

### Step 9 — Hub: scan intake

Open **Parcel intake** (`/logistics/intake`), enter/scan the same **`EZP:...`** label, and submit the intake scan. Manual code entry is supported when no camera/scanner is available.

Expected: **`AT_SORTING_CENTER`**, with the receiving sorting center and tracking event recorded. The parcel leaves the rider's collected-pickup list after refresh.

## 6. Sorting, storage, and dispatch

### Step 10 — Hub: sort into the delivery area

Open **Sorting queue** (`/logistics/sorting`), locate the order and verify the destination is Majayjay, Laguna. Click **Confirm area and sort**.

Expected: **`SORTED`**, destination area **Majayjay Demo Delivery Area**. The seller and buyer are in the same service area, so this demo uses one sorting hub and requires no inter-hub transfer.

### Step 11 — Hub: put the parcel into staging

Open **Storage** (`/logistics/storage`). In the put-away form, enter:

- Parcel reference: this order's **`EZP:...`** value.
- Location reference: **`EZL:DEMO-STAGE-01`**.

Submit. Expected: an active parcel placement in the demo staging bin. The order remains `SORTED`.

### Step 12 — Hub: assign final delivery

Open **Dispatch parcels** (`/logistics/dispatch`), locate the order, select **Carlos Courier**, and assign delivery.

Expected: **`ASSIGNED_TO_RIDER`**. The primary barangay rider is already configured, so no coverage override reason should be necessary. The same rider can handle pickup and delivery for this demo.

### Step 13 — Rider: accept final delivery

Refresh the rider workspace and use **Accept assignment** for this order if shown. Do this within the configured **15-minute** deadline. This is the final-delivery assignment, separate from accepting the earlier seller pickup.

### Step 14 — Hub: remove the parcel from storage

Return to **Storage**. In the pick/remove form, enter the same parcel reference and **`EZL:DEMO-STAGE-01`**, then submit.

Expected: the active placement is closed and removal is recorded. A parcel still stored in a bin cannot be released to the rider.

### Step 15 — Hub: scan the dispatch handover

Return to **Dispatch parcels**. For this assigned parcel, enter the rider's current **`EZR:...`** badge and this parcel's **`EZP:...`** label, then click **Confirm scanned hub handoff**. For a one-parcel demo, this per-parcel form is simplest. The batch-manifest form is an alternative; select only the intended parcel and scan every selected label exactly once.

Expected: **Hub release recorded**; the order stays `ASSIGNED_TO_RIDER` with `hub_released_at` recorded. The rider can now begin delivery. Assignment alone does not establish physical hub release.

## 7. Doorstep delivery, buyer receipt, and review

### Step 16 — Rider: start delivery

Open this order from the rider workspace (`/courier/orders/<order ID>`). Under **Start delivery**, provide the parcel reference if requested and click **Start delivery**.

Expected: **`OUT_FOR_DELIVERY`**. The order receives a fresh buyer delivery code.

### Step 17 — Buyer: show the delivery code

Open the order details from the buyer dashboard (`/buyer/orders/<order ID>`). Show the current **six-digit delivery code** or its QR to the rider. This is an order-receipt code, distinct from password-recovery OTP.

Use the current displayed code. The default expiry is 60 minutes and the default incorrect-attempt limit is 5. If expired or locked, use the buyer page's refresh-code action and enter the new code.

### Step 18 — Rider: record successful delivery and COD

In the rider order's **Delivery result** form, enter:

| Field | Value |
| --- | --- |
| Recipient confirmation | John Buyer |
| Buyer delivery code | The actual code shown on this buyer's order |
| Cash collected | The exact full order total, normally `150.00` for this example |
| Delivery notes | `Local demonstration parcel handed to the buyer.` |
| Proof of delivery | A clearly labeled demo JPG/PNG/PDF; use a file below 2 MB to fit the local PHP upload limit |

Click **Confirm delivered** and approve the dialog after the demonstration handover.

Expected: **`DELIVERED`**, a successful delivery-attempt record, private proof attachment, recorded COD collected amount, and consumed delivery code. A proof file and the correct code are required.

### Step 19 — Buyer: confirm receipt

Refresh the buyer's order and use **Confirm receipt** / the received-order confirmation action after checking the parcel.

Expected: **`COMPLETED`**. Rider delivery completion and buyer confirmation are separate actions.

### Step 20 — Buyer: review the purchased product

On the completed order in the buyer dashboard, choose rating **5**, enter `Received the correct tote bag in good condition during the local demo.`, and submit.

Expected: the review is associated with this buyer, product, and completed order and appears on the product page. An early review or a second review of the same product/order is rejected.

## 8. Flow checklist and evidence

Record the new product ID and order number before progressing. These checkboxes are for the live browser demonstration; the automated verification results are recorded separately below.

| Done | Checkpoint | Expected evidence |
| --- | --- | --- |
| [ ] | Product submitted | Seller inventory `pending_review`; buyer listing hidden |
| [ ] | Admin approval | Compliance `approved`, reviewer and review event |
| [ ] | Buyer purchase | `PLACED`, one item, COD total, stock 2 |
| [ ] | Seller acceptance/preparation | `CONFIRMED` → `PREPARING` → `READY_FOR_PICKUP` |
| [ ] | Pickup scheduled/assigned | Pickup time and assigned Carlos Courier |
| [ ] | Rider arrival/seller handover | Arrival timestamp and verified rider badge |
| [ ] | Physical pickup | Parcel scan and `PICKED_UP` |
| [ ] | Intake | `AT_SORTING_CENTER`, hub custody and scan history |
| [ ] | Sort and store | `SORTED`, destination area, active staging placement |
| [ ] | Assign/accept delivery | `ASSIGNED_TO_RIDER`, correct rider and acceptance |
| [ ] | Storage pick/hub release | Placement closed and hub release event |
| [ ] | Delivery started | `OUT_FOR_DELIVERY`, current buyer code |
| [ ] | Rider delivery | `DELIVERED`, proof and exact COD amount |
| [ ] | Buyer receipt | `COMPLETED`, completed tracking event |
| [ ] | Review | One review visible on the product page |

Fill in for your run:

- Product ID: __________
- Order number / ID: __________
- Parcel scan text: __________
- Demonstration date/time: __________
- Observed checkout total: __________
- Completion/review evidence: __________

Use **Tracking**, **Scan history**, **Activity logs**, the order timeline, and product compliance history to demonstrate recorded events. Order state transitions go through the existing `OrderTransitionService`; the setup seeder prepares accounts/routing and does not manufacture completion events.

## 9. Common blockers and recovery

| Symptom | Check / action |
| --- | --- |
| Product absent for buyer | Admin approval, positive stock, product not archived; listing edits can trigger review again |
| Wrong old order visible | Match the new order number and product name, refresh, and use status filters/pagination |
| Hub cannot see pickup | Schedule pickup first and verify both account/location and Majayjay routing |
| Rider missing | Approved Carlos Courier, correct hub, active Majayjay service area, primary San Roque coverage and available capacity |
| Seller handover button missing | Rider must accept pickup and record arrival first |
| Pickup scan rejected | Seller must confirm handover; scan `EZP:` plus the actual parcel code |
| Delivery assignment expired | Hub reassigns through normal dispatch and rider accepts within 15 minutes |
| Hub release blocked | Pick parcel out of storage, use the assigned rider's current badge, and scan the correct parcel |
| Start delivery blocked | Confirm scanned hub handoff first |
| Delivery rejected | Current buyer code, unused/unexpired code, proof file and exact COD total are required |
| Upload fails | Keep the local demonstration proof at or below 2 MB; server request limits can be lower than the form's application limit |
| Review unavailable | Buyer must confirm receipt so the order is `COMPLETED` |
| Automatic timeout changes order | Create a fresh demo order and proceed within seller response/preparation deadlines (defaults 24/48 hours); do not edit status directly in the database |

## 10. Setup and verification audit

Repeat the dedicated local setup with:

```powershell
php artisan db:seed --class=OrderJourneyDemoSeeder
```

The seeder uses one transaction and is restricted to `local`/`testing`. It aligns only the four explicitly listed demo profiles and their named saved demo address, verifies email timestamps, links the rider to the hub, and ensures routing, primary coverage, active membership, badge and staging bin. Existing passwords, other saved addresses and their default flags, order histories, reviews, and remaining product stock are preserved. Conflicting hub ownership or another primary barangay rider stops the transaction. This command is setup, not an order reset.

The existing approved **EZiCart Demo Everyday Tote Bag** remains an optional checkout shortcut. For the full product-creation demonstration, use the newly named product from Step 1.

### Applied local data

The dedicated seeder completed successfully against the local MySQL database on **2026-10-27**. A read after the run confirmed:

| Audit item | Result |
| --- | --- |
| Approved buyer / seller / rider / hub | User IDs **2 / 3 / 4 / 5** |
| Approved marketplace admin | User ID **1** |
| Login password check | All five existing password hashes match **`Password123!`** |
| Shared operational profile address | All four profiles match the address in Section 1 |
| Rider ownership | Rider **4** belongs to hub **5** |
| Active delivery area and municipality mapping | Area **1**, `EZ-DEMO-MAJ`, owned by hub **5** |
| Active rider membership | Rider **4** is active and primary in area **1** |
| Primary barangay coverage | San Roque routes to rider **4** |
| Named buyer demo delivery address | Saved address **2**, same shared location |
| Active staging location | Location **1**, `DEMO-STAGE-01`, area **1**, capacity **10** |
| Existing approved shortcut product | Product **12**, EZiCart Demo Everyday Tote Bag |
| Existing orders preserved | The three existing `READY_FOR_PICKUP` orders remain; use the new order number from Step 3 for this run |

### Verification evidence

- **Passed:** `OrderJourneyDemoSeederTest` — **2 tests / 39 assertions**, including shared address, rider ownership, checkout readiness, repeatability, reactivation of inactive membership, preserved passwords/default addresses/remaining stock, and transactional rollback of profile/routing changes on conflicting hub ownership.
- **Passed:** `LogisticsWorkflowTest::test_professor_order_flow_completes_from_seller_acceptance_through_buyer_confirmation` — **1 test / 85 assertions**, covering seller product creation, admin compliance approval, checkout, seller preparation, pickup handover, hub intake/sorting/storage/pick/dispatch, rider delivery with code/proof/COD, buyer completion, review, and selected ownership/sequence checks.
- **Passed:** PHP formatting, JavaScript syntax and whitespace checks on the changes.
- **Live browser walkthrough:** not performed. Complete the checklist in Section 8 during your demonstration. Automated tests use isolated test records; they do not complete an order in your local demo database.

The tab-session client now keeps its `__tab` selector in the URL so a native refresh selects the same server session before JavaScript executes. The selector chooses a cookie namespace; authentication remains in Laravel's encrypted HttpOnly session cookie.
