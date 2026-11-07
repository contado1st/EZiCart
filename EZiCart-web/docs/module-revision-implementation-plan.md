# Module revision implementation plan

Prepared: 7 November 2026. This plan precedes application implementation.

## Scope decision

The user selected **implement the remaining fixes and update the documents**. Scope includes F01–F12, the probe script, regression coverage and the operator guide. This plan was prepared before application changes began. Optional improvements in the original audit remain a separate backlog.

## Assessment baseline

The isolated probe script was rerun against the current branch using SQLite `:memory:`. The buyer order-detail endpoint now returns HTTP 200, so the original missing-relationship exception is historical. Inspection still finds incorrect discount, item-price and tracking-note mappings. The other original behavioral observations remain reproducible. The load-planning template still fails strict UTF-8 decoding; the two Markdown documents are valid UTF-8.

The guide's blanket statements that API controllers and response structures are untouched are no longer accurate for the combined web/mobile branch. Historical test results must be labeled by revision; they cannot establish the status of later changes.

## Stage 1: maintain the audit and its evidence

1. Keep finding IDs F01–F12 stable so existing references remain useful.
2. Separate historical reproduction evidence, current status, planned change and acceptance criteria.
3. Add assertions to the probe output. A successful HTTP response must not stand in for correct financial values or authorization.
4. Make expected rejection paths observable without terminating later probes. Mark unexpected scenario failures separately from a reproduced finding.
5. Preserve disposable database, fake notifications and array-mail safeguards. Never seed, migrate or reset the configured application database.
6. Document the exact invocation directory, output meaning, limitations and validation results.

## Stage 2: application fixes

| Sequence | Findings | Implementation boundary | Required evidence |
| --- | --- | --- | --- |
| 1 | F01 | Map existing order fields and snapshots into the existing buyer API contract; preserve response names and ownership checks | Assigned/unassigned order details, correct discount/items/notes, foreign-buyer 404 |
| 2 | F02 | Separate authorized origin intake from destination routing in hub scope and transition guards | Pickup through both hubs to final delivery; unrelated-hub denial |
| 3 | F03 | Separate approved return requests from physical custody; use existing transition and financial services | Approval reserves no rider load; pickup needs acceptance, verified capacity and scan; refund authorization is distinct |
| 4 | F04–F05 | Extend transfer participant and suspension recovery handling without broadening unrelated staff access | Suspension creates a recovery task; audited replacement handoff; both involved hubs reachable; unrelated rider denied |
| 5 | F08, F10 | Include transport work in rider analytics and check license eligibility before outgoing work | Transport metrics stay separate from local COD; expired licenses block dispatch; receipt/unloading stays available |
| 6 | F06–F07 | Reconcile dashboard earnings and derive real review ratings | Seller/platform discounts, refunds, unrated shop, multiple-product reviews |
| 7 | F09, F11 | Apply one-shop policy during API preview and paginate/search pending applications | Same-shop bulk purchase succeeds; mixed-shop preview fails; all pending applications reachable |
| 8 | F12 | Normalize only the affected template's encoding, then inspect its output | Strict UTF-8 check, Blade compilation, complete layout scan; browser review if available |

### Decisions to settle during design

- **Returns:** reuse existing approved-dispute state as authorization if it can represent pending collection safely; add a persisted state only if existing fields cannot preserve the custody distinction. Determine affected dashboards, badges and notifications before introducing any new order state.
- **Driver recovery:** retain the suspended driver's historical custody, record actual handover and assign one replacement. Do not silently cancel a parcel physically in transit.
- **License eligibility:** define behavior for legacy missing expiry dates and approved renewals. Do not invent historical dates or block safe unloading.
- **Financial metrics:** distinguish accrued seller earnings from settled cash and subtract the correct funding share of discounts. Do not relabel an incomplete formula as the ledger balance.

No dependency changes, production database mutations, seed runs or broad redesign are needed for the assessment. Any eventual migration must have a concrete data-preservation and rollback design.

## Stage 3: verification and release criteria

1. Add or extend HTTP/service regression tests around each changed behavior, including role and ownership refusals.
2. Run the narrow affected tests first. Run the complete PHP suite after application changes converge, with frontend tests and a build when frontend code changes.
3. Run PHP syntax, relevant formatting, route loading and Blade compilation checks. A passing syntax check is not a functional test.
4. Rerun the isolated probes and record measured values. Passing one narrow probe does not close an entire finding whose acceptance criteria include more steps.
5. Update the audit with actual outcomes and the guide with only implemented behavior. Keep planned features explicitly labeled as planned.
6. State unavailable checks, especially live SMTP, background-worker health, authenticated browser review and physical delivery. Never infer these from a unit test or scheduler listing.

## Completion criteria

For document/probe maintenance: the three requested inputs agree with current evidence, commands work from `EZiCart-web`, and unresolved application findings remain clearly open.

For application implementation: each included finding has regression coverage, measured acceptance results and matching operator instructions. A report is not a substitute for implementing an authorized fix.

## Implementation outcome

F01–F12 were implemented and the audit/guide updated. Returns reuse existing records, and transport recovery preserves an audit trail; no new schema or dependency was required. Final verification: **351 PHP tests / 3,244 assertions**, **47 frontend tests**, **12/12 behavior probes**, production build, source layout scan and PHP syntax checks passed. Live browser, mail, queue delivery and physical operations remain unverified. See the audit for evidence and the guide for operator steps.
