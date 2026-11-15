# Missing-case revisions: Batch 1 implementation audit

**Date:** 14 November 2026 (Asia/Manila). **Scope:** Laravel web. **Delivered:** 18 planned case revisions; 38 remain in later batches. This is code delivery with automated evidence, not a teacher-executed Pass/Fail result or full completion of the 56-case programme.

## Implemented behavior

| Group | Cases | Implementation |
| --- | ---: | --- |
| M01 | 14 | Seller-owned categories, two levels, normalized name uniqueness, ordering, visibility, three memberships per product, deletion preview, Uncategorized fallback, and buyer shop filtering |
| M02 | 2 | Duplicate to private draft with independently stored media and reset SKU/submission identity; selectable bulk pause/remove with individual skipped results and replay protection |
| M08 | 2 | One seller response per review, editable with a version check, escaped public display and private administrative edit history |

M01 includes SEL-SCAT-001 through SEL-SCAT-013 and BUY-SRCH-002. M02 covers SEL-PROD-012/016. M08 covers SEL-REV-002/003. The machine-readable [case register](audits/missing-56-implementation-progress.json) records implementation files and verification references against each original case.

Category operations serialize on the seller account, validate ownership, enforce membership/depth limits on direct requests, and preserve existing platform category values. Hiding a parent hides its children from shop navigation without hiding products from normal marketplace search. Deletion rechecks a preview fingerprint, removes the selected category/subcategories, and preserves products and surviving memberships. Products with no store membership appear through the Uncategorized filter.

Product duplication uses ProductDraft rather than a public Product. Cover/gallery/video and active variant images are copied into private draft-owned storage, with file cleanup if copying fails. Existing drafts and their publication validation remain in use. Repeated duplication/bulk requests use a seller-scoped UUID and payload fingerprint; a token reused for different details is rejected. Product lifecycle actions share the inventory service; bulk deletion skips unfinished orders and retains historical products as archived listings. Original individual-removal behavior is preserved.

Review replies are authorized through the review's owning seller order. A unique review constraint and row lock protect one-response behavior; edits reject stale versions. Identical repeated creation returns the existing response. Seller reply editing does not change buyer ratings/comments/media. Administrative audit records retain before/after content. No new notification channel or external dependency was introduced.

## Verification actually performed

| Check | Result |
| --- | --- |
| New isolated feature suite | 16 passed, 163 assertions; category ownership/validation/hierarchy/filtering/deletion, draft media/replay/failure cleanup, mixed bulk outcomes, review replies, and SQLite migration down/up with retained commerce fixtures |
| Related regression suites | 57 passed, 540 assertions: PartialCatalogIntegrationTest, ProductComplianceTest, ProductMediaTest, PartialReviewIntegrationTest, ProfileShopTest and ApplicantDraftWorkspaceTest |
| Combined automated coverage | 73 tests, 703 assertions across the above two runs; no claim of a full-project rerun |
| Production build | Passed; existing optional fontaine warning remains. Initial sandbox child-process EPERM was resolved by running the authorized build outside the sandbox |
| PHP formatting and syntax | Pint passed; PHP syntax passed for all 17 changed/new non-Blade PHP files; git diff whitespace check passed |
| Local schema | Dedicated additive migration applied to configured local MySQL; four tables created |
| Configured database probe | Read-only schema and category-integrity probe passed; all four new tables currently contain zero records |
| Browser discovery | Browser access host unavailable, with no browser-control tabs. Desktop/mobile visual and interactive acceptance not performed |

Focused suite: `storage/logs/missing-56-batch-1-focused.txt`. Regression: `storage/logs/missing-56-batch-1-regression.txt`. Build: `storage/logs/missing-56-batch-1-build.txt`. Read-only runtime result: `storage/logs/missing-56-batch-1-runtime.json`.

The first test run exposed incomplete test fixtures and tab-session account-switch setup; fixtures were corrected using existing required order/media fields and isolated tab sessions. The final suite passed. These fixture corrections did not weaken application validation.

## Database and rollout

Migration: `2026_11_14_212513_create_seller_catalog_workspaces_and_review_replies` creates store_categories, product_store_category, seller_catalog_operations and review_replies. It does not rewrite existing users, products, orders, reviews, account balances, pricing or platform categories.

A private full backup was saved before applying the migration: `storage/app/private/backups/before-missing-56-batch-1-20261114-213635.sql`. No live demo category, draft, reply, account or order was created. The empty new tables let current seller inventories use Uncategorized fallback immediately. Rollback drops the four new tables and therefore discards data entered into these features; preserve/export that data before any future populated rollback. Migration reversal/reapply was verified only in isolated SQLite, not by removing populated tables in MySQL.

## Differences and acceptance limits

- This workspace caps store categories at 200 for bounded selectors and supports 25-row category administration pages. These are documented project choices; the teacher cases do not specify that total category cap.
- Deleting a category retains other existing memberships. Historical products with commerce/compliance records are archived rather than physically deleted and remain visible as archived inventory, following existing history protection. Literal workbook expectations of disappearance may need a fresh Different assessment.
- Replies permit up to 1,000 characters as a project input limit; A12 specifies one reply with editing, not an explicit separate seller-response character count.
- Partial-item promotion refunds, sale/flash prices, return escalation, automated dispatch and shipment claims belong to later batches. No later batch is marked delivered.
- MySQL timed concurrency, all-device visual acceptance and literal teacher fixtures remain unexecuted. The configured MySQL probe checks empty-schema integrity; it does not establish populated workflow/race acceptance.
- Original source audit classifications and teacher result cells are preserved. The register tracks implementation separately; the overall 319-case/127-item reassessment comes after evidence-based review.

## User walkthrough

1. Sign in as an approved seller and open Inventory. Select **Store categories** to create categories and subcategories. Names are unique within your shop.
2. Set a category's position and visibility, then save. Hiding a parent hides its child navigation links too.
3. In Inventory, choose **Categories** beside a product. Choose up to three categories and save; clear the selection to use Uncategorized.
4. Open the seller's public shop and use its store-category links. The existing platform-category/search/sort controls still work within the selected store category.
5. To remove a category, select **Review deletion**, inspect the child categories and affected-product count, then confirm or cancel. If contents changed, reopen the preview.
6. In Inventory, choose **Duplicate to draft**. Review the private copy, enter fresh variant SKUs if needed, and submit through the normal compliance-review flow when ready.
7. Select product checkboxes, choose Pause sales or Remove eligible products, and apply. Review the item-by-item results; unfinished-order products are skipped on removal.
8. Open **Customer reviews**, expand **Reply as seller**, and submit a response. Use **Edit seller response** to update it. Buyers see the escaped response beneath their review.

Next planned batch: M03/M04/M09 (15 cases), covering scheduled sales, voucher visibility/targeting/claimed edits, and lifetime purchase limits.
