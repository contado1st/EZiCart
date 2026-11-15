# Follow-up: two Partial cases fixed

Applied 14 November 2026, after the initial re-audit snapshot. This records the code changes and intended behavior; it does not claim that the original teacher scenarios were rerun.

After these code changes, the original 54 Partial cases map to **37 Aligned, 17 Different and 0 Partial**. Overall workbook counts adjust from the pre-fix snapshot to **164 Aligned, 4 Partial, 95 Different and 56 Missing**. The broad role checklist maps to **70 Aligned, 32 Partial, 15 Different, 6 Missing, 2 Unverified and 2 Optional**. These are code-alignment reclassifications only; they are not newly executed teacher cases.

## Rider availability — `LOG-DISP-003`

`RIDER_WORK_AVAILABILITY_ENFORCED` now defaults to `true` in Laravel configuration and `.env.example`. Approved riders must explicitly select **Available for new work** before dispatch, pickup, return collection or hub-driver assignment will accept them. Unavailable, unchosen and suspended riders are excluded. Changing to unavailable releases only safely unaccepted assignments; accepted parcels and physical custody remain with the rider. The rider page and logistics roster now explain the rule.

The local project `.env` has no explicit override for this setting, and no cached Laravel configuration exists in the workspace. The seven existing riders were not edited: they must choose **Available** in their own rider workspace before they can receive another assignment. Existing accepted work is preserved.

## Seller payout reservation — `SEL-EARN-007`

A verified, funded payout request now transfers eligible proceeds once from the seller's pending earnings account to a dedicated `payout_hold` ledger account. A stable ledger key makes repeated submissions return the same reservation; the ledger prevents reserving more than the funded balance. The finance page shows zero available for another request on an order that already has a payout request, and the wallet page shows seller payout holds separately from withdrawal holds.

Approval leaves proceeds reserved. Settlement moves that held amount to the selected destination. If a refund takes priority, the hold is released back to pending earnings before the refund reverses those proceeds. Existing ledger payouts without a new reservation continue to settle from their legacy pending account. No schema migration is required; ledger accounts are created through the existing account service.

Only the requested order's eligible earnings become unavailable for another payout request. The general wallet balance remains distinct from earnings awaiting settlement.

## Validation status

After the follow-up, five focused suites passed: rider availability, financial ledger, seller reports, return workflow and workflow revisions (**146 tests, 1,180 assertions**). The financial workflow now explicitly verifies that repeating a seller payout request reserves the proceeds only once. Workflow fixtures also mark riders Available where those scenarios assign new work. PHP syntax, the loaded Laravel config and Blade compilation passed. A full Laravel suite was started but stopped after exceeding the interactive check window; the earlier 723 PHP, 63 frontend and 8 audit-probe results predate this follow-up. Literal teacher spreadsheet scenarios and browser/device acceptance were not replayed.
