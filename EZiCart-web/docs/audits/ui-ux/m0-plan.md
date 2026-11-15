# UI modernization: implementation plan and baseline

Date: 15 November 2026. Scope: Laravel/Blade web UI.

## Fixed design decisions

Preserve the existing main pink frame, softer nested pink edge and grey inner edge. Preserve the notification pages' neutral borders. Keep the separate logistics and storefront palettes and fonts. Revisit frame styling only after the user reviews the result.

No database, financial, permission, parcel-transition or dependency changes belong to this UI pass.

## Baseline assessment

The project already provides server-rendered pages, shape-aware skeletons, accessible React confirmations, dashboard tabs, numbered pagination, live workflow counts, unread badges and reduced-motion rules. Reuse these. Existing tests cover their behavior.

Source inspection found these concrete opportunities:

| Finding | Planned revision | Boundary |
| --- | --- | --- |
| Field outlines use very pale `#dce0e8` on white | Give editable fields a clearer neutral outline | Frame colors stay unchanged |
| Header icons shrink to 34px on mobile | Use 44px touch controls; collapse the brand column at narrow widths | Keep messages, notifications and account navigation available |
| Dashboard action groups have ad hoc margins; two admin actions look equally primary | Introduce an explicit wrapping action group and secondary action | Keep both destinations and queue counts |
| Dashboard-local Add product / Receive parcel / Review applications repeat the header CTA | Declare the existing in-page primary action, suppress only the duplicate header link | Other pages retain their quick action |
| Seller heading is a generic greeting and repeats a star glyph | Use a task-specific title and one existing rating icon | Preserve amounts, counts and accounting explanations |
| Floating chat and dialogs do not account for device safe areas | Add inset-aware positioning and touch targets | Preserve existing iframe loading and focus logic |
| Shared feedback component lacks its dedicated feedback class | Apply existing success feedback styling and separate error copy/spacing | Keep server-confirmed messages and validation semantics |
| Numeric styling is inconsistent outside the main metrics | Apply tabular numerals to shared tables and numeric inputs | Keep normal fonts for prose and labels |

## Sequence and gates

1. M1: shared controls, readable fields, safe areas, numerical alignment and responsive targets. Inspect the CSS cascade, keep pink declarations unchanged, build assets.
2. M2: task-specific seller overview and dashboard action hierarchy. Use a Blade section to suppress only explicitly declared duplicate header actions. Render buyer, seller, rider, hub and admin pages through existing feature tests.
3. M3: connect existing feedback styling, add restrained chat-opening feedback with reduced-motion support, retain the existing loading/confirmation/tab implementations. Run relevant frontend regression tests and build.
4. M6: review the final diff and write a cumulative audit with actual verification results and unresolved visual checks. Do not call source inspection a browser pass.

Each milestone gets a separate audit in this directory. Advanced features are conditional, not mandatory checkboxes: command menus, BlurHash, speculative navigation, fake undo, gesture interception, 3D effects, row staggers and new motion libraries are deferred because the existing task UI does not establish a need. Existing idempotency, OTP, optimistic cart behavior and account/tab isolation are retained rather than rewritten as design work.

## Preview evidence and limits

Native Chrome inspection reached the existing domain storefront and confirmed the main navigation and product content. The browser extension host is unavailable. This is baseline accessibility-tree evidence only; it is not a screenshot review or evidence of the revised local build. Native inspection took almost five minutes for one read, so verification will prioritize local render tests and asset checks, with the visual review status reported separately.

Update after implementation: the existing local server at `http://127.0.0.1:8000` responded successfully. Native Chrome provided desktop and 400px mobile screenshot reviews of the catalog and seeded seller dashboard. The mobile dashboard review exposed a pre-existing zero-width content track when the fixed sidebar left grid flow. The corrective revision makes the mobile frame a single column, and keeps the task-disclosure indicator on its first line. The final screenshot confirmed readable full-width dashboard content. See the milestone audits for the resulting evidence and remaining device checks.
