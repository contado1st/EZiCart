---
name: EZiCart authenticated workspaces
description: Compact role workspaces with slim navigation and clear bordered panels.
colors:
  primary: "#be2854"
  primary-hover: "#9f2046"
  primary-soft: "#fff0f4"
  logistics-primary: "#71384f"
  logistics-primary-hover: "#57263b"
  logistics-soft: "#eee7f4"
  canvas: "#f8f9fc"
  logistics-canvas: "#f8f7fb"
  sidebar: "#fcfcfe"
  logistics-sidebar: "#faf8fc"
  surface: "#ffffff"
  ink: "#202634"
  muted: "#657084"
  line: "#e7e9ef"
  input-line: "#dce0e8"
typography:
  headline:
    fontFamily: '"Instrument Sans", ui-sans-serif, system-ui, sans-serif'
    fontSize: "25px"
    fontWeight: 650
    lineHeight: 1.25
    letterSpacing: "-0.025em"
  title:
    fontFamily: '"Instrument Sans", ui-sans-serif, system-ui, sans-serif'
    fontSize: "14px"
    fontWeight: 650
    lineHeight: 1.5
    letterSpacing: "-0.01em"
  body:
    fontFamily: '"Instrument Sans", ui-sans-serif, system-ui, sans-serif'
    fontSize: "12px"
    fontWeight: 400
    lineHeight: 1.6
  metric:
    fontFamily: '"Instrument Sans", ui-sans-serif, system-ui, sans-serif'
    fontSize: "28px"
    fontWeight: 650
    lineHeight: 1.25
    letterSpacing: "-0.025em"
  label:
    fontFamily: '"Instrument Sans", ui-sans-serif, system-ui, sans-serif'
    fontSize: "10px"
    fontWeight: 600
    letterSpacing: "0.09em"
rounded:
  control: "7px"
  navigation: "8px"
  compact-panel: "12px"
  panel: "14px"
spacing:
  small: "8px"
  navigation: "12px"
  grid: "16px"
  section: "20px"
  panel: "22px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "8px 12px"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "9px 11px"
  navigation-active:
    backgroundColor: "{colors.primary-soft}"
    textColor: "{colors.primary}"
    rounded: "{rounded.navigation}"
    padding: "10px 12px"
  panel:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.panel}"
    padding: "{spacing.panel}"
---

# Design System: EZiCart authenticated workspaces

## Overview

The implemented dashboard frame uses a slim left sidebar, a compact utility topbar, metric cards, and clear white bordered panels. The shared frame supports scanning operational information while retaining each role's existing content and actions.

This document records source code evidence, not a visually verified rendering. It applies to authenticated workspaces selected by `resources/views/layouts/app.blade.php`, including the six confirmed dashboard roles and the related workspace routes selected there. Welcome, storefront, and authentication designs are outside its scope. The visual direction comes from the user's screenshot reference; no additional brand metaphor was confirmed.

**Key Characteristics:**

- Compact navigation and utility controls.
- Neutral panels with restrained role accents.
- Metric values and tables use tabular numerals.
- A collapsible desktop rail becomes a mobile drawer.

## Colors

### Primary

Rose identifies active navigation, primary actions, focus outlines, and selected details for buyer, seller, and e-commerce administrator workspaces. The deeper rose is the primary hover treatment; the pale rose is used for selected navigation and icon backgrounds.

### Secondary

Wine and lavender are the alternate role palette for courier, sorting center, and logistics administrator workspaces. This variant replaces the accent, soft fill, canvas, and sidebar tokens together.

### Neutral

White panels sit on a pale canvas; dark slate carries primary text, muted slate carries supporting text, and light borders separate regions. Preserve existing semantic status colors from the relevant module rather than treating the role accent as a status code.

## Typography

Instrument Sans with system sans fallbacks is shared across the workspace frame. Page headings use the headline token, panel headings use title, and supporting descriptions use body. Navigation labels are medium weight; uppercase group headings use the label token. Metric values use the metric token and tabular numerals. The existing operational monospace utility remains available for identifiers.

At the smallest breakpoint, page headings reduce to (23px) and metric values to (24px). Supporting descriptions are constrained to (75ch) where implemented. Avoid changing data labels or truncating financial values to fit the hierarchy.

## Layout

The desktop grid pairs a sidebar (240px) with a flexible content column under a sticky header (72px). Desktop collapse reduces the sidebar to (76px). Default page padding is (30px 28px 48px), with larger gutters from (1600px).

At (1200px) and below, dashboard workflow columns stack and dashboard metric grids use two columns. At (900px) and below, the sidebar becomes a fixed drawer (270px), multi-panel operational and administrator grids stack, and the main frame uses the full width. At (540px) and below, the header reduces to (64px), dashboard financial stat cards use one column, and panel padding and typography tighten. Other metric grids retain their source-specific column behavior.

Grid gaps are typically the grid or section spacing tokens. Tables scroll within their own wrappers. Flexible columns and panels use minimum widths of zero to contain overflow.

## Elevation & Depth

The shared frame uses borders and tonal layering. Metric cards, panels, and primary actions have no box shadow. The mobile drawer has a translucent dark overlay (`rgb(25 30 44 / 38%)`) to distinguish navigation from the page beneath it.

## Shapes

Panels and metric cards use the panel radius, reducing to the compact panel radius at the smallest breakpoint. Buttons and fields use the control radius; navigation uses the navigation radius. Other established compact cards use (9px) corners. Avatars and role indicators are circular. Borders are thin (1px) and neutral.

## Components

### Buttons

Primary controls use the role accent, white text, a darker hover fill, and a minimum height of (36px). Compact action buttons use (11px) semibold text. The topbar action uses (12px) text and (8px 14px) padding, with smaller values at the smallest breakpoint. Outline controls retain white fill and a neutral border. Disabled buttons reduce opacity to (0.5).

### Inputs / Fields

Fields use a white background, neutral stroke, control radius, and body text. Placeholder and label colors remain subdued. All interactive controls receive an accent focus outline (2px) with offset (3px). Preserve associated labels and existing validation behavior.

### Cards / Containers

Panels use white fill, a neutral border, panel radius, and panel spacing. Metric cards have (20px) padding and minimum height (138px), reducing to (16px) and (130px) on small screens. Metric icons sit in pale role-colored tiles. Currency values may wrap rather than clip.

### Navigation

The sidebar groups links under small uppercase headings. Links have minimum height (42px), role-tinted active fill, and a neutral hover fill. Shopicons supply the existing icon vocabulary. The topbar contains the brand, navigation toggle, page context, and role-relevant utility actions.

The drawer script updates expanded state, makes closed mobile navigation inert, moves focus into the open drawer, supports Escape and overlay dismissal, restores toggle focus, and cycles keyboard focus within drawer links and buttons. Reduced-motion preference removes transitions and animations. Navigation color transitions use (150ms ease); the drawer uses (180ms ease).

### Tables and Status Labels

Tables use compact text, uppercase muted column headings, horizontal row separators, and tabular numerals. Status labels have compact padding (4px 8px), small type (10px), and gently rounded corners (5px); their meanings and semantic fills remain owned by existing role modules.

## Do's and Don'ts

### Do:

- **Do** reuse the shared frame and its role palette for authenticated workspaces.
- **Do** preserve data, actions, workflows, and server role permissions.
- **Do** keep financial values readable and table overflow contained.
- **Do** preserve visible keyboard focus and reduced-motion behavior.

### Don't:

- **Don't** apply this dashboard system to welcome, storefront, or authentication surfaces.
- **Don't** replace module status meanings with the decorative role accent.
- **Don't** add dependencies, schema changes, or business workflow changes as styling side effects.

Source authority: `public/css/workspace.css`, `public/js/workspace.js`, the workspace Blade components, and the route selection in the application layout. Browser screenshots and computed styles were unavailable during this documentation pass; responsive and accessibility descriptions above record implementation intent and code behavior.
