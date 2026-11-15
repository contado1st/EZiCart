# Disposition of the 100 UI concepts

15 November 2026. Companion to [the implementation plan](m0-plan.md) and [the researched brief](../../ui_ux_design_modernization_master_prompt.md).

**Applied** means changed in this pass. **Retained** means an existing implementation or design constraint was preserved. **Diagnostic** means a review principle, not a new feature. **Deferred** means deliberately outside this pass. These labels do not claim browser acceptance; see the cumulative audit for verification limits.

## A: visual clarity

| ID | Disposition | Application / reason |
| --- | --- | --- |
| A01 | Applied | Remove the rider's redundant Priority Action badge; keep real unread, task and approval counts. |
| A02 | Applied | Suppress a duplicate header action only when the page declares the same primary route. |
| A03 | Retained | Editorial typography stays separate; no global italic reset. |
| A04 | Retained | Normal UI fonts remain; no decorative monospace layer. |
| A05 | Applied | Extend existing workspace/catalog field tokens and shared components. |
| A06 | Retained | Catalog uses real product images, existing geometry and honest unavailable-image text. |
| A07 | Retained | Existing product gallery and operational task panels remain prominent. |
| A08 | Applied | Explicit wrapping action group and grouped validation feedback. |
| A09 | Applied | Seller title now describes orders and store performance. |
| A10 | Applied | Admin applications are primary; product review becomes secondary without losing its count. |
| A11 | Retained | Rose/slate and lavender/wine role accents remain unchanged. |
| A12 | Applied | Remove the redundant rating emoji; keep the existing Shopicons star. |
| A13 | Retained | No new decoration; user-approved pink frame hierarchy stays. |
| A14 | Diagnostic | Frame/control radii retained; no indiscriminate concentric-radius formula. |
| A15 | Applied | Stronger field boundary and explicit readable placeholder color; calculated values in M1 audit. |
| A16 | Retained | Existing balanced headings and overflow wrapping remain. |
| A17 | Diagnostic | Shared action alignment uses flex/gap; optical screenshot acceptance remains pending. |
| A18 | Applied | Extend tabular numerals to numerical/OTP inputs; existing tables already use them. |
| A19 | Retained | Existing destination-aware and geometry-aware skeleton loader reused. |
| A20 | Applied | Brief chat-opening feedback and existing confirmed-success highlight; text remains authoritative. |

## B: interaction techniques

| ID | Disposition | Application / reason |
| --- | --- | --- |
| B01 | Retained | Existing skeletons; no duplicate loading overlay. |
| B02 | Retained | Existing cart server-response feedback; no optimistic money or custody state. |
| B03 | Deferred | A command menu needs a demonstrated navigation gap and its own keyboard contract. |
| B04 | Deferred | No new global shortcuts; existing tab keyboard behavior is retained. |
| B05 | Deferred | No fake undo without a reversible server operation. |
| B06 | Retained | Workflow refresh already retains last-known counts and explains failure. |
| B07 | Retained | Blade already renders on the server; no private speculative prefetch. |
| B08 | Deferred | Existing image geometry and placeholder are sufficient for this pass; no new metadata pipeline. |
| B09 | Deferred | No cross-document snapshots containing account data; use current navigation. |
| B10 | Deferred | Keep the existing explicit chat close control and Escape handling; no new gesture interception. |
| B11 | Retained | Existing state/focus ownership retained; CSS chat entrance cannot block opening or closing. |
| B12 | Retained | Browser back and URL-based tabs remain native. |
| B13 | Applied | Real 44px control boxes on touch; no overlapping invisible hit areas. |
| B14 | Applied | Chat, dialogs and mobile sidebar account for safe-area insets. |
| B15 | Retained | Existing OTP controls/security flow unchanged; numeric appearance only refined. |
| B16 | Applied | Same numerical-input refinement as A18; not counted as a separate feature. |
| B17 | Retained | Existing balanced operational headings; normal prose remains unbalanced. |
| B18 | Deferred | Preserve established radii; no experimental corner-shape requirement. |
| B19 | Retained | Existing server mutation/idempotency contracts unchanged. |
| B20 | Deferred | Click sidebar has no nested hover-menu problem requiring pointer tracking. |

## C: diagnostic vocabulary

| ID | Disposition | Application / reason |
| --- | --- | --- |
| C01 | Diagnostic | Preserve font kerning and current fonts. |
| C02 | Diagnostic | Align icons inside stable controls; live optical review remains pending. |
| C03 | Applied | Tabular numeral refinement, shared with A18. |
| C04 | Retained | Existing media dimensions and skeleton geometry reserve space; no extra page-load choreography. |
| C05 | Retained | Preserve current tracking; no forced uppercase UI rewrite. |
| C06 | Retained | Server responses remain authoritative for important mutations. |
| C07 | Diagnostic | Improve action readability without replacing role fonts. |
| C08 | Applied | Touch target improvements, shared with B13. |
| C09 | Applied | Shared feedback line height, list spacing and action alignment. |
| C10 | Deferred | No new positioned popover needing an origin-calculation system. |
| C11 | Deferred | Existing hex tokens are adequate; no color-space migration. |
| C12 | Retained | Existing balanced headings, no hard-coded line breaks. |
| C13 | Applied | Safe areas, shared with B14. |
| C14 | Deferred | No stagger delaying long tables or urgent tasks. |
| C15 | Retained | Existing prose measure and full-width tables remain. |
| C16 | Applied | Clearer field outlines, primary/secondary actions and unclipped catalog focus. |
| C17 | Retained | Existing native selects preserved. |
| C18 | Retained | Instant close and existing focus restoration; no animation lifecycle state added. |
| C19 | Applied | Keep dashboard tabs/catalog filters and place occasional control-tower transfer/reassignment forms behind native disclosures, reopening after validation errors. |
| C20 | Deferred | No justified shared-element identity/navigation use case in this pass. |

## D: UX and animation principles

| ID | Disposition | Application / reason |
| --- | --- | --- |
| D01 | Deferred | No elastic deformation of work controls. |
| D02 | Retained | Existing immediate pending feedback; no artificial wind-up delay. |
| D03 | Applied | Specific seller heading and one clear admin primary action. |
| D04 | Retained | Existing hidden/open/loading/error state ownership preserved. |
| D05 | Deferred | No follow-through wobble on operational content. |
| D06 | Applied | Short ease-out chat opening, no slower task completion. |
| D07 | Deferred | No real spatial task needing an arc. |
| D08 | Retained | One chat entrance; no competing badge pulses or row motion. |
| D09 | Applied | 180ms chat opening with no interaction lock. |
| D10 | Applied | Clear success icon/text and readable validation grouping rather than flashing effects. |
| D11 | Deferred | Keep the existing 2D workspace. |
| D12 | Diagnostic | Consistent component refinements; no new visual world. |
| D13 | Applied | Larger reachable mobile controls. |
| D14 | Applied | Same target refinement as B13; no blanket enlargement of rows. |
| D15 | Applied | Distinguish primary and supporting dashboard actions. |
| D16 | Retained | Existing role grouping; no arbitrary seven-item cap. |
| D17 | Retained | Existing loading/retry feedback; no invented latency or speed claims. |
| D18 | Retained | All server validation and authorization unchanged. |
| D19 | Retained | Familiar native forms, tabs, back navigation and confirmations. |
| D20 | Retained | Existing Need Actions and role sidebar remain available. |

## E: motion proposals

| ID | Disposition | Application / reason |
| --- | --- | --- |
| E01 | Applied | Chat entrance uses short ease-out and an already-visible starting state. |
| E02 | Retained | Existing 180ms sidebar and confirmation behavior; no new dropdown system. |
| E03 | Deferred | No row stagger on workflow tables. |
| E04 | Retained | Existing 1px button press feedback; do not force 0.97 scale on small text. |
| E05 | Deferred | No bespoke moving toggle knobs. |
| E06 | Deferred | Do not delay secondary details, warnings or errors. |
| E07 | Deferred | No decorative product flights. |
| E08 | Retained | Existing finite success highlight now connects to the shared feedback component. |
| E09 | Retained | No new moving-layout interaction requiring ease-in-out. |
| E10 | Retained | Existing dialog scale of 0.98; do not impose a 0.95 scale everywhere. |
| E11 | Deferred | Chat can open from multiple header/launcher triggers; do not pretend one fixed origin is accurate. |
| E12 | Retained | Existing focus returns to the actual opener; close remains immediate. |
| E13 | Retained | No bounce on forms, tables, scans or finance. |
| E14 | Retained | No drag system added; native direct manipulation remains. |
| E15 | Deferred | Immediate close is preferable here to adding a timed exit/removal lifecycle. |
| E16 | Deferred | No duplicate old/new interactive content or account snapshots. |
| E17 | Deferred | No masks clipping text, focus or evidence. |
| E18 | Deferred | No icon blur or extra GPU effect. |
| E19 | Deferred | Preserve browser bounds/back and scrolling. |
| E20 | Deferred | No Motion dependency; existing React confirmation island stays small. |
