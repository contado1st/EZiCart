# Sidebar Dropdown & Arrangement Feature — Implementation Plan

**Date:** 16 November 2026  
**Author:** Antigravity AI Agent  
**Target:** `EZiCart-web` Sidebar Navigation Component & Workspace Layout  
**Pre-Audit Reference:** [`sidebar-dropdown-pre-audit.md`](file:///c:/Users/Admin/Downloads/EZiCart/EZiCart-web/docs/audits/ui-ux/sidebar-dropdown-pre-audit.md)

---

## 1. Goal & Architectural Design

Transform the static, flat navigation groups across all role sidebars into interactive, accessible, collapsible dropdown accordions.  
This allows users in all roles (Buyer, Seller, Courier, Sorting Center, Logistics Admin, Marketplace Admin) to organize and arrange their sidebar links cleanly, while maintaining immediate access to active pages and unread alerts.

```
+-------------------------------------------------------+
|  WORKSPACE SIDEBAR                                    |
|                                                       |
|  ▼ STORE MANAGEMENT                   [1 unread]  [v] | <- Dropdown Trigger
|    ├── Overview                                       |
|    ├── Orders (active)                                |
|    └── Products & inventory                           |
|                                                       |
|  ▶ PARCEL RECORDS                                 [>] | <- Collapsed Group
|                                                       |
|  ▶ PLANNING                                       [>] | <- Collapsed Group
|                                                       |
|  ▼ ACCOUNT                                        [v] |
|    ├── Notifications                  [3 unread]      |
|    ├── Account & profile                              |
|    └── Finance & Wallet                               |
+-------------------------------------------------------+
```

---

## 2. Component Specifications

### 2.1 Native Semantic HTML Pattern (`<details>` / `<summary>`)
We employ native `<details>` and `<summary>` elements to ensure:
* **Zero-JS Resilience:** If JavaScript is delayed or disabled, sections remain functional and can be toggled natively by the browser.
* **Full Keyboard Accessibility:** Accessible via native `Tab`, `Space`, and `Enter` key handling without custom keyboard traps.
* **Screen Reader Support:** Read natively as an expandable/collapsible disclosure widget.

### 2.2 Active Group Safety Rule
If a group contains the current active route, it must render with:
1. `open` attribute explicitly present in server-side Blade rendering.
2. `data-active-group="true"` attribute to instruct JavaScript never to auto-collapse the active group during state hydration.

### 2.3 Unread Notification Indicator on Collapsed Groups
When a group is collapsed, any unread messages or notifications within it would otherwise be hidden.  
The trigger `<summary>` will display an attention badge dot (`.workspace-group-badge`) if child items have unread counts, ensuring no alerts are missed.

### 2.4 State Persistence (`localStorage`)
The user's preferred arrangement (which groups are expanded or collapsed) will be persisted in `localStorage` per role:
* Key: `ezicart_sidebar_nav_${role}`
* Stored payload: JSON array of collapsed or open group identifiers.
* Hydration: On page load, `workspace.js` reads this mapping and sets the `open` state, while preserving the active group's open status.

---

## 3. Targeted File Revisions

| File | Proposed Changes |
| :--- | :--- |
| `config/icons.php` | Register `chevron-down` and `chevron-right` icons (completed). |
| `resources/icons/tabler/chevron-down.svg` | Tabler outline chevron icon (copied and available). |
| `resources/views/components/workspace-sidebar.blade.php` | Refactor groups from `div.workspace-nav-group` to `details.workspace-nav-group.workspace-nav-dropdown`. Add active group detection, group badge count checking, and dropdown summary triggers with rotating chevrons. |
| `public/css/workspace.css` | Add styling for `.workspace-nav-dropdown`, `.workspace-nav-dropdown-trigger`, `.workspace-dropdown-chevron`, `.workspace-group-badge`, focus outlines, and smooth height/rotation transitions. |
| `public/js/workspace.js` | Add dropdown arrangement persistence, `localStorage` sync, and active group preservation. |
| `resources/views/components/buyer-account-sidebar.blade.php` | Upgrade buyer account sidebar navigation groups to matching collapsible dropdown pattern. |

---

## 4. Execution Sequence

1. **Blade Updates:** Update `workspace-sidebar.blade.php` and `buyer-account-sidebar.blade.php` with `<details>` and `<summary>` dropdown layout.
2. **CSS Styling:** Add modern styles in `workspace.css` for dropdown headers, subtle hover treatments, badge dots, and smooth chevron rotation.
3. **JS State Management:** Implement localStorage persistence and toggle event listeners in `workspace.js`.
4. **Verification & Audit:** Verify across all roles, check keyboard navigation, inspect responsive drawer behavior, and compile `sidebar-dropdown-post-audit.md`.

---

*Handoff Notice for Codex / GPT:* This plan is ready for immediate implementation. Follow the exact classes and data attributes specified above.
