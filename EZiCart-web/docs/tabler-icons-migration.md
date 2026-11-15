# Tabler icon migration

15 November 2026.

The Laravel web interface now uses the official `@tabler/icons` package, pinned to 3.52.0, with outline artwork. Shopicons and its JavaScript imports were removed.

Existing `<x-shop-icon name="chat" />` calls and `.shop-icon` layout selectors remain the shared interface. They now render a span containing a local Tabler SVG, without custom elements or JavaScript initialization. The icon inherits its surrounding color, uses a 24 × 24 viewBox and a 2px stroke, and is hidden from assistive technology because its parent control supplies the label.

## Updating icons

- `config/icons.php` maps the existing view names to Tabler names. In particular, `arrowback` maps to `chevron-left` to preserve the directional rotations used by the interface.
- `npm run icons:sync` copies only the mapped outline SVGs and the upstream MIT license into `resources/icons/tabler`.
- `npm run build` runs the same sync before Vite. Commit the SVGs with the mapping so PHP deployments can render icons without a runtime npm installation or external icon service.
- Add a mapping, run the sync, then use the shared Blade component. Only configured names and safe filenames are accepted.

The selected artwork and attribution come from the official [Tabler Icons repository](https://github.com/tabler/tabler-icons). No font or CDN is needed.

## Verification

- Dashboard navigation and communication regression suites: 23 tests and 240 assertions passed.
- Production build: passed; sync copied all 30 selected SVGs and the MIT license. Vite completed in 2.88 seconds with the existing optional fontaine notice.
- Blade compilation, scoped Pint, PHP syntax and diff whitespace checks passed.
- Native Chrome: refreshed administration dashboard verified at desktop and narrow window widths. Sidebar, header, metric and Messages icons rendered with the new artwork and existing colors and sizing. Other roles are covered by shared-component page rendering tests; this is not a claim of exhaustive browser coverage.
- Dependency inspection: `@tabler/icons@3.52.0` installed; `@h2d2/shopicons` absent. Source scan found no remaining Shopicons package imports, custom elements or asset references.
- The old 30 Shopicons SVGs were removed. Existing registration label edits were retained separately.
