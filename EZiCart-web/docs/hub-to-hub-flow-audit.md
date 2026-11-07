# Hub-to-hub flow audit and demo guide

## Outcome

Dedicated hub transport is implemented within the existing `courier` role. Logistics administrators assign driver duty; ordinary local riders cannot release hub transfers, and dedicated transport drivers cannot take seller pickup or last-mile assignments. Existing riders remain local riders until explicitly changed.

The local migration and `HubTransferDemoSeeder` have been run. Both demo lanes passed a rollback-only MySQL service check. The four new HTTP feature tests passed, including transfer completion and continuation into destination-hub last-mile assignment. The full regression suite passed: **327 tests and 3,011 assertions**.

## Accounts already inserted

All **new** accounts below use `Password123!`. A seeder rerun preserves existing passwords and account details.

| Function | Account | Vehicle / demo cargo limit |
| --- | --- | --- |
| Logistics administrator | `hub.admin@ezicart.com` | Manages hubs and driver duty |
| Majayjay source hub | `hub.majayjay@ezicart.com` | Sorting center |
| Santa Cruz hub | `hub.santacruz@ezicart.com` | Sorting center |
| Calamba destination hub | `hub.calamba@ezicart.com` | Sorting center |
| Majayjay transport driver | `transport.majayjay@ezicart.com` | Dedicated light truck, 500 kg |
| Santa Cruz transport driver | `transport.santacruz@ezicart.com` | Dedicated light truck, 500 kg |
| Calamba transport driver | `transport.calamba@ezicart.com` | Dedicated light truck, 500 kg |
| Majayjay local rider | `local.majayjay@ezicart.com` | Motorcycle, 40 kg |
| Santa Cruz local rider | `local.santacruz@ezicart.com` | Motorcycle, 40 kg |
| Calamba local rider | `local.calamba@ezicart.com` | Motorcycle, 40 kg |
| Demo buyer | `hub.buyer@ezicart.com` | Order recipient |
| Demo seller | `hub.seller@ezicart.com` | Cargo-box seller |

These capacities are demo fixtures attributed to the demo administrator, not real-world vehicle recommendations. Real vehicles require verification against their documented cargo allowance.

The hubs use Laguna addresses. Dedicated demo areas are created for each hub; Santa Cruz and Calamba receive municipality routing maps only when those maps are free or already belong to this demo. Existing Majayjay routing is preserved. The seeder stops rather than replaces a conflicting destination map, hub ownership or driver duty.

## Starting parcels

| Parcel | Source | Destination | Packed weight | Initial state |
| --- | --- | --- | --- | --- |
| `HUB-DEMO-001` | Majayjay | Santa Cruz | 21.000 kg | SORTED, awaiting transfer planning |
| `HUB-DEMO-002` | Santa Cruz | Calamba | 22.000 kg | SORTED, awaiting transfer planning |

These are two independent transfer examples. Each starts as an explicitly labeled demo fixture already at its source hub, with an initialization tracking event. They do not imply real seller pickup scans or a completed checkout. Existing orders and transfer progress are not reset.

## Step-by-step demo: Majayjay to Santa Cruz

1. Open separate tabs using **Account > Sign in another account**. Sign in as the demo logistics admin, Majayjay hub, Majayjay transport driver and Santa Cruz hub.
2. As **logistics admin**, open **Parcel oversight** on the control tower. Search for `HUB-DEMO-001`. Choose **Santa Cruz Demo Hub** in its transfer form, provide a reason and select **Plan physical transfer**. The transfer becomes `PLANNED`.
3. As **Majayjay hub**, open **Hub transfers**. Find `HUB-DEMO-001`. Copy its current `EZP:` parcel label from its parcel record, or scan its printed label. Choose **Transport Majayjay Driver**, enter a manifest reference such as `DEMO-MAJ-SC-001`, and select **Scan and release transfer**.
4. Expect `IN_TRANSIT`. The parcel still belongs to Majayjay until physical receipt. The dedicated driver's **Weight & load planning** page reserves 21 kg against its 500 kg demo capacity. Ordinary sorting, storage and delivery operations cannot bypass the active transfer.
5. As **Majayjay transport driver**, open **Assigned hub transfers**. Login's normal courier dashboard redirects here. Check the destination address, parcel, weight and manifest. This driver sees only their own assigned transfers. They transport the parcel; they cannot submit either hub's release/receipt action.
6. As **Santa Cruz hub**, open **Hub transfers** after physical arrival. Scan or enter the same current `EZP:` label and select **Scan physical receipt**. Expect transfer `RECEIVED`, parcel `AT_SORTING_CENTER`, and custody moved to Santa Cruz.
7. Check the driver's load plan: the transfer's 21 kg reservation is removed. Source hub and driver receive transfer-history notifications; buyer and seller receive their normal order update. Repeating the receipt is rejected.
8. To continue locally, Santa Cruz opens **Sorting queue**, sorts the received parcel, then opens **Dispatch parcels** and assigns **Local Santa Cruz Driver**. This local rider has the destination service-area membership. If no exact barangay assignment exists, use the existing supervised coverage override and record a reason. Complete the existing rider-badge and parcel-label release scans before the local rider starts delivery.

Repeat steps 2-7 for `HUB-DEMO-002`, substituting Santa Cruz as source, `transport.santacruz@ezicart.com` as transport driver, and Calamba as destination. This second parcel reserves 22 kg until Calamba receives it.

## Administration and controls

- In **Staff profiles**, logistics admins can select **Local pickup and last-mile delivery** or **Dedicated hub-to-hub transport** while verifying vehicle capacity. A duty change is rejected while that driver has reserved loads. Capacity may still be corrected truthfully; overweight outgoing movements remain blocked.
- A source hub can release a transfer only to a dedicated, approved driver belonging to that hub through its owner or active service-area membership.
- The source must scan the matching parcel and record a transport manifest. A parcel still stored in a location must be retrieved before release.
- The destination must own the parcel's destination area and scan the matching parcel. Premature receipt, another hub's receipt and repeated receipt are rejected.
- The destination receives custody only after its receipt scan. Vehicle weight reservations include all active hub-transfer parcels and release at receipt.
- Missing weights, missing verified capacity and combined overweight loads remain blocked by the shared load service.
- Planning, release and receipt preserve administrative audits and tracking events. Release audits include the chosen driver, manifest, parcel weight and vehicle capacity.
- Transfer notifications for hubs and transport drivers point to the accessible transfer page instead of order pages they may not own.

## Verification evidence

| Check | Result |
| --- | --- |
| Migration on local MySQL | Passed |
| Seed 12 accounts, 3 owned areas, 6 vehicles and 2 parcels | Passed; read-only database inspection confirmed accounts and capacities |
| Repeat seeder without resetting password or parcel progress | Passed in HTTP test suite; local rerun also completed |
| Admin plan > source release > assigned driver view > destination receipt | Passed |
| Destination sorting and local rider assignment after receipt | Passed |
| Driver duty, hub ownership and transfer-view isolation | Passed |
| Invalid label, premature receipt and duplicate receipt | Passed |
| Block duty changes while vehicle has reserved loads | Passed |
| Both seeded lanes on local MySQL | Passed; all smoke-test movements rolled back |
| Full regression suite | Passed: 327 tests, 3,011 assertions |
| Routes and PHP formatting | Checked during implementation |
| Authenticated desktop/mobile visual review and real camera hardware | Not performed |

The automated HTTP tests use the configured isolated SQLite test database. The local MySQL smoke check exercises the real transfer services and actors, suppresses notification delivery, and rolls back all temporary movements. It does not leave either demo parcel completed. This audit does not claim that real driving, camera recognition, physical parcel handling or buyer delivery was tested.

## Reproduce

```powershell
php artisan migrate
php artisan db:seed --class=HubTransferDemoSeeder
php artisan test --compact tests/Feature/HubTransferFlowTest.php tests/Feature/ParcelWeightLoadTest.php tests/Feature/WorkflowRevisionTest.php
php scripts/check-hub-transfers.php
```

The seed and smoke checker are restricted to local/testing environments. The smoke checker requires the two parcels to remain at their initial sorted stage; it refuses to reset a parcel you have already progressed. Use the manual walkthrough for progressed data rather than deleting or resetting your database.

The existing workflow transfers directly to the hub that owns the final delivery area. Ordinary seller-pickup intake still requires the receiving hub to own that destination area; this audit starts with parcels already held at a source hub and verifies the explicit transfer/rerouting workflow. It does not certify a new automatic origin-hub intake network. Automatic intermediate-hub route chains, driver GPS, transport ETA, trailer/fleet scheduling and a driver self-confirmed unloading step are not part of this revision. Receipt remains the destination hub's responsibility.
