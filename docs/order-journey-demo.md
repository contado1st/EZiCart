# One complete order demo

## Prepared local data

Seeded on 2026-10-22. Start with an approved product and place the order manually; there is no pre-created demo order, delivery proof or review.

| Sign-in role | Email | Password |
| --- | --- | --- |
| Buyer | buyer@ezicart.com | Password123! |
| Seller | seller@ezicart.com | Password123! |
| Pickup and delivery rider | courier@ezicart.com | Password123! |
| Sorting hub | sorting@ezicart.com | Password123! |
| Marketplace admin, if needed | admin@ezicart.com | Password123! |

These existing local accounts and their password hashes were preserved; the seeder confirmed the password above matches all five accounts. Use separate browser profiles/private sessions, or log out between roles.

- Product: **EZiCart Demo Everyday Tote Bag**, product **#12** in this local database.
- Seller: Sarah Essentials Store.
- Price: **PHP 100**, with **3 units** initially. Purchase **quantity 1** for this demo. Remaining stock allows the public product/review page to stay visible afterward.
- Buyer address: **Demo delivery — Majayjay**, saved address **#2** locally. San Roque, Majayjay, Laguna; house 45, Market Road, 4005.
- Destination area: **Majayjay Demo Delivery Area** (`EZ-DEMO-MAJ`), owned by the seeded sorting hub.
- Primary San Roque rider: the seeded courier.
- Hub staging location: **Demo Majayjay staging bin**, code **DEMO-STAGE-01**; location scan value **EZL:DEMO-STAGE-01**.
- The rider's badge is generated and available in the rider workspace. Parcel labels and the buyer's delivery code are generated during the real workflow; they are not fixed demo values.

## Demonstration sequence

1. **Buyer:** find the demo tote bag, add quantity 1, and make sure the cart contains only that product. Select **Demo delivery — Majayjay** at checkout, choose **COD**, review the displayed shipping charge and total, then place the order. Record its order number. The normal configured shipping charge applies; no voucher or global shipping override was seeded.
2. **Seller:** open that order, confirm it, move it to preparing, then ready for pickup. Schedule pickup using a valid future date/window. Print or display its parcel label.
3. **Hub:** open pickup management and assign the seeded courier to that order.
4. **Rider:** accept/claim the pickup and record arrival at the seller.
5. **Seller:** verify the arriving rider using the rider's badge/QR and confirm handover.
6. **Rider:** scan the order's actual parcel label to confirm physical pickup. Its scan value has the form `EZP:<parcel code>`.
7. **Hub:** scan the parcel into intake. Sort it to the Majayjay destination area.
8. **Hub:** put the sorted parcel into the demo staging bin by scanning the parcel and **EZL:DEMO-STAGE-01**.
9. **Hub:** assign the seeded courier for delivery. **Rider:** accept the delivery assignment promptly; the configured acceptance deadline is 15 minutes. Physical handoff also records acceptance if it occurs before that deadline.
10. **Hub:** pick the parcel out of the staging bin, then confirm the scanned hub handoff using the rider badge and parcel label/manifest. Merely assigning a rider does not release the parcel.
11. **Rider:** scan the parcel to start delivery. **Buyer:** open the order details to display the newly issued delivery code.
12. **Rider:** record the recipient, enter/scan that buyer delivery code, enter the **exact order total** collected as COD, and upload a demonstration delivery-proof image/PDF. Mark delivery complete.
13. **Buyer:** confirm receipt after checking the parcel. On the buyer dashboard, submit the product rating and comment. The order is completed and the review appears on the product page.

The approved listing skips product creation/compliance review during this demonstration, as requested. The seller can inspect it in Products. Editing its name, description, category or image will require compliance review again; an admin must approve it before purchase.

Normal seller response/preparation deadlines, rider capacity, custody checks and delivery-code expiration still apply. If the demo is paused, resume promptly or use the existing recovery actions shown by the application. Refresh the delivery code from the buyer order page if it expires.

## Repeatable setup

```powershell
php artisan db:seed --class=OrderJourneyDemoSeeder
```

This dedicated local/testing seeder requires the existing approved seeded accounts. It does not invoke DatabaseSeeder, reset passwords, change existing account statuses, reset product stock, delete orders, replace another hub's routing, or complete workflow steps. Re-running it preserves progress; it is not a demo reset command. Conflicting routing/coverage/location ownership causes the transaction to stop.

Tests: `OrderJourneyDemoSeederTest` passed **2 tests / 26 assertions**, covering checkout readiness, repeatability, preservation of existing default addresses/passwords/stock, and rollback on conflicting hub ownership. PHP formatting and whitespace checks passed. No browser walkthrough was performed for this seed run.
