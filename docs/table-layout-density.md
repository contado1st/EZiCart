# Application table layout

Updated October 21, 2026.

`public/css/table-layout.css` is loaded after module and workspace styles in `layouts/app.blade.php`, with a file modification timestamp for cache invalidation. It applies to application table pages, including admin approvals, seller inventory, logistics and rider operations, finance, reports, activity logs and the shopping cart.

- Table-bearing page wrappers use the available content width instead of centered reading-width limits. Desktop gutters are 20px; narrow screens use 12px horizontal gutters.
- Panels containing tables use 16px padding, reduced to 12px on narrow screens. Table cells use 10px vertical padding with natural wrapping and automatic column sizing.
- Existing table wrappers scroll horizontally when content cannot fit. Non-logistics tables retain a readable minimum width on phones. Logistics tables keep their existing labeled mobile cards.
- Approval and rejection controls align together; the rejection reason has a bounded, wrapping layout. Cart contents use the available width beside a bounded order summary, stacking on smaller screens.
- Existing colors, fonts, records, actions, permissions, standalone email templates and printable waybill layout are preserved. Rows remain sized to their content; record count determines table height.

Checks: CSS parsed with zero warnings; the Impeccable layout scan reported no findings. No automated tests or npm build were run for this directly served stylesheet. Browser visual and responsive review was unavailable because the browser inventory contained no enabled surfaces.
