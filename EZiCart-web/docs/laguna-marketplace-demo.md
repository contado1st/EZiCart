# Laguna marketplace demo accounts and audit

Created 16 November 2026 (Asia/Manila), after the owner corrected the scope to **14 sellers total**, distributed among Majayjay, Liliw and Magdalena. This is fictional school-testing data, not real businesses, identities, legal documents or vehicle inspections.

## Accounts inserted

| Municipality | Sellers | New sorting centers | Parcel riders | Hub transport drivers |
| --- | ---: | ---: | ---: | ---: |
| Majayjay | 5 | 1 | 2 | 1 |
| Liliw | 5 | 1 | 2 | 1 |
| Magdalena | 4 | 1 | 2 | 1 |
| Total | 14 | 3 | 6 | 3 |

There are **26 new accounts**, not 72 sellers. The 14 sellers cover all **12 current registration categories**, with extra Pet Supplies and Electronics and Gadgets sellers. Category configuration was not changed. Sellers are distributed across actual barangay names in the three towns; house numbers and street addresses are expressly fictional.

The private, complete login list is saved at:

- `storage/app/private/demo/laguna-marketplace/accounts.md`
- `storage/app/private/demo/laguna-marketplace/accounts.json`

All new logins use the reserved `.test` domain `laguna-demo.test`. The initial password is `LagunaDemo!2026`; it is a demo credential. Rerunning the seeder never resets a password. If the password has changed, the regenerated login list says **Existing password retained** instead of presenting the default as valid.

Examples:

- `seller.majayjay.01@laguna-demo.test`
- `hub.liliw@laguna-demo.test`
- `rider.magdalena.local1@laguna-demo.test`
- `rider.majayjay.transport@laguna-demo.test`

## Information and sample documents

Every new account has a name, adult date of birth/age, contact number and canonical identity fields, email/password, complete regional/municipal/barangay/address/postal information, profile name/bio/image, and simulated registration/approval timestamps. All are approved and email-verified **test fixtures**; these fields do not claim real human consent, phone verification or administrative investigation. The seed audit explicitly identifies simulated approval and administrator references.

| Account type | Additional data and private samples |
| --- | --- |
| Seller | Unique shop name, current category, sole-proprietorship type, format-valid synthetic TIN, identity sample and business-permit sample |
| Sorting center | Company name/canonical name, representative position, service region, format-valid synthetic SEC/TIN identifiers, representative ID, business permit, SEC and BIR Form 2303 samples |
| Rider/driver | Explicit local/transport duty, home hub, service-area membership, vehicle type/category/description, unique synthetic plate/license, future licence expiry, demo verified cargo capacity, availability and badge; ID, driver-licence and OR/CR samples |

Files are valid PNGs with conspicuous **DEMO ONLY**, **NOT A REAL OR VALID DOCUMENT**, and **NO LEGAL VALIDITY** labels. They contain no government seal. They remain in private storage and use existing authorized document-preview/download routes. There are **67 distinct document images**, **26 profile sample images**, and **70 available document entries** because a hub's representative image is referenced through both identity and representative document types. No real recipient was emailed or contacted.

The data fills the application's current required fields; it does not add new registration fields or substitute for actual legal/regulatory compliance. Synthetic phone numbers are format-valid test values and must not be contacted. Demo cargo limits are 30 kg for motorcycle, 75 kg for tricycle and 500 kg for van fixtures; they are not manufacturer specifications or transport recommendations.

## Routing and duty assignments

As explicitly selected by the owner, the separate Majayjay demo hub was added while preserving Majayjay's existing municipality route and existing barangay primary rider. New Majayjay parcel riders belong to the existing primary hub. The new dedicated Majayjay transport driver belongs to the new demo hub and has explicit source-area membership at the primary hub, using the existing transfer service's source-hub coverage rule.

| Municipality | New demo hub ID | Primary routed hub ID | Result |
| --- | ---: | ---: | --- |
| Majayjay | 48 | 5 | Existing primary route retained; additional demo hub does not replace it |
| Liliw | 52 | 52 | New municipality route to the new demo hub |
| Magdalena | 56 | 56 | New municipality route to the new demo hub |

There are 97 barangays in the cached fixture: Majayjay 40, Liliw 33 and Magdalena 24. Primary/backup coverage was added only where absent: **96 new rows**; the existing row was not replaced. Existing inactive memberships, passwords, suspension states, work choices and operational progress are not reset on reruns. Changing an existing demo identity, home hub or duty incompatibly causes a review error rather than overwriting it.

New rider availability is explicitly seeded as Available for this fictional scenario. Existing capacity, expiry, availability, coverage, weight and handoff checks remain enforced. No GPS location is fabricated; automatic proximity-based offers still require the rider's real device-location update. The additional Majayjay hub is not automatically substituted as the town's checkout destination hub.

Address labels and codes were downloaded through the project's [PSGC provider](https://psgc.cloud/api/v2), with [PSA municipality references](https://psa.gov.ph/classification/psgc/municipalities). Postal codes follow the [PHLPost locator](https://phlpost.gov.ph/zip-code-locator/): Majayjay 4005, Liliw 4004 and Magdalena 4007. The source snapshot is committed as `database/seeders/fixtures/laguna-demo-addresses.json`; seeding itself needs no network request.

## Running the fixture again

From `EZiCart-web`:

```powershell
php artisan db:seed --class=LagunaMarketplaceDemoSeeder
```

Only local/testing environments are permitted, and existing approved commerce/logistics administrators must already exist. The seeder is deliberately separate from `DatabaseSeeder`; a routine database seed does not automatically recreate this network. It adds missing fixture accounts, preserves modified existing fixtures, leaves earlier routes/primary coverage alone and refreshes the private login manifest. Resolve reported ownership/identity conflicts before retrying. New files are removed if an account-creation transaction fails, while pre-existing private files are retained.

## Verification and preservation

- Four feature tests, **658 assertions**, passed. They verify exact counts/distribution, every current category, identities/passwords, role documents and PNG validity, representative web logins, rerun preservation, protected existing primary routing, active transfer eligibility for the Majayjay driver, rollback/file cleanup on identity collision, and production refusal.
- The configured MySQL insertion completed in a transaction. Before/after row hashes confirmed that all pre-existing users, routing rows, rider memberships, coverage rows and audit rows remained unchanged. Existing orders, items, products, financial records, rate versions, settings, balances and ledger entries were also unchanged.
- Added: 26 users, 3 areas, 2 municipality mappings, 10 rider-area memberships, 96 barangay coverage records and 26 explicitly labelled fixture audit events.
- Fresh-connection verification found 26 approved accounts, 14 sellers, 12 distinct seller categories, no missing required base information, and all 70 private document entries available with valid PNG content. All 26 initial passwords verified against their hashes.
- Live ledger reconciliation passed with no imbalanced transactions or account-balance mismatches. The pre-insertion private database backup hash was verified.
- Representative seller-permit, hub-SEC and rider-licence images were visually inspected: data fits and the fictional-document labels are readable. Real emails, real document authenticity, real vehicle capacity and live physical deliveries were not tested or represented as verified.
- Pint and whitespace checks passed. No application workflow, dependency, schema, product, parcel, payment, tariff or balance was added or changed by this fixture.

Private backup, insertion receipt and verification report are under `storage/app/private/backups/` with the `laguna-marketplace` label. Existing data can be reviewed against that backup without exposing credentials or database exports as public assets.
