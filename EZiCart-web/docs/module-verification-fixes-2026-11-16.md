# Module verification fixes - 16 November 2026

Follow-up to [the baseline audit](final-all-modules-verification-2026-11-16.md). Scope remains Laravel/web and shared API behavior; no Flutter schema or response contract was changed.

## Implementation and status

| Baseline finding | Work completed | Current status |
| --- | --- | --- |
| F01: missing product weights | User-authorized random **school-demo** unit weights assigned to the seven affected products, with pre-change backup, original values and seven administrative audit entries. All eight marketplace-visible products now produce individual delivery quotes. Seller inventory also shows a missing-weight warning and an owner-scoped, paginated filter. | Resolved for the seven demo listings. These are synthetic weights, not actual measurements. |
| F02: mixed-weight quote understates load | Versioned pricing checks **every selected line** before totaling. A missing/zero weight cannot be masked by another product. Web redirects to the cart with the reason; API retains its existing 422 error structure and does not issue checkout credentials. Legacy flat-fee compatibility remains unchanged. | Fixed, with mixed-order/null/zero-weight, valid aggregate and legacy regression coverage. |
| F03: two failing regression expectations | Buyer chat assertion now uses the current accessible Chat label. Withdrawal retry fixture now sends the real form's `wallet_form=withdrawal`. Unread isolation, retry, changed-payload rejection and balance assertions remain in place. | Resolved; targeted and full suites pass. |
| F04: historical notification failures | Added `php artisan operations:readiness --json`, a read-only diagnostic. It authenticates/decrypts encrypted payloads without unserializing or executing jobs, and exposes only internal IDs/statuses. | **Not cleared**: 15 historical jobs remain; 10 are decryptable and require compatibility/relevance review, 5 have unreadable or legacy payloads. No emails were replayed and no failure records were deleted. |
| F05: incomplete legacy credentials/papers | Staff profiles show missing/expired license warnings and actual document availability, with links through existing authorized private preview routes. Cross-admin-domain and buyer access remain forbidden. Readiness command lists affected internal IDs and document types. | **Data still required**: 7 license dates need completion/review and 14 approved accounts have unavailable papers (3 sellers, 7 couriers, 4 hubs). No recoverable originals were found in public storage. Nothing was fabricated. |
| F06: 20 kg tariff and old allocation snapshots | Diagnostic output records effective maximums and explicitly explains historical compatibility. Existing tariff refusal above supported weight remains active; historical snapshots were not changed. | Existing 20 kg policy preserved. Supporting new heavy-load orders needs reviewed operating rates; old 21/22 kg demo orders and the one legacy snapshot are not silently repriced. |
| F07: probe fixtures and misleading success exit | Explicit availability added to transport/return fixture riders. API controller invocation now resolves injected services through the container. Standalone exception handler is restored after Laravel bootstrap and reports incomplete results with exit 2. MySQL runner uses direct PHPUnit with a 512 MB test-process limit, streams output, uses an exact 39-case default filter, reports exceptions as failure and allows 1,200 seconds. | All 12 original probes pass. MySQL result below. |

The readiness command deliberately returns exit **1** while operational records/failures still need attention. A passing code suite does not erase that status. It sends, deletes, credits and requeues nothing.

## Demo data change

The user explicitly requested random weights after being asked for actual measurements. Only these product IDs were changed:

| Product ID | Assigned unit weight (kg) |
| --- | --- |
| 1 | 0.800 |
| 4 | 0.400 |
| 5 | 2.000 |
| 7 | 0.900 |
| 8 | 0.700 |
| 9 | 1.600 |
| 13 | 1.000 |

Private recovery artifacts:

- Database backup: `storage/app/private/backups/before-module-audit-fixes-20261116-140912.sql`.
- Assignment receipt, including original values: `storage/app/private/backups/module-demo-weights-20261116.json`.
- Audit action: `demo_product_weight_assigned`, with a null system actor and an explicit user-request/synthetic-data reason.

The local/sandbox-only assignment script refuses a second run when its receipt exists and preserves any weight already supplied. Prices, stocks, orders, packed weights, credentials, wallet balances, ledger entries, rate approvals and historical allocations were not edited. Actual packed parcel weight is still required before pickup. Other nonvisible/unarchived listings can still need weight; the seller filter exposes these without deleting them.

## Verification

All command artifacts below are local under `storage/logs/`.

| Check | Result | Artifact |
| --- | --- | --- |
| Targeted price/readiness/communication/withdrawal coverage | **34 passed; 260 assertions** | `module-fixes-20261116-targeted.xml` |
| Full backend suite | **966 passed; 8,851 assertions, zero failures/errors/skips** | `module-fixes-20261116-full.xml` |
| MySQL checkout, checkout concurrency and workflow set | **39 passed; 258 assertions**, completed in about 673 seconds; no unintended return-workflow cases included | `module-fixes-20261116-mysql.txt` |
| Original audit probe | **12/12 checks passed, no probe errors** | `module-fixes-20261116-probes.json` |
| Frontend regression suite | **134 passed, zero failures/skips** | `module-fixes-20261116-frontend.txt` |
| Current configured database quotes | **8/8 marketplace products quoted; zero visible listings missing weight; mixed missing-weight quote refused** | `module-fixes-20261116-quote-check.json` |
| Historical document recovery inspection | **No recoverable missing originals in public storage** | `module-fixes-20261116-document-recovery.json` |
| Operational data diagnostic | **Needs attention**, as described in F04/F05 | `module-fixes-20261116-readiness.json` |
| Formatting / syntax / Blade compilation | **Pint passed; 12 changed PHP files linted without errors; all Blade templates compiled** | `module-fixes-20261116-syntax.json`, `module-fixes-20261116-views.txt` |

Browser retry reached Chrome's accessibility tree, but input failed with unavailable window geometry and then detected concurrent user activity. No affected screen was visually certified. The browser access host is also unavailable. The affected Blade responses and access controls are covered by HTTP tests; real device camera, SMTP delivery, public-domain layout and Flutter device checks remain outside the automated result.

The successful MySQL run used a newly created disposable schema and removed it afterwards. No test-schema connections remained in the final inspection. MySQL checkout races are covered by this run; wallet race fixtures remain SQLite-specific and are included in the full suite, not presented as MySQL wallet-race certification. The longer timeout accommodates repeated isolated-schema migrations instead of hiding timeout failures.

## Remaining acceptance work

1. Obtain genuine expiry dates and missing original papers (or a verified document backup). Existing approval records are preserved; missing historical data is not treated as verified just because a status says approved.
2. Review historical notifications privately. An unreadable encrypted payload needs compatible prior encryption material or an explicit, separately reviewed decision about obsolete notifications. Decryptability alone is not permission to send an old status update.
3. If heavy shipments are intended, create new reviewed tariff versions with supported weight brackets and costs. Do not alter old approvals or financial history to remove a warning.
4. Complete visual/hardware/mail acceptance checks in a controllable browser and designated test environment.
