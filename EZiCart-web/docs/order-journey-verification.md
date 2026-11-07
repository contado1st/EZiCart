# Order journey verification

Date: 2026-10-22 (Asia/Manila)

## Scope

Audited the existing seller → buyer → pickup rider → sorting hub → delivery rider → buyer receipt → product review workflow. The application supports COD checkout; cashless payment is intentionally held until a provider is configured.

The existing full-journey feature test was extended to submit the product through the seller endpoint, approve it through the marketplace administrator endpoint, and publish the buyer review after receipt. One obsolete messaging notification test was updated for the existing private-chat contract. No application workflow, schema, dependency, or live account was changed.

## Full journey

| Stage | Verified behavior |
| --- | --- |
| Buyer access | Registration, email verification and login activate the test buyer. |
| Seller listing | Seller creates a product; it enters pending compliance review and is hidden from buyers. |
| Marketplace approval | Administrator approves the listing; buyer can view and add it to the cart. |
| Checkout | Buyer obtains the server quote and submits COD checkout. Server ignores forged ownership, status and price fields. A PHP 100 product plus PHP 50 shipping produces a PHP 150 order. |
| Inventory | Stock moves from 3 to 2 at checkout and remains 2 after fulfillment; later steps do not decrement it again. |
| Seller fulfillment | Seller confirms, prepares, marks ready for pickup and schedules pickup. |
| Pickup custody | Hub assigns pickup; rider accepts and records arrival; seller verifies the rider badge; rider scans the parcel to take possession. |
| Hub processing | Hub scans receipt, sorts to the destination area, creates a staging location and scans putaway. |
| Dispatch | Hub assigns the destination rider, scans the parcel out of storage, and verifies the rider/parcel manifest at release. Delivery cannot start before release. |
| Delivery | Assigned rider scans the parcel, provides the buyer's delivery code, records the exact COD amount and uploads delivery proof. |
| Receipt | Buyer confirms receipt; the order becomes COMPLETED with delivery attempt, assignment and tracking records. |
| Review | Buyer posts a rating/comment; it appears on the buyer dashboard and product page. Early, duplicate and other-buyer reviews are rejected. |

These stages are exercised through Laravel HTTP requests using the actual controllers, services, middleware and database. Storage and notifications are isolated for the full-journey case. The code reads the generated delivery code in the fixture; it does not simulate a human copying or scanning that code from a browser.

## Checks

- Expanded full journey: passed, 1 test / 85 assertions.
- Expanded full journey on a disposable local MySQL database: passed, 1 test / 85 assertions. The existing isolated runner removed its randomly named test database afterward.
- PHP formatting of the modified test: passed.
- Broader seven-file regression run: 164 tests / 1,375 assertions, with 163 passing and one failure in an obsolete messaging notification test.
- Corrected messaging notification test: passed on its focused rerun, 1 test / 4 assertions. The original test omitted the now-required recipient and expected the retired group-chat notification/link. Its replacement assertions verify the saved order-linked private message, intended recipient, and mail/in-app conversation links. No messaging application behavior was changed.
- Private messaging and communication-center regression suites: passed, 17 tests / 168 assertions.
- Whitespace check: passed.

The broad run covered product compliance, checkout pricing/inventory/idempotency, simultaneous last-unit and last-voucher requests, saved addresses, seller preparation, pickup custody, hub ownership and storage, dispatch, exact COD collection, delivery-code limits and proof rollback, failure/retry/return handling, cancellation deadlines, and financial reconciliation. The full seven-file set was not rerun after the test-only correction; the corrected case and expanded journey were verified separately.

No blocking application defect was observed in the tested COD purchase-to-review journey. This conclusion is limited to the exercised scenarios below.

## Operational limits

This is application integration verification, not a production certification. It does not verify the deployed site's browser interactions, camera/scanner hardware, actual SMTP delivery, tunnel/DNS connectivity, or worker uptime. The normal automated suite uses SQLite. The full happy path also passed on MySQL, but simultaneous-request tests were only run on SQLite in this audit; they do not establish MySQL locking behavior under concurrency.

For live operation, the listing must be approved, the destination must resolve to an appropriate service area/hub, riders must be approved and assigned suitable coverage, and scans must match the actual parcel and rider. Multiple hubs require explicit area ownership. Real external notifications require a healthy configured mail transport and queue worker. Deadline/assignment automation requires the scheduler; the existing console schedule registers these commands.

The test verifies recorded COD collection and delivery, not actual physical cash reconciliation or bank payout. Separate financial regression tests cover reconciliation rules and refund/payout records.

## Reproduce

```powershell
php artisan test --compact tests/Feature/LogisticsWorkflowTest.php --filter=test_professor_order_flow
php tests/Support/run-isolated-mysql.php test_professor_order_flow
php artisan test --compact tests/Feature/LogisticsWorkflowTest.php tests/Feature/WorkflowRevisionTest.php tests/Feature/CheckoutIntegrityTest.php tests/Feature/CheckoutConcurrencyTest.php tests/Feature/ProductComplianceTest.php tests/Feature/RoadmapFeaturesTest.php tests/Feature/BuyerAddressTest.php
php artisan test --compact tests/Feature/LogisticsWorkflowTest.php --filter=test_order_message_notification_sends_email_and_in_app_conversation_link
php artisan test --compact tests/Feature/OrderMessagingTest.php tests/Feature/CommunicationCenterTest.php
php vendor/bin/pint --test tests/Feature/LogisticsWorkflowTest.php
```

The full-journey test uses an isolated test database and fake private storage. It does not require seeding or altering the local operational database.
