# M1 audit: controls and shared foundations

15 November 2026. Status: **implemented; pilot browser review passed**. Physical-device acceptance remains limited as described below.

Concepts: A05, A08, A15, A18, B13, B14, B16, C03, C08, C09, C13, C16, D13, D14.

## Changes and evidence

| Before | Revision | Evidence |
| --- | --- | --- |
| White fields had `#dce0e8` outlines | Workspace/catalog field token `#8792a2` | Calculated contrast against white: 1.32:1 before, 3.15:1 after. This is a boundary calculation, not a whole-page accessibility certification. |
| Placeholder rendering could inherit browser opacity | Explicit existing muted token at opacity 1 | `#657084` is 5.00:1 on white and 4.74:1 on workspace canvas. Native local catalog review showed clear labels and boundaries. |
| Small mobile header controls and compact touch links | 44px header/touch targets; 44px page primary actions with 13px type | Source inspection plus desktop and 400 × 768 Chrome emulation. |
| Small touch field text | 16px touch inputs/selects with 44px minimum height | Source rules verified; native phone keyboard/zoom behavior not run. |
| Existing tables already used tabular numbers | Extend to numerical, decimal and OTP inputs | Source inspection; currency values and formatting unchanged. |
| Floating UI used fixed offsets | Inset-aware chat, dialog maximum height and mobile sidebar | Mobile chat stayed below the header with reachable controls; actual nonzero device cutouts not tested. |
| Product-card overflow could clip an outside focus outline | Inset catalog-link focus outline and focused-card boundary | Source inspection; full assistive-technology review not run. |

Changed files: `public/css/interaction-feedback.css`, `public/css/catalog-search.css`, `public/css/message-drawer.css`, `public/css/workspace.css`, `public/css/responsive-layout.css`.

## Preserved contracts

All eight canonical frame-related rules in `interaction-feedback.css` match HEAD, including the four pink/grey frame tokens, main/nested/table border declarations and urgent frame treatment. Comparison normalizes selector whitespace because the baseline contains CRLF. Notification styles, landing typography, role accents and frame radii were not rewritten. No font or package was added.

## Checks and limits

- **Passed:** six edited public stylesheets parse using the installed PostCSS parser, including the later workflow stylesheet.
- **Passed:** Vite build (3.19 seconds reported by Vite).
- **Passed:** desktop and 400px mobile catalog inspection; actual images and honest missing-image placeholders remain visible.
- **Not run:** physical iOS/Android cutouts, virtual keyboards, all-role screenshot matrix, full screen-reader audit or measured CLS/INP.

The build warns that optimized font fallbacks optionally use `fontaine`. No dependency was installed for this existing warning. Existing Instrument Sans and other surface fonts remain.

Rollback: revert only the listed CSS refinements. No data migration or business rollback is required. Ready for the task-hierarchy pilot; preserve the user's border choice.
