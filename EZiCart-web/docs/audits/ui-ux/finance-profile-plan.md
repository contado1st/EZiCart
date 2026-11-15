# Finance and account-review UI plan

15 November 2026. Continue beyond the six main dashboard previews. Concept coverage remains a separate metric; this pass does not automatically add new covered concepts.

## Findings and chosen changes

| Source / baseline evidence | Revision |
| --- | --- |
| Wallet screenshot: finance navigation has no current destination; grid gaps combine with child margins | Mark the active finance destination and remove duplicate page-level spacing only on the wallet page |
| Wallet export link lacks a clear secondary-button treatment | Use the existing page action group and outlined button |
| Personal wallet help incorrectly includes the shared platform treasury explanation | Select personal/shared wording from the existing actor domain; preserve sandbox and non-bank-account wording |
| Withdrawal request controls are ungrouped; administrative Decision select has no associated label | Reuse wrapping form groups and add unique labels; keep names, constraints, keys, confirmations and POST routes |
| Wallet funding and withdrawal forms both read global old amount/request_key | Add a form identity used only to restore the matching form's old values/key. Other forms get fresh keys. Do not use UI markers for authorization |
| Profile review forms all read old reason from one failed request | Restore a reason only in its matching revision; preserve route-bound authorization and rejection validation |
| Profile review table has an 820px minimum width | Keep desktop comparisons; use labeled mobile record rows with explicit table semantics, retaining current/requested values and decisions |
| Wallet/review off-end pages can imply there are no records at all | Distinguish empty page from zero-total history and provide a first-page recovery link with existing filters |
| Financial amounts share ordinary wrapping/alignment | Right-align explicit amount cells and keep comparable numerals unbroken, preserving actual cents and formatting |

## Preserve

Main pink, softer nested and grey inner frame hierarchy; neutral notification pages; role-specific palettes; sandbox notices; available/pending/reserved/held meanings; independent administrator review; exact ledger values; idempotency behavior; reason/evidence requirements; role boundaries and protected documents. Do not transform financial history into cards that hide before/after columns.

Do not submit demo funding, withdrawals, approvals, profile changes or financial execution in native preview. Existing local seed accounts may be used for read-only screens. No dependencies, migrations, payment providers or business-service changes are needed.

## Verification and deliverable

1. Inspect source, preserve baseline evidence and implement scoped Blade/CSS refinements.
2. Add meaningful regressions for form/revision-specific retry restoration, labelled decisions, active navigation and off-end recovery. Run related wallet, funding and profile authorization/workflow tests in the isolated test database.
3. Parse edited public CSS and build if template classes require it. Keep existing confirmations, loading, account/tab isolation and financial services.
4. Review wallet and account-review pages at desktop/mobile sizes using existing data where available. Inspect populated review states in tests if no pending live fixture exists. Record browser policy blocks and unverified states accurately.
5. Write a dedicated audit and extend the scorecard with subpage coverage, without inflating the concept percentage or claiming every state is reviewed.
