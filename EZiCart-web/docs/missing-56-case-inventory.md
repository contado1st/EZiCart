# Inventory of the 56 Missing workbook cases

Prepared 14 November 2026; final implementation/scope review 15 November 2026. Laravel web only. Every row below retains its original Missing baseline. **53 case revisions (M01–M12) are implemented; three cases (M13/M14) are scope-reconciled with deliberate differences; zero cases remain unaddressed.** This is not a claim that all original expectations are Aligned or that teacher cases were literally replayed. See the [final seven-case audit](missing-56-final-seven-audit.md) and progress register for evidence and acceptance limits.

See [implementation plan](missing-56-features-implementation-plan.md) and [machine-readable progress register](audits/missing-56-implementation-progress.json) for original expected results, source identity, states and eventual evidence.

| Case | Scenario | Source sheet / row | Group | Release | Planned treatment |
| --- | --- | --- | --- | ---: | --- |
| SEL-SCAT-001 | Create a store category and assign a product | S2 Store Categories / 2 | M01 | 1 | Implement and verify source expectations |
| SEL-SCAT-002 | Empty, spaces-only and over-length names | S2 Store Categories / 3 | M01 | 1 | Implement and verify source expectations |
| SEL-SCAT-003 | Duplicate category name in the same store | S2 Store Categories / 4 | M01 | 1 | Implement and verify source expectations |
| SEL-SCAT-004 | Same category name in a different store | S2 Store Categories / 5 | M01 | 1 | Implement and verify source expectations |
| SEL-SCAT-005 | Rename a category to a duplicate name | S2 Store Categories / 6 | M01 | 1 | Implement and verify source expectations |
| SEL-SCAT-006 | Rename and reorder categories | S2 Store Categories / 7 | M01 | 1 | Implement and verify source expectations |
| SEL-SCAT-007 | Hide a category | S2 Store Categories / 8 | M01 | 1 | Implement and verify source expectations |
| SEL-SCAT-008 | Show a hidden category | S2 Store Categories / 9 | M01 | 1 | Implement and verify source expectations |
| SEL-SCAT-009 | Delete a category that still has products | S2 Store Categories / 10 | M01 | 1 | Implement and verify source expectations |
| SEL-SCAT-010 | Delete a parent category with subcategories | S2 Store Categories / 11 | M01 | 1 | Implement and verify source expectations |
| SEL-SCAT-011 | Subcategory nesting at the depth limit | S2 Store Categories / 12 | M01 | 1 | Implement and verify source expectations |
| SEL-SCAT-012 | Product in several store categories at the limit | S2 Store Categories / 13 | M01 | 1 | Implement and verify source expectations |
| SEL-SCAT-013 | Store category is separate from platform category | S2 Store Categories / 14 | M01 | 1 | Implement and verify source expectations |
| SEL-PROD-012 | Duplicate a product | S3 Product Mgmt / 13 | M02 | 1 | Implement and verify source expectations |
| SEL-PROD-016 | Bulk deactivate and bulk delete with a mixed selection | S3 Product Mgmt / 17 | M02 | 1 | Implement and verify source expectations |
| SEL-DISC-001 | Create a scheduled percentage sale | S6 Discounts / 2 | M03 | 2 | Implement and verify source expectations |
| SEL-DISC-002 | Sale goes live at the exact start time | S6 Discounts / 3 | M03 | 2 | Implement and verify source expectations |
| SEL-DISC-003 | Sale ends at the exact end time | S6 Discounts / 4 | M03 | 2 | Implement and verify source expectations |
| SEL-DISC-004 | Invalid sale dates | S6 Discounts / 5 | M03 | 2 | Implement and verify source expectations |
| SEL-DISC-005 | Discount percentage at the limits | S6 Discounts / 6 | M03 | 2 | Implement and verify source expectations |
| SEL-DISC-006 | Fixed sale price at or above the original | S6 Discounts / 7 | M03 | 2 | Implement and verify source expectations |
| SEL-DISC-007 | Overlapping sales on the same product | S6 Discounts / 8 | M03 | 2 | Implement and verify source expectations |
| SEL-DISC-008 | Sale on a product with variants | S6 Discounts / 9 | M03 | 2 | Implement and verify source expectations |
| SEL-DISC-009 | Edit a running sale | S6 Discounts / 10 | M03 | 2 | Implement and verify source expectations |
| SEL-DISC-010 | End a running sale now | S6 Discounts / 11 | M03 | 2 | Implement and verify source expectations |
| SEL-VOU-003 | Code-only voucher is hidden | S7 Store Vouchers / 4 | M04 | 2 | Implement and verify source expectations |
| SEL-VOU-008 | Voucher for selected products only | S7 Store Vouchers / 9 | M04 | 2 | Implement and verify source expectations |
| SEL-VOU-009 | Edit a voucher buyers already claimed | S7 Store Vouchers / 10 | M04 | 2 | Implement and verify source expectations |
| SEL-FLASH-001 | Create a flash sale in a time slot | S8 Flash & Bundles / 2 | M05 | 3 | Implement and verify source expectations |
| SEL-FLASH-002 | Flash quantity and price validation | S8 Flash & Bundles / 3 | M05 | 3 | Implement and verify source expectations |
| SEL-FLASH-003 | Price returns to normal when flash quantity runs out | S8 Flash & Bundles / 4 | M05 | 3 | Implement and verify source expectations |
| SEL-FLASH-004 | Order more than the remaining flash quantity | S8 Flash & Bundles / 5 | M05 | 3 | Implement and verify source expectations |
| SEL-FLASH-005 | Flash sale ends at the slot end | S8 Flash & Bundles / 6 | M05 | 3 | Implement and verify source expectations |
| SEL-FLASH-006 | Overlapping store sale and flash sale | S8 Flash & Bundles / 7 | M05 | 3 | Implement and verify source expectations |
| SEL-FLASH-007 | Bundle deal item counting | S8 Flash & Bundles / 8 | M06 | 3 | Implement and verify source expectations |
| SEL-FLASH-008 | Buy X Get Y | S8 Flash & Bundles / 9 | M06 | 3 | Implement and verify source expectations |
| SEL-RET-003 | Reject without a reason | S10 Returns / 4 | M07 | 4 | Implement and verify source expectations |
| SEL-RET-004 | Reject with a reason | S10 Returns / 5 | M07 | 4 | Implement and verify source expectations |
| SEL-RET-005 | Escalate to a dispute | S10 Returns / 6 | M07 | 4 | Implement and verify source expectations |
| SEL-RET-006 | No response within 3 days | S10 Returns / 7 | M07 | 4 | Implement and verify source expectations |
| SEL-REV-002 | Reply to a review | S11 Reviews & Chat / 3 | M08 | 1 | Implement and verify source expectations |
| SEL-REV-003 | Second reply and edit reply | S11 Reviews & Chat / 4 | M08 | 1 | Implement and verify source expectations |
| RID-JOB-004 | Job goes to Unassigned after 3 declines | R3 Job Assignment / 5 | M11 | 5 | Implement and verify source expectations |
| BUY-SRCH-002 | Store page with store categories | B3 Browse & Search / 3 | M01 | 1 | Implement and verify source expectations |
| BUY-PDP-005 | Per-buyer purchase limit | B4 Product Detail / 6 | M09 | 2 | Implement and verify source expectations |
| BUY-PDP-006 | Sale price, countdown and vouchers shown | B4 Product Detail / 7 | M05 | 3 | Implement and verify source expectations |
| BUY-VOU-011 | Voucher on items already on sale | B7 Vouchers / 12 | M03 | 2 | Implement and verify source expectations |
| BUY-PAY-004 | Buyer cancels at the gateway | B9 Payment / 5 | M13 | 6 | Internal-wallet equivalent; gateway expectation remains explicit |
| BUY-PAY-005 | Browser closed on the gateway | B9 Payment / 6 | M13 | 6 | Internal-wallet equivalent; gateway expectation remains explicit |
| BUY-TRK-005 | Automatic completion | B10 Order Tracking / 6 | M10 | 4 | Implement and verify source expectations |
| BUY-RET-005 | Track request status | B12 Return & Refund / 6 | M07 | 4 | Implement and verify source expectations |
| BUY-CHAT-004 | Flash-sale reminder | B14 Chat & Notifs / 5 | M05 | 3 | Implement and verify source expectations |
| LOG-DISP-001 | Auto-assign to the nearest eligible rider | L3 Dispatch / 2 | M11 | 5 | Implement and verify source expectations |
| LOG-TRK-002 | Manual override needs a reason | L5 Tracking & Exceptions / 3 | M14 | 6 | Evidence-bound correction; direct override remains explicit |
| LOG-TRK-004 | Mark a parcel Lost | L5 Tracking & Exceptions / 5 | M12 | 5 | Implement and verify source expectations |
| LOG-TRK-005 | Mark a parcel Damaged with a photo | L5 Tracking & Exceptions / 6 | M12 | 5 | Implement and verify source expectations |

**Coverage:** 56 unique cases; Seller 42, Buyer 9, Rider 1, Logistics 4. Mapping verified against the current audit source with no missing IDs, duplicate IDs, or unmapped cases. No teacher case was executed by this planning pass.
