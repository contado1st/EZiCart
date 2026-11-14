# Plan: remaining checklist features

**Prepared:** 14 November 2026 (Asia/Manila)  
**Scope:** Laravel web application only; mobile application excluded.  
**State:** Assessment and implementation plan only. No application behavior, schema, configuration, fixture, or teacher checkbox was changed for this plan.

**Workbook execution scope:** the [56-case implementation plan](missing-56-features-implementation-plan.md) now provides the exact Missing-case inventory, six delivery batches, scope reconciliations, and progress register. Use that narrower plan for this implementation batch; this document remains the broader checklist roadmap.

## 1. Purpose and baseline

The completed “54 partial revisions” are a separate scope: the final review records 54 delivered revisions with passing automated regression checks. This does not mean the entire teacher checklists are complete.

The latest checklist assessment at `docs/audits/2026-11-14/checklist-assessments.json` contains 127 role-checklist items:

| Assessment | Count |
| --- | ---: |
| Aligned | 70 |
| Partial | 32 |
| Missing | 6 |
| Different | 15 |
| Unverified | 2 |
| Optional | 2 |

The four workbook audits cover 319 scenarios: 164 Aligned, 4 Partial, 95 Different, and 56 Missing. Workbook scenarios overlap with one another and with role-checklist items; they must be implemented as coherent capabilities rather than counted as 56 separate features. The auditable per-case details and source references remain in `docs/audits/2026-11-14/`.

This plan prioritizes capabilities assessed **Missing**, then closes material **Partial** gaps where they do not require changing a confirmed business policy. Items assessed **Different** are tracked as policy decisions, not assumed defects. **Unverified** items require acceptance evidence rather than code by default.

## 2. Guardrails

1. Preserve current working behavior and extend existing services, policies, notification pathways, audit logging, and Blade layouts. Prefer additive schema and reversible data migrations.
2. Keep server-side authorization, ownership scoping, validation, transaction boundaries, and audit records for every new workflow. Never rely on hiding a button as access control.
3. Do not relax parcel proof, wallet ledger integrity, payout holds, category constraints, or seller/rider approval rules to make a checklist scenario pass.
4. Do not create demo credits, orders, profiles, seller data, or teacher fixture records as part of implementation. Use isolated automated fixtures and document any manual acceptance data separately.
5. Preserve the original teacher files and their checkbox state. Update only project-owned audit/progress documents after code and evidence exist.
6. Build and verify one dependency-sized phase at a time. Run focused tests after each phase and the appropriate regression suites at the end. Browser/device checks require an available browser surface and must be reported separately from automated tests.

## 3. Reconciliation required before implementation

The checklist has requirements that can be reconciled cleanly and requirements that encode different policies. Use separate concepts where that preserves both useful behaviors; do not make a destructive choice implicitly.

| Topic | Checklist findings | Plan treatment |
| --- | --- | --- |
| Seller categories | Role checklist expects one exclusive platform category assigned by an administrator. Seller workbook expects seller-owned, nested store-navigation categories, multiple memberships per product, and separation from the platform category. | Model platform taxonomy/assignment separately from seller-owned shop navigation categories. Seller-owned categories must never become platform categories. Enforce exclusive platform assignment only if that policy is confirmed and existing sellers/products have a safe migration mapping. |
| Checkout | Workbook asks multi-seller parent checkout/splitting. Project instructions established one-shop-per-checkout. | Keep the existing one-shop checkout. Record split checkout as **Different / policy conflict**, not a missing implementation, unless the user explicitly changes the earlier policy. |
| Payment channels | Workbook requests hosted card/e-wallet/bank callback and cancellation scenarios. The user chose internal demo credits and admin-approved recorded demo deposits instead of external providers. | Keep the internal demo wallet scope. Do not add or claim hosted provider behavior. Preserve provider-specific rows as an intentional difference. |
| Return and settlement | Workbook asks seller-first reply/escalation and faster/immediate release in some cases. Existing safeguards use admin-led disputes and controlled holds/refunds. | Add seller response and escalation as a distinct dispute stage only after mapping deadlines, allowed evidence, refund authorization, and existing admin controls. Do not release funds earlier as a side effect. |
| Rider availability/dispatch | Manual availability and capacity are implemented; teacher scenarios request nearest-rider automatic cascade and login-presence coupling. | Preserve manual availability and capacity protections. Plan dispatch automation as an optional, capacity-aware enhancement; do not infer online state from login alone. |
| Rates and compensation | Rate workflow exists but no approved tariff is active. | Implement admin configuration and reporting improvements without activating invented live rates. Clearly indicate unavailable rates until an authorized rate is approved. |
| Store bans, account deletion, penalties | Checklist terminology includes permanent bans/deletes and penalties, while current moderation uses reversible suspension and audited decisions. | Prefer reversible moderation, warnings, and account-retention/anonymization. Any permanent ban, financial penalty, or hard delete needs an explicit policy and retention decision before implementation. |

## 4. Proposed implementation phases

### Phase A — Seller/category foundation

**Checklist coverage:** role checklist 1.2.5–1.2.8 and 1.4.3; seller workbook SEL-SCAT-001–013; buyer store-navigation cases; category-constrained seller product scenarios.

**Plan:**

- Inventory current category values, product references, seller business-category fields, route names, approval flow, and buyer/store query behavior.
- Define two non-overlapping domains: administrator-managed platform taxonomy and seller-managed store categories. Platform taxonomy supports parent/child labels; seller categories support the workbook's store navigation needs and at most two nesting levels.
- Add an admin approval-time platform-category assignment if the exclusive assignment policy is approved. Add a uniqueness constraint at the correct scope; prevent deleting/reassigning a category while referenced without an explicit migration plan.
- Add seller CRUD for store categories (unique per store, rename/reorder/show/hide), product-to-store-category membership with the checklist limit, and safe delete confirmation that moves affected memberships to Uncategorized without changing product status.
- Keep platform product category and store category relationships independent. Scope every seller operation to the authenticated store and validate direct requests.
- Provide a preview and dry-run migration for existing category strings before any data conversion. Preserve current platform category values.

**Acceptance:** category uniqueness and nesting, cross-store isolation, limits, hidden category URLs, product visibility/search independence, deletion-to-Uncategorized, and product/platform-category preservation are tested in service, request, and feature tests. Admin category assignment must reject a category already reserved when exclusive assignment is enabled.

**Dependency / decision:** approve the exclusive platform category-per-seller rule and decide how existing sellers map to current categories before enabling database uniqueness. Seller-owned store categories can be built separately without that decision.

### Phase B — Seller product operations and promotions

**Checklist coverage:** SEL-PROD-012/016; SEL-DISC-001 onward; BUY-PDP sale/per-buyer-limit scenarios; BUY-CHAT flash reminder; low-stock and boundary-validation partial items.

**Plan:**

- Add duplicate-to-private-draft with fresh IDs/SKUs and no copied order/review/stock history.
- Add bulk deactivate/delete with per-item outcomes. Refuse deletes for products with unfinished orders; never partially delete related commerce records.
- Add scheduled seller price sales with start/end timestamps, overlap prevention, stock/price snapshot consistency, and a single authoritative price used in listing, cart, checkout, analytics, and refunds.
- Add flash-sale quantity reservation/countdown only after sale pricing works; share inventory locking with normal checkout to prevent oversell. Add reminders only for opted-in buyers and valid sale schedules.
- Add a configurable lifetime per-buyer product purchase cap with transactional enforcement across prior completed/pending purchases and variants; define refund/cancellation treatment before coding.
- Add threshold-crossing low-stock alerts with deduplication/re-arm rules. Tighten product validation only to agreed checklist limits after checking seeded/product compatibility.

**Acceptance:** time-bound pricing at both boundaries, concurrent checkout/stock cases, duplicate submissions, bulk mixed eligibility, product visibility, notification deduplication, and purchase-cap behavior on cancellation/refund are covered. No UI price may differ from server checkout totals.

### Phase C — Buyer service and communication gaps

**Checklist coverage:** seller-first returns/escalation, seller review replies, seller/rider ratings, order completion automation, SMS/real-time notification differences, profile/field-validation partials.

**Plan:**

- Add seller response state and reason to return/dispute cases, bounded response deadlines, buyer escalation, and admin resolution. Preserve existing dispute records and financial approval gates.
- Add seller replies to product reviews with moderation, length limits, one reply policy, edit/audit history, and buyer notifications.
- Decide whether seller and rider ratings are separate entities or dimensions of a completed order review; prevent duplicate ratings and define public aggregation/privacy rules.
- Add scheduled automatic completion only if the exact deadline, dispute freeze, proof-of-delivery precondition, and payout interaction are approved. Existing holds remain authoritative.
- Address validation/profile partials by matching the accepted checklist limits with backward-compatible validation messages and no unintended account lockout.
- Keep external SMS and hosted payment callbacks outside the demo-wallet scope; accurately label in-app/email channels and delivery status.

**Acceptance:** deadline boundary, seller timeout, escalation, duplicate actions, dispute freezes, privacy, refund ledger, and payout invariants have targeted tests. Scheduler commands must be idempotent and safe to retry.

### Phase D — Logistics and rider workflow gaps

**Checklist coverage:** map/navigation 5.1.4, multi-stop views, nearest-rider offer cascade, Lost/Damaged exception cases, seller-specific remittance reporting, and unserviceable-area partials.

**Plan:**

- Add a safe map/directions link generated from authorized pickup/drop-off coordinates or encoded address text. Avoid exposing unrelated users' addresses; do not require a third-party map API key for basic external directions links.
- Add a grouped multi-stop itinerary for existing separate assignments; retain parcel-level scan/custody actions and per-stop capacity checks.
- Design capacity/availability/area-aware dispatch suggestions as a recommendation queue first. Automatic assignment/cascade requires approved timeout, tie-break, decline and reassignment rules; never assign unavailable, suspended, overloaded, or already-custody-conflicting riders.
- Add explicit Lost and Damaged exception states only with evidence, actor/reason, custody reconciliation, notifications, and an authorized claim/refund resolution path. A hub must not mark an item Delivered without delivery proof.
- Add seller filter to remittance and delivery reports while preserving the caller's role scope and export totals.
- Block checkout for unsupported destinations when serviceability/rate configuration is authoritative; avoid inventing tariffs or breaking the current demo pricing mode.

**Acceptance:** direct request authorization, location privacy, map-link encoding, stop ordering, total cargo checks, race/decline scenarios, exception recovery, seller report scoping, and no-service checkout behavior receive focused tests.

### Phase E — Admin moderation, finance, and reporting gaps

**Checklist coverage:** seller drilldown, user reports, payout statements, configurable minimum/single pending payout rules, user moderation breadth, penalties/warnings, and admin override wording.

**Plan:**

- Add an admin seller overview that links existing store, products, orders, and analytics without duplicating or widening data access.
- Add filtered user-account reports with privacy-aware fields, audit log, and CSV/XLSX/PDF output consistent with current export standards.
- Add dedicated payout schedule/history statements and explicit configurable minimum/single-pending-request policy if approved; retain ledger reservation, independent approval, and demo-mode labels.
- Add a reversible warning/violation record before any financial penalty. Define appeal, expiry, visibility, and admin jurisdiction; no automatic financial deduction without a separately approved policy.
- Make cancellation/refund/override screens explain available guarded actions. Do not add arbitrary state mutation; corrections must continue to use evidenced transitions and audit.
- Implement a reversible store-ban state only after defining seller appeal/reinstatement and what happens to active orders, refunds, vouchers, and product pages.

**Acceptance:** admin-domain permissions, seller/user scoping, exports, financial reconciliation, independent approval, appeal/audit history, and active-order handling are covered.

### Phase F — Acceptance, reliability, and checklist closure

**Checklist coverage:** dashboard reconciliation, session timeout, responsive/browser checks, performance/broken links, email delivery, and literal teacher scenarios.

**Plan:**

- Verify dashboard totals against direct scoped queries for each role and known fixtures.
- Review idle-session policy against the workbook's 30-minute expectation; test real browser expiry and separate-tab session behavior before changing runtime settings.
- Run browser acceptance on desktop, tablet, and mobile widths; inspect core buyer/seller/rider/logistics/admin journeys and all new flows.
- Run representative page-load, link, media, and error-message checks. Separate code defects from domain/network/CDN configuration.
- Test scheduler/queue execution and captured email delivery in a controlled non-production environment. Keep SMS/gateway claims explicitly unsupported until integrated.
- Reassess all 127 checklist items and all 319 workbook rows from source, preserve teacher checkboxes, attach evidence, and publish a new dated audit with remaining policy differences and unverified checks.

## 5. Priority order and release gates

Recommended order:

1. Phase A seller/platform category model and migration preview (foundational for category-dependent product and store work).
2. Phase B product lifecycle, sale pricing, inventory protections, and seller operational gaps.
3. Phase C return/review/order-completion workflows.
4. Phase D logistics exceptions, navigation and report gaps.
5. Phase E admin reporting/moderation/finance completeness.
6. Phase F browser and checklist acceptance; run throughout, with final closure after feature phases.

Each phase is independently reviewable. Do not start irreversible data conversion or activate policy-sensitive controls until its gate is met. Keep additions behind explicit configuration where rollout may affect existing accounts or pricing.

## 6. Cross-cutting verification plan

For every implementation phase:

- Add focused feature/service tests for happy path, validation, authorization, cross-role/tenant isolation, replay/double-submit, concurrency where relevant, rollback, and audit/notification behavior.
- Use isolated test databases and explicit fixtures; do not run destructive seeders against the configured application database.
- Check migration up/down/reapply on a disposable copy and inspect indexes/foreign keys. Reconcile existing production-like rows before imposing non-null/unique constraints.
- Run PHP syntax/style checks and relevant frontend tests/build where touched, then the appropriate full regression set.
- Perform manual browser acceptance only when a browser surface is available; record viewport, route, actor role, and observed result. Do not equate backend tests with visual acceptance.
- Update source-linked checklist assessments only after evidence exists; retain Different, Optional and Unverified classifications when still accurate.

## 7. Scope that remains intentionally different or requires a separate decision

- Multi-seller parent checkout and split orders conflict with the established one-shop checkout rule.
- Hosted card/bank/e-wallet provider flows conflict with the chosen internal demo wallet scope.
- Immediate seller settlement conflicts with the established delivery hold, verified funding, and approval controls.
- Permanent hard-delete, arbitrary delivery overrides, and automatic rider assignment require policy and risk decisions before they can be considered implementation work.
- Teacher workbook results remain unexecuted until the literal teacher scenarios are accepted and recorded by the project owner/teacher.

These are not silently included in “Missing” implementation work. The implementation audit must call them out as differences, not report them as fixed.

## 8. Source documents reviewed

- `docs/audits/2026-11-14/checklist-assessments.json`
- `docs/audits/2026-11-14/role-checklist-audit.md`
- `docs/audits/2026-11-14/seller-test-case-audit.md`
- `docs/audits/2026-11-14/buyer-test-case-audit.md`
- `docs/audits/2026-11-14/rider-test-case-audit.md`
- `docs/audits/2026-11-14/logistics-test-case-audit.md`
- `docs/teacher-requirements-web-reaudit-2026-11-14.md`
- `docs/final-54-revisions-check.md`

