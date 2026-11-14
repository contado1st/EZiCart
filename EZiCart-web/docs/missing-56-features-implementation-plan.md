# Implementation plan for the 56 Missing workbook cases

**Plan date:** 14 November 2026; final implementation/scope review 15 November 2026 (Asia/Manila). **Scope:** Laravel web only. **Status:** 53 case revisions implemented, three policy cases scope-reconciled, zero cases pending an implementation/scope outcome. Original classifications and teacher execution flags remain separate. See the [final audit](missing-56-final-seven-audit.md), progress register and historical batch audits for verification and acceptance limits.

This is the execution plan for the 56 rows assessed Missing in the 14 November workbook audit. It narrows the broader [remaining checklist plan](remaining-checklist-features-plan.md) to an exact case inventory, dependencies, acceptance conditions, and delivery sequence. The 56 rows describe overlapping scenarios, rather than 56 independent features.

## Baseline and traceability

| Workbook | Missing cases |
| --- | ---: |
| Seller | 42 |
| Buyer | 9 |
| Rider | 1 |
| Logistics | 4 |
| **Total** | **56** |

The [case inventory](missing-56-case-inventory.md) lists every ID and source sheet/row. The [progress register](audits/missing-56-implementation-progress.json) preserves each scenario, expected result, original assessment, and planned group. All implementation states start as planned or awaiting scope reconciliation. Original teacher checkbox execution remains false.

Comparing the original 70 Missing IDs with the current audit gives 56 still Missing, 5 now Aligned, 6 Different, and 3 Partial. Therefore the decrease of 14 does not mean 14 literal teacher expectations were fully satisfied. The completed original 54 revisions remain a separate inventory.

The current 319-case baseline is 164 Aligned, 4 Partial, 95 Different, and 56 Missing. Keep these historical counts unchanged until fresh implementation evidence supports a new dated assessment. The six Missing Word-checklist items are related context, not six extra rows added to this workbook batch. In particular, exclusive platform-category assignment and rider map links belong to the broader checklist plan and must not expand this batch silently.

## Current-source findings

- `ProductCategoryService` validates shared platform category labels; it does not manage seller-owned category trees.
- `ProductDraftService` already provides private draft persistence and staged private media. Product duplication should extend this path. `ProductController` already protects individual product deletion and availability changes; bulk actions should share those rules.
- `CheckoutPricingService` computes authoritative cent totals and `PlacePurchaseService` creates order snapshots under locks. Sales, flash allocations, bundles, and targeted vouchers must converge on this calculation, including the shared API boundary. Storefront-only price calculation would be incomplete.
- `SellerVoucherController::updateLimits` already edits expiry and recipient/per-buyer limits and prevents rescheduling claimed vouchers. SEL-VOU-009 needs the remaining total-usage-limit and funding protections, rather than a replacement editing system. The audit's blanket statement that no editing endpoint exists is too broad.
- Seller review listing exists; reply persistence/actions are absent. Extend the existing seller review page and review domain.
- `ReturnWorkflowService` already distinguishes physical return receipt, stock inspection, and financial authorization. Seller responses must integrate with these controls.
- `ParcelCorrectionService` already provides audited, evidence-bound corrections. The requested direct override to Delivered is a policy conflict with those controls.
- The order enum has no dedicated Lost/Damaged values. A new exception lifecycle requires explicit transition and financial treatment, not a free-form status update.
- Scheduled commands already handle seller deadlines, roadmap automation, availability, vouchers, and wallet expiry. New scheduled work should use these conventions, with retry-safe actions and transaction-aware notifications.
- Rider geolocation fields were not found in the inspected models/migrations. Nearest-rider dispatch requires a trustworthy location source, freshness rules, and a fallback to manual dispatch.

These findings are source inspection, not executed behavioral tests. No application tests, browser acceptance, migrations, or live database changes were performed for this planning pass.

## Feature groups and delivery order

| Group | Capability | Cases | Release |
| --- | --- | ---: | --- |
| M01 | Seller-owned categories and buyer shop navigation | 14 | 1 |
| M02 | Duplicate products and bulk product actions | 2 | 1 |
| M03 | Scheduled product sales and vouchers on sale prices | 11 | 2 |
| M04 | Hidden/targeted vouchers and safe claimed-voucher edits | 3 | 2 |
| M05 | Flash-sale allocation, display, countdown and reminders | 8 | 3 |
| M06 | Quantity bundles and Buy X Get Y | 2 | 3 |
| M07 | Seller return decisions, escalation and deadline | 5 | 4 |
| M08 | Seller review replies and editing | 2 | 1 |
| M09 | Lifetime per-buyer product limit | 1 | 2 |
| M10 | Scheduled buyer order completion | 1 | 4 |
| M11 | Nearest eligible rider offers and decline cascade | 2 | 5 |
| M12 | Lost/Damaged cases and claims | 2 | 5 |
| M13 | Hosted gateway scenarios under internal wallet scope | 2 | 6, scope reconciliation |
| M14 | Evidence-bound delivery correction | 1 | 6, scope reconciliation |
| **Total** | | **56** | |

Release counts are 18, 15, 10, 6, 4, and 3 respectively. These are work batches, not a promise that every teacher expectation will become Aligned: the three scope-reconciliation rows retain explicit differences unless the governing policy changes.

### Release 1: categories, product operations, review replies — 18 cases

**M01:** Add seller-owned category records with owner, normalized unique name, parent, order, and visibility, plus product membership. Names use the workbook's 1–50 character rule, with duplicate checks after trimming/case normalization. Permit two levels and three store-category memberships per product. Provide reorder, hide/show, membership editing, and a transactional delete preview covering affected children/products. Deleting a category removes its navigation memberships and provides Uncategorized fallback for products with no surviving membership; products with other memberships retain them. Do not change product publication or platform classification.

Buyer shop navigation must honor visibility and ownership on direct category URLs, preserve search/sort/pagination, and avoid duplicate cards. All queries retain existing product, seller approval, and availability scopes. Existing products begin with Uncategorized fallback without rewriting platform category strings. Exclusive platform-category ownership is a separate Word-checklist decision.

**M02:** Add duplicate-to-private-draft with fresh identity and editable copied content. Copy media safely into draft-owned storage so deleting either record cannot break the other. Treat copied SKU values as requiring replacement; never duplicate orders/reviews/claims. Add selectable product rows and bounded bulk deactivate/delete requests. Each selected product uses existing lifecycle rules, with a per-item changed/skipped/error result; unfinished-order products are retained. Whole-request validation/authorization failures must not produce hidden partial effects.

**M08:** Add one persistent seller response per review, authorized through the review's seller order. Provide reply and edit through the existing review page; use documented input limits, record edit history, escape content, and show the seller response to buyers. Do not alter buyer review editing policy as a side effect.

**Final-review precision:** A12 requires one seller reply with editing; it does not specify a separate seller-reply character limit. Use a documented bounded input rule without describing it as a teacher-specified limit. Keep the one-reply rule enforced by database uniqueness and authorize reply edits through the owning order. Duplication creates a new `ProductDraft`, not a publicly visible Product or a reused `submitted_at`/publication token. Reset draft submission metadata and variant identities, stage copied media privately, and clean staged files on failure. Repeat requests must return the same operation result. Bulk actions should process bounded selections in stable ID order and return item outcomes; inspect ownership for the whole selection before starting mutation. Category deletion previews must be revalidated under locks so changed membership cannot invalidate the confirmation.

**Exit evidence:** all 18 source scenarios plus cross-store request attempts, normalized-name collisions, invalid tree cycles, media retention, repeat bulk submissions, one-reply concurrency, and responsive/keyboard use of the changed pages.

### Release 2: authoritative sales, voucher extensions and purchase caps — 15 cases

**M03:** Add scheduled percentage/fixed-price offers, status, product/variant applicability, audit history, and overlap validation. Use inclusive start and exclusive end timestamps, stored consistently and displayed in Asia/Manila. Enforce whole percentages 1–99, fixed sale prices at least PHP 1 and below the original price, and exact integer-cent rounding. Define fixed-price behavior for variants explicitly in the form. Running edits affect future quotes only; existing order lines retain their snapshots.

Resolve effective prices through one shared service used by product ranges/cards, cart, checkout quotes, and placement. Evaluate schedule validity when quoting/placing, so a delayed scheduler cannot leave incorrect prices active. Include offer version/effective allocation in quote fingerprints; stale checkouts require review. Voucher eligible spend is calculated from the actual discounted merchandise amount; shipping and seller/platform funding remain separate.

**M04:** Add public/code-only visibility and a selected-product scope. Code-only offers must not leak through listings, shop panels, recommendations, notification previews, or public serialization; an authenticated buyer may see their own claimed voucher. Manual code entry remains case insensitive. Minimum spend and discount allocation use eligible lines only. Extend existing claimed-voucher edits to allow safe expiry extension and increased total usage limits while locking amount/type/minimum spend/scope after claims. Reconcile and reserve additional sponsor budget under locks; reject insufficient funds and limits below recorded history. Do not silently widen a voucher's product scope.

**M09:** Add an optional product-level lifetime per-buyer cap, shared across variants. Proposed counting rule: paid/open COD orders count, unpaid reservations temporarily reserve capacity, cancellation/expiry releases reservations, and completed purchases remain counted even after returns. Document this rule for the user before activation. Enforce on the server against account history, not cart/session values, and serialize purchases for that buyer/product.

**Final-review precision:** Reject cap violations at Add to Cart and quantity update as well as Buy Now and placement; placement remains authoritative when carts/races are stale. Ordinary seller sale/flash/bundle reductions reduce seller merchandise revenue and the applicable commission base; they do not create a voucher subsidy or require a second wallet debit. Preserve the existing commission rate and one-voucher policy. Persist original/effective prices, discount allocation, promotion version and rounding so reports and refunds can explain the amount actually paid. Unpaid reservations keep their agreed price until their original payment deadline, even if an offer ends meanwhile; payment consumes the stored reservation instead of re-quoting it. Price edits affect newly reviewed quotes only.

For claimed voucher edits, expiry must only extend and the usage limit must only increase. Existing recipient/per-buyer edits may have been used by current users: define the post-claim lock consistently for those existing endpoints and show a clear reason, without rewriting saved claims. Increasing a funded voucher limit must update both the voucher and budget maximum/reserve in one transaction with a new operation key; calling the existing funding reserve method alone is insufficient because it retains an existing budget maximum.

**Exit evidence:** time-boundary prices, no-overlap races, sale edits preserving orders, targeted spend/discount rounding, hidden-voucher exposure checks, funded-limit increases, and purchase-cap races across cart, Buy Now, web/shared API entrypoints and cancellation.

### Release 3: flash sales, bundles and reminders — 10 cases

**M05:** Build flash slots on the Release 2 price resolver with independently limited allocation. One quantity may be split between flash price and the current ordinary/store-sale price, exactly as SEL-FLASH-004/006 require. Validate all source quantity/price limits and concurrent slot overlaps before saving. Lock product, variant, offer and allocation records in a stable order. Reserve allocations for unpaid orders; consume on payment/accepted COD placement; release on unpaid cancellation/expiry. Do not restore promotional capacity for delivered returns after the slot ends.

Persist price segments or explicit line allocations so order, seller screen, invoice, review eligibility, inventory restoration and financial reports agree without treating two price segments as two purchased products/reviews. Show remaining flash quantity and server-derived countdown across product/cart/checkout; stale displays must reconcile with a fresh server quote.

Add opt-in flash reminders with a stored schedule, duplicate-send protection, cancellation when the slot ends/is withdrawn, and links honoring product visibility. Use the existing notification system and channels; real external delivery acceptance remains separately recorded.

**M06:** Add seller-scoped quantity bundles and Buy X Get Y using the same cent-based calculation and allocation snapshots. Count mixed variants as required. Recommended precedence is flash/store effective price, then an explicitly eligible bundle, then eligible voucher, followed by shipping; block incompatible promotion combinations rather than double-applying them. A free item must exist with available stock and its weight contributes to parcel capacity; represent zero merchandise price separately from unit cost, stock and shipping. Removing qualifying quantities must remove the discount/gift before placement.

**Final-review precision:** Fix the default reminder lead time at one minute before the start, with one reminder per subscription/slot version; subscriptions made after that point receive immediate confirmation of the imminent/live slot rather than a false claim of advance delivery. Withdrawn/rescheduled slots invalidate pending sends. Flash allocation cannot exceed sellable inventory; cancelled unfulfilled paid/COD orders may release their allocation only while the slot is still active, within its configured maximum, and without creating a second physical-stock restoration. Expired slots never regain promotional capacity. Half-open time intervals permit adjacent slots without overlap. Define and test ties between overlapping promotions of different kinds and gift quantity multiplication explicitly before enabling those combinations.

**Exit evidence:** last flash unit race, partial flash/normal line split, unpaid expiry restoration, flash versus store-sale fallback, exact PHP 104.55 bundle example, gift qualification/stock/weight, price snapshot exports, and exactly-once reminder handling.

### Release 4: seller-first return handling and automatic completion — 6 cases

**M07:** Add a response stage linked to existing return/dispute records: awaiting seller, seller approved, seller rejected, escalated, deadline approved, and resolved. Keep existing admin-led cases readable and operational. Add rejection reason validation of 10–300 characters, buyer-visible exact reason, one-time escalation, and a three-day response deadline. Seller actions must stop after escalation or deadline action; races use locked state/version checks. Deadline approval approves the return request and next physical/financial review steps; it must not invent parcel receipt or execute a refund automatically. Preserve buyer access to urgent admin assistance and existing evidence, inspection, and refund authorization.

**M10:** Add a retry-safe completion command for eligible Delivered orders seven days after verified delivery. Exclude active disputes/returns/claims, missing proof, and unresolved settlement exceptions. Delegate status changes to `OrderTransitionService` and leave the financial service responsible for fund eligibility. Notify buyer/seller once and record the automatic actor/reason. Recheck a dispute opened at the same time. Teacher wording that implies unconditional automatic earnings release must remain Different where verified funds/approval holds prevent release.

**Final-review precision:** Both seller response and buyer escalation windows are 72 elapsed hours: response from request creation, escalation from seller rejection. Enforce at request time as well as in scheduled processing; define an exact deadline as expired and persist deadline approval once. Use 168 elapsed hours for buyer completion. Do not time out legacy admin-led cases or complete historical delivered orders immediately upon deployment: use an explicit rollout cutoff/eligibility preview and recorded opt-in for pre-cutoff records.

The existing public transition method requires a `User` actor and authorizes Completed only for the owning buyer. Add a dedicated, non-routable system-completion entry inside the transition service that shares state, proof, payment and dispute validation and records a system event. Do not impersonate the buyer, create a privileged account, broaden buyer authorization, or write status directly. Return-request approval, physical receipt, stock inspection, refund execution and payout remain separately audited events. Keep exceptions to the teacher's automatic earnings-release expectation explicit for BUY-TRK-005.

**Exit evidence:** before/at/after three-day and seven-day deadlines, repeated scheduler runs, simultaneous seller/escalation/timeout actions, legacy dispute access, proof/claim blocks, and exact stock/refund/payout reconciliation.

### Release 5: dispatch and shipment claims — 4 cases

**M11:** Add fresh location input and a separate offer record rather than overloading physical parcel assignment/custody fields. Candidate selection must enforce account approval, explicit availability, hub/area/vehicle capacity and workload restrictions before ranking by distance. Require a timestamped, authorized location source; absent/stale coordinates place work in manual dispatch rather than guessing nearest. Keep accepted parcels assigned when a rider becomes unavailable.

Offer one rider at a time, atomically accept, expire/reject and advance to the next eligible candidate, avoid offering the same rider twice in the cycle, and move to Unassigned after three distinct riders decline or expire. Record expired offers separately from explicit declines; use the source workbook's offer timing where the automatic mode applies. Preserve manual dispatch as fallback and expose the candidate/reason history. Coordinate access must be scoped to operational need, with freshness and retention settings.

**Final-review correction:** A14 counts both declines and expiries toward the three-offer limit. Automatic mode uses a 60-second offer, tracks each outcome separately, and returns the job to Unassigned after three distinct candidates decline or expire; fewer available candidates can exhaust the queue earlier. A manual retry starts an explicit new cycle. The automatic candidate limit is fewer than three active jobs per A34, while existing manual workload policy remains separately visible. Apply verified cargo-capacity checks to both. Rank distance from a fresh rider position to the parcel's actual next pickup/custody location, never a buyer destination substituted for a seller pickup. Use a stable rider ID tie-break. Provisional offers reserve available capacity under the same vehicle lock used by manual assignments and hub-transfer work; failed/expired offers release only that reservation. A scheduler/outbox retry must never issue a second live offer for the same job.

**M12:** Add Lost and Damaged incident reporting with constrained transition rules, mandatory damage photo, reason/actor/current custodian, private evidence, and affected-party notification. Separate incident reporting, investigation, and authorized resolution so a mistaken report is recoverable. Block ordinary dispatch/completion/settlement while the incident is unresolved. Start a claim review without manufacturing COD collected funds, seller debt, a wallet refund, or automatic stock restoration. Include affected hub-transfer manifests and rider load accounting; suspected loss must not silently free physical cargo capacity before reconciliation.

**Final-review precision:** Treat an unresolved incident as a shared hold checked by manual and automated transitions, buyer receipt, hub-transfer actions, seller payout requests/approval/settlement and the new completion command. Inspect all current status consumers (enum, transition map, tabs, tracking, analytics, exports, expiry jobs, and API serializers) before adding any stored status. A separate incident record may retain the prior custody stage, but the user-facing Lost/Damaged state must be unambiguous. Historical incident correction and resolution must record who removed the hold and why, without replaying stock, COD or refund actions.

**Exit evidence:** nearest eligible candidate, tie/stale-location behavior, three declines, accept/expire races, unavailable/overweight exclusions, cross-hub permissions, missing damage-photo rejection, incident recovery and funded/unfunded claim outcomes.

### Release 6: account for three policy-sensitive rows

**M13 — BUY-PAY-004/005:** The original rows specifically require a hosted external gateway cancel/closed-browser callback. The authorized internal demo wallet has no such gateway. Document and verify internal equivalents: abandoning the payment screen leaves a retryable To Pay order, re-entry uses the same reservation, a confirmed committed wallet debit is shown after reopening, and retries do not double-charge. Preserve provider-specific original expectations as Different after evidence supports the adapted scope; do not fabricate a provider callback or claim external payment acceptance. External provider integration would require a changed scope.

**M14 — LOG-TRK-002:** Integrate a visible correction request/review action with existing evidence, nonempty reason, approval, custody and proof controls. A reason alone must not mark an undelivered parcel Delivered. Test approved evidence-bound correction and forbidden direct delivery overrides. Retain the original direct-override expectation as Different unless an explicitly reviewed policy permits a different authority model.

These three cases stay in the 56-row inventory for accountability. They cannot be promised as literal Aligned results under the current approved design.

## Verification, rollout and audit

For each release, record modified files, migrations, routes, role permissions, UI changes, exact case IDs, tests actually run, result and residual differences in the progress register and an implementation audit. A case is not delivered merely because a table or button exists.

Use additive schema with nullable/default behavior for existing records. Validate migration up/down/reapply on an isolated database and inspect populated data on a disposable copy before enabling new constraints. Never rewrite historical paid order amounts from current promotions. Background tasks need locking/retry handling and visible failure logs. No new package dependency is assumed; any necessary dependency requires the project's existing approval process.

Run focused feature/service tests per release for ownership, money/stock, replay, deadlines and failure handling. Exercise MySQL concurrency for promotion allocation, offer acceptance and financial mutations before claiming runtime race safety. Frontend/unit and build checks apply to changed assets; browser desktop/mobile acceptance covers tab navigation, 25-row pagination, forms and feedback. Record browser unavailability explicitly if it persists.

At final review, confirm all 56 original IDs appear exactly once, all source expectations are preserved, and all implemented groups have evidence. Run the applicable regression suite for the changed domains, verify seller-to-buyer delivery/return demo flows, and produce a fresh 319-case/127-item assessment. Preserve original teacher documents and their unexecuted checkboxes. Report Aligned, Partial, Different, Missing and Unverified honestly; no forecast of zero Missing is a substitute for assessment.

## Recommended first implementation batch

Begin with Release 1: store navigation categories, duplicate/bulk product actions, and seller review replies. They cover 18 cases and establish reviewable additions before the pricing and money-sensitive work. During that batch, review workbook assumptions for the later pricing, dispatch and return policies and record any approved departures before building them.

## Final plan review

Reviewed against the preserved 56-row source inventory, referenced A12/A14/A31/A34 rules, and current draft, voucher funding, transition and financial services on 14 November 2026. Coverage and grouping remain 56 unique cases across 14 groups and six releases, with no application implementation claimed. Detailed review findings and verification are recorded in [the final plan review](missing-56-plan-final-review.md).

The plan is ready to begin Release 1. Defaults introduced by this plan (purchase-cap counting, promotion combination precedence, reminder lead time, automatic dispatch location freshness and rollout eligibility) must be displayed/documented as project rules and verified in their own release. They are not blanket claims of literal teacher alignment. Hosted gateway callbacks and direct delivery overrides remain explicit scope conflicts; automatic earnings release can remain Different under existing funding/approval controls.
