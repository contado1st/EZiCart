# EZiCart system workflow revision report

Date: 20 October 2026 (Asia/Manila)

Source specification: `EZiCart_System_Workflow_Audit.md`, validated against the repository. The pasted user request supplied the implementation instructions. The audit supplied findings and proposed requirements; its claims were checked before use.

## A. Executive summary

Implemented a shared checkout calculator and transactional purchase creation, explicit marketplace/logistics administrator boundaries, seller deadlines, inspected return restocking, scanned hub transfers, COD reconciliation, refund and payout records, replacement orders, operational alerts, and retried workflow emails.

COD remains the only supported checkout payment method. Financial execution records describe external cash or bank operations supported by private proof; the application does not initiate bank transfers or provider refunds.

The local MySQL database was backed up into `storage/app/private/upgrade-backups/` before upgrading. Seven pending migrations, including four earlier prerequisites and the three new migrations, were applied successfully. Two existing COD orders received financial snapshots. No remittance, refund execution, or payout was inferred from an approval or order status.

## B. Findings and original state

| Finding | Verified original state | Result |
| --- | --- | --- |
| Multi-seller shipping discrepancy | Checkout displayed PHP 50 once; creation charged PHP 50 for each seller | Shared calculation charges PHP 50 per seller shipment everywhere |
| Foreign product variation | Cart could retain an unmatched ID; checkout checked variation stock without ownership | Relationship, activity, quantity and aggregate stock checked at cart and checkout |
| Stale prices | Session prices determined order totals | Database prices determine the reviewed quote and transactional order snapshots |
| Seller voucher scope | Whole basket qualified and received proportional discounts | Seller vouchers discount only that seller; explicit platform vouchers cover the basket |
| Concurrent voucher redemption | Voucher read outside transaction | Lock and revalidate inside purchase transaction, increment once per purchase |
| Duplicate checkout | No persistent submission identity | Unique buyer/token purchase, request fingerprint, original-result replay |
| Inventory races | Product locks existed, but different variation lines could individually pass a shared stock check | Aggregate demand plus ordered locks and conditional stock updates |
| Cancellation/restocking | Order transitions restored cancellations and all seller returns | Cancellation restoration is locked and idempotent; returns require a persisted sellable inspection decision |
| Seller deadlines | No expiry or reminder command | Configured confirmation/preparation deadlines, reminders and safe expiry |
| Variation edits | Deleted all variations, including when the form omitted them | Update IDs in place; retire removed options and preserve historical references |
| Admin domains | One admin controlled commerce and logistics accounts | Commerce and logistics routes and account scopes are separate |
| Financial lifecycle | COD amount captured, with no remittance/payout execution ledger | Separate collection, remittance, funding, refund and payout records |
| Physical custody | Existing QR, badges, release, proof and recovery controls | Preserved; added source/destination transfer scans and operation blocking in transit |
| Distribution secrets | Local `.env` and repository metadata need protection during export | Added archive exclusions; no credentials printed, changed or committed |

Existing centralized transitions, private proof downloads, delivery-code/COD checks, hub scoping, recovery records, and seller/rider handovers were retained. A baseline run passed 132 tests and 1,099 assertions before changes.

## C. Implementation by workflow

### Checkout and inventory

- `CheckoutPricingService` uses integer centavos, aggregates product/variation demand, and allocates discounts with exact largest-remainder rounding. The allocation cannot exceed any seller subtotal.
- `PlacePurchaseService` creates a parent `Purchase`, independent seller orders, item snapshots, stock deductions, initial financial records, tracking events and notifications in one transaction.
- A quote fingerprint binds the displayed items, variations, prices, discounts and total. Changed pricing or voucher eligibility requires another review. Browser-submitted prices, totals, sellers and statuses are ignored.
- A unique `(buyer_id, checkout_token)` constraint and serialized buyer retries preserve the first result. Reusing a token with changed delivery/quote details is rejected. New purchases receive new tokens.
- MySQL uses row locks and conditional decrements. SQLite acquires its writer lock before checkout reads and has a five-second busy timeout. Tests use independent PHP processes for actual contention.
- The buyer order page shows the parent purchase and its seller orders, including linked zero-charge replacement shipments.

### Commercial lifecycle and returns

- Default confirmation deadline: 24 hours after placement. Default preparation deadline: 48 hours after confirmation. The final four hours trigger a single reminder for that stage.
- Expiry operates only on overdue `PLACED`, `CONFIRMED` and `PREPARING` orders without physical custody. It restores stock once, voids uncollected financial obligations, and records tracking and audit entries.
- Legacy orders receive no retroactive deadline automatically. `orders:initialize-seller-deadlines` assigns a fresh grace period and informs the seller when the operator chooses to enable the policy for older orders.
- Seller return receipt records custody. A separate whole-parcel inspection approves restocking or excludes unsuitable goods. The decision, inspector and timestamp persist. Old returns already restored by previous code are not restocked again.
- Product edits lock the product, preserve variation IDs, retire removed options, and log changes. Replacement images are deleted after commit; failed updates clean up newly uploaded images.

### Administrator permissions

The legacy `admin` value is a compatibility alias for marketplace administration. Existing stored roles and historical migrations were not rewritten. `ecommerce_admin` is the explicit new role. `logistics_admin` receives a separate control-tower workspace. No unrestricted Super Admin was added.

| Capability | E-Commerce Admin / legacy admin | Logistics Admin | Sorting center | Rider | Seller | Buyer |
| --- | --- | --- | --- | --- | --- | --- |
| Seller approval and marketplace moderation | Yes | No | No | No | Own listings | No |
| Logistics account approval/suspension | No | Yes | Existing own-rider operations | No | No | No |
| Platform vouchers and commercial reports | Yes | No | No | No | Own seller vouchers | Eligible use |
| Dispute/refund decisions | Yes | No | No | No | Own replacement fulfilment | Own dispute request |
| Geographic hub ownership | No | Yes, guarded against active parcel reassignment | Existing own-hub rules | No | No | No |
| Cross-hub visibility and transfer planning | No | Yes | Own incoming/outgoing transfers | No | Own orders | Own orders |
| Physical transfer release/receipt | No | No | Matching source/destination hub, scanned | No | No | No |
| Delivery verification | No | No | No | Assigned rider, code, proof and exact COD | No | Own delivery code |
| Cash remittance submission | No | No | No | Own recorded collection | No | No |
| Remittance verification | No | Yes | No | No | No | No |
| Refund/payout approval and external execution recording | Yes | No | No | No | Own payout request | Own refund visibility |

Authorization is enforced by route middleware, domain checks and services. Account/documents, financial evidence, cross-hub operations and order access are checked on the server.

### Logistics and reliability

- The control tower provides cross-hub parcel and exception visibility, account review/suspension, geographic ownership assignment, guarded pickup reassignment, COD reconciliation and transfer planning.
- A transfer requires a parcel physically received at the source, the destination service-area owner, no active rider assignment, and one active transfer per parcel.
- Source release requires retrieving stored goods, scanning the parcel and recording a transport manifest reference. Destination receipt requires its own scan. Commercial status changes and ordinary parcel operations are blocked during an active transfer.
- A released transfer cannot be cancelled administratively. Destination receipt completes custody and returns the parcel to the existing sorting flow.
- Aging alerts use the last recorded operational stage and notify only logistics administrators and the responsible hub. Each observed stale stage is recorded once.
- In-app notifications remain transactional. External channels are dispatched after commit through encrypted queue jobs, with five attempts and increasing backoff. Enqueue failures are logged; external email delivery cannot promise exactly-once delivery.
- Sensitive administrative actions write actor, entity, previous/new values, reason and timestamp to an application append-only audit table. No route permits editing those audit records.

### Financial lifecycle

- Financial snapshots distinguish COD due, collected cash, submitted remittance, discrepancy and verified receipt. Cancellation before collection voids the payable/receivable record while preserving historical order prices.
- Rider submissions require a receipt/reference. A shortage remains a discrepancy and cannot be verified. Only Logistics Admin can verify an exact remittance.
- Seller vouchers are seller-funded. Platform vouchers preserve seller proceeds and record the platform subsidy. Seller payout waits for recorded promotion funding when needed.
- Seller payout progresses through `REQUESTED`, `APPROVED`, then `SETTLED`. Eligibility requires completed fulfilment, seven days since delivery, verified remittance, funded subsidies, and no blocking refund/dispute. External settlement requires private proof and a unique reference.
- Refund decisions create `AUTHORIZED` execution records. `EXECUTED` requires verified collected funds, seller return receipt, an external transaction reference and proof. Approval alone never marks money as returned.
- Approved replacements reserve fresh inventory once and create a linked zero-charge order with a new parcel identifier. The existing logistics flow handles the replacement shipment.
- Reports distinguish completed order value, verified gross commission, funded promotions, executed refunds and settled payouts. The order ledger is paginated.

## D. Architectural decisions and trade-offs

| Decision | Alternative considered | Reason |
| --- | --- | --- |
| PHP 50 per seller shipment | One checkout-wide fee or a new weight/zone engine | Preserves existing fulfilment economics and fixes the display discrepancy without inventing unrequested rates |
| Shared pricing plus a purchase parent | Repeated controller arithmetic or a full checkout framework | One calculation and one persistent retry boundary, using existing Laravel services |
| Existing order/parcel retained | Immediate order/shipment rewrite | Existing QR and custody controls rely on the order. Financial records and transfers can be separated without replacing working operations |
| Explicit admin domains with legacy commerce alias | Rename every existing account or introduce unrestricted super-user access | Preserves historical references and gives new logistics oversight the least privilege it needs |
| Manual external financial execution records | Simulate gateway success or add an unspecified provider | Supports COD evidence/reconciliation and keeps bank execution truthful |
| Whole-parcel inspection and full-order refunds | Unspecified partial-item financial/restocking rules | Provides a clear supported policy; partial workflows need their own business rules and accounting support |

No Composer or npm dependency was added or upgraded. Runtime inspection found Laravel 13.30.1, PHP 8.4.21 and a local MySQL application database. The Composer PHP requirement remains `^8.3`.

## E. Verification

The full suite and isolated database checks passed. Final focused checks cover the subsequent transfer storage/authorization review and scan-based aging calculation.

| Command/check | Result |
| --- | --- |
| Baseline `php artisan test --compact` | Passed: 132 tests, 1,099 assertions |
| Interim full regression run | Passed: 160 tests, 1,285 assertions |
| Interim isolated MySQL suite | Passed: 30 tests, 192 assertions, including three simultaneous-request cases |
| Final `php artisan test --compact` | Passed: 166 tests, 1,326 assertions |
| Final `php tests/Support/run-isolated-mysql.php` | Passed: 34 tests, 220 assertions, including actual MySQL contention |
| Final focused transfer guard test | Passed: 1 test, 19 assertions; denies source-hub storage after release and checks authorization before transfer disclosure |
| `php tests/Support/run-isolated-mysql.php test_cross_hub_transfer_requires_both_scans_and_restricts_operators` | Passed after final review: 1 test, 19 assertions on MySQL |
| Final focused aging / variation / refund checks | Passed individually: 11 / 4 / 12 assertions |
| `php vendor/bin/pint --dirty --test` | Passed |
| PHP syntax checks across changed/new PHP files | Passed: 79 files at the syntax-check pass |
| `git diff --check` | Passed |
| `php artisan view:cache` | Passed |
| `php artisan route:list --path=finance` and `--path=parcel-transfers` | Passed; new endpoints registered |
| `php artisan schedule:list` | Passed; deadline and aging commands registered |
| `php artisan migrate` after private backup | Passed on the local MySQL upgrade, including all earlier pending prerequisites |
| `php artisan finance:initialize-orders` | Passed; two existing COD snapshots initialized |
| `npm run build` | Passed outside the sandbox after its Windows process launch was blocked inside the sandbox |
| Desktop/mobile browser review | Blocked: no connected browser surface was available |

Development failures were corrected: an obsolete test factory assumed a `users.name` column; new fixtures use the established project helper pattern. A new variation test caught dropped variation IDs. A new legacy-deadline assertion was corrected to compare at database second precision. Final review closed a transfer storage race and ensured unauthorized hubs do not receive transfer-state errors before ownership checks. Regression cases were updated to perform the required quote review, use Logistics Admin for rider sanctions, and inspect returns before asserting stock restoration. Existing custody, delivery and inventory assertions were retained.

The isolated MySQL runner creates a uniquely named disposable schema on the local test server, runs migrations and targeted tests with credentials passed privately through process environment, then drops only that schema. It never points tests at the application's existing database. The SQLite contention workers likewise accept only isolated temporary test files.

## F. Remaining limits

- Bank transfers, provider refunds and online payment integrations are not enabled. External transaction evidence is reviewed and recorded by the responsible administrator.
- A refund after an already settled seller payout is blocked until funds are recovered; automated recovery/negative seller balances are not implemented.
- Partial refunds, partial return restocking, multiple parcels per seller order, carrier transport expenses and a general double-entry accounting ledger require additional business specifications.
- Voucher usage counts committed checkouts. Automatic promotional reissue after cancellation is not introduced by this revision.
- Historical orders with insufficient delivery evidence are not assumed collected or remitted. They require operational investigation.
- Marketplace policy amounts are configured in `config/commerce.php`; a general category/settings editor was not introduced.
- Actual SMTP delivery, real banking reconciliation and deployment worker supervision were not exercised by automated tests. Tests use isolated mail/storage/queue settings.
- The successful Vite build retains an existing optional-font optimization notice for `fontaine`. No optional dependency was installed.
- No credentials were rotated. Any credentials exposed in an independently shared archive still require owner-controlled rotation; Laravel key rotation needs a separate data-encryption plan.

## G. Deployment checklist

1. Back up the destination database and private storage. Review pending earlier migrations, especially canonical identifier uniqueness, before deployment. The local database upgrade and private SQL backup are already complete.
2. Deploy code, then run `php artisan migrate --force` in the destination environment. The new migrations add nullable references/defaulted fields so existing order snapshots survive. Rollbacks that drop the new records are destructive after operational data has been written; prefer a forward fix.
3. Run `php artisan finance:initialize-orders` to initialize historical financial snapshots. It does not infer remittance, payout or refund execution.
4. Review legacy unshipped orders, then run `php artisan orders:initialize-seller-deadlines` to give them fresh grace periods. This command sends seller notifications; it was not run against real local accounts during implementation.
5. Provision administrators through `php artisan users:create-administrator EMAIL --role=logistics_admin` or `--role=ecommerce_admin`. The command prompts for a password and logs provisioning. Existing `admin` accounts retain marketplace access. No shared default administrator password was added.
6. Keep private storage writable and outside public links. Financial receipts and existing sensitive documents stay on the `private` disk.
7. Run the scheduler every minute, for example `php artisan schedule:run` under the platform scheduler. Seller deadlines run every fifteen minutes; parcel aging runs hourly.
8. Supervise the configured database queue with `php artisan queue:work --tries=5`; restart workers after deployment and monitor failed jobs. Existing SMTP settings supply workflow email delivery. No new payment credentials are required.
9. Run `npm run build` and refresh application/view caches for the deployed revision. Perform authenticated desktop/mobile operational review when a browser is available.
10. Export a committed release with `git archive`; `.gitattributes` excludes environment secrets and private development/storage directories while retaining `.env.example`. Never distribute a raw working-directory ZIP containing `.env`, `.git`, private uploads or database backups.

## H. Next operational priorities

1. Validate real cashier receipt checking, seller settlement and refund evidence using a controlled COD pilot.
2. Provision separate department administrators and train hub operators on transfer scans and return inspection.
3. Confirm delivery/preparation SLAs and shipping economics with operations before changing `config/commerce.php` defaults.
4. Specify fund recovery after seller settlement, partial adjustments, promotional reissue and transport expenses before expanding financial automation.
5. Add shipment splitting or a provider integration only when required, with provider reconciliation and failure-path tests.

These changes are verified only to the extent recorded above. Automated results do not establish production operational readiness or external financial execution.
