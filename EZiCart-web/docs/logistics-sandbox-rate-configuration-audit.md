# Logistics sandbox rate configuration

Configured on 16 November 2026 (Asia/Manila), in the existing local `ezicart_db` database. This implements the owner's request to configure logistics compensation for school testing. Financial mode remains `sandbox`; no real cash payout is enabled.

## Active configuration

The existing `finance:sandbox tariffs` command created four explicitly labelled example schedules. The normal rate-management service recorded approval and activated versioned delivery pricing (`commerce_settings.shipping_model = 1`). No financial service, API contract, migration, mobile screen or dependency changed.

| Active version | Route |
| --- | --- |
| 5 | Same barangay |
| 6 | Same municipality |
| 7 | Inter-municipality |
| 8 | Inter-province |

These versions are effective immediately. They use the existing divisor of 6,000 and the greater of actual and volumetric weight. Version selection and historical shipment snapshots retain the existing behavior.

| Chargeable weight | Local COD | Local wallet | Cross-hub COD | Cross-hub wallet |
| --- | ---: | ---: | ---: | ---: |
| Up to 1 kg | PHP 70 | PHP 66 | PHP 103 | PHP 99 |
| Over 1 kg, up to 5 kg | PHP 80 | PHP 76 | PHP 113 | PHP 109 |
| Over 5 kg, up to 20 kg | PHP 105 | PHP 101 | PHP 138 | PHP 134 |

Local means same barangay or same municipality. Cross-hub pricing applies to the inter-municipality and inter-province schedules; actual compensation still requires evidence of the relevant work. A geographic route class alone does not prove that a driver or destination hub handled a parcel. Unsupported chargeable weights above 20 kg are rejected, rather than silently charged at the largest bracket. No long-distance zone or remote surcharge was invented.

## Compensation components

| Component | Configured amount | Beneficiary or allocation |
| --- | ---: | --- |
| Pickup | PHP 20 | Recorded pickup rider after pickup |
| Origin hub | PHP 8 | Origin sorting-center account identified by receipt/transfer records |
| Hub transport | PHP 25 per received transfer leg on cross-hub schedules | Assigned carrier rider/driver for that leg |
| Destination hub | PHP 8 on cross-hub schedules | Recorded distinct destination sorting center |
| Last-mile delivery | PHP 25 / 35 / 60 by weight bracket | Rider with recorded successful delivery proof |
| COD | PHP 4 for COD; zero for wallet | Logistics operating control account |
| Overhead | PHP 8 | Logistics operating control account |
| Planned margin | PHP 5 | Logistics margin account after actual claims |
| Return to sender | PHP 20 contingent | Separate funded return workflow; excluded from ordinary delivery totals |

Remote, handling and insurance components are zero in these examples. These amounts are fictional training costs, not actual carrier contracts, validated wages or a commercial profitability guarantee. [LBC's COD guidance](https://www.lbcexpress.com/article/article-cash-on-delivery) supports weight/destination-based pricing; it does not establish this internal compensation split. The existing [research notes](wallet-remittance-shipping-research.md) explain the same distinction.

An additional actual transport leg needs sufficient approved funding. Missing or unverified beneficiary shares remain held in the unallocated-logistics account. They are not converted into platform margin. If one person performs both pickup and delivery, the verified allocations can credit the same wallet.

## Receiving the earnings

Existing settlement controls remain in force: verified funding, completion, seven days since delivery, no blocking incident/dispute/refund, and approved seller settlement. When an administrator executes that settlement, the existing logistics service credits verified rider/driver and sorting-center available wallets with individual ledger entries. Recipients see those entries in **Finance & Wallet**. Logistics operating allocations and margin remain in separate control accounts.

This configuration did not settle orders or credit any wallet. Demo withdrawal execution remains an accounting operation, not a GCash, Maya or bank transfer.

## Setup provenance and existing shipments

The setup reused the existing approved logistics-admin record 16 for draft creation and the distinct existing demo-admin record 29 for demonstration approval/activation. An additional system audit entry and the approval reasons explicitly identify owner-authorized automated sandbox setup. They do not claim that two humans independently reviewed commercial costs. The normal requirement for distinct creator/reviewer identities remains unchanged.

The three existing orders, their order items, purchases, finance records, shipment snapshots, wallet accounts, ledger entries/transactions, allocations, payouts and refunds were compared before and after activation and remained unchanged. The existing shipment for order 38 retains its original legacy flat-fee snapshot and has no rate version. Activation does not retrofit compensation or charge that buyer extra. That shipment needs a separately authorized pricing/funding reconciliation if it must use the new allocations.

The database was backed up before configuration. Backup, hash, rehearsal receipt, applied receipt and post-commit verification are stored under `storage/app/private/backups/`; they are not public assets or source-controlled database exports.

## Verification

- A transaction rehearsal created/approved/activated the schedules, checked 24 quotes, and rolled back successfully before application.
- Application checked all four route classes, all three weight brackets, and COD/wallet totals: 24 quotes passed.
- A fresh connection confirmed four effective approved sandbox versions, enabled versioned pricing, unchanged hashes for existing operational/financial records, and a matching backup hash.
- Live ledger reconciliation passed: no imbalanced transactions or account-balance mismatches.
- Financial workflow suite: 22 tests, 411 assertions passed. Existing tests cover actual pickup, two hubs, a dedicated transfer driver, delivery, delayed approved settlement, exact recipient-wallet credits, refunds and ledger reconciliation in isolated test databases.
- Added a regression for the actual sandbox command: repeated draft generation stays at four versions, the documented route/weight component amounts and wallet COD exclusion match, and chargeable weight over 20 kg is rejected.
- Pint and whitespace checks passed. No live parcel was moved or paid to demonstrate the configuration. No browser or real-money payout verification is claimed.

Before commercial use, replace example schedules with evidenced operating costs and independently reviewed rates. Configuration remains editable through new tariff versions in **Delivery rates**; approved historical versions remain immutable.
