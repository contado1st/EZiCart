# Responsive application frames

Updated October 21, 2026. `public/css/responsive-layout.css` extends the existing module styles and is loaded last by the application layout, using a file modification timestamp for cache invalidation.

- Workspace content is a named inline-size container. Metric cards, operations panels, administration panels and announcements controls respond to the space remaining beside the sidebar, including after expanding or collapsing it. Existing metrics use four, two and one columns; operational statistics retain automatic fitting.
- Inbox and notification frames switch to a single pane when the content area becomes narrow, even if the browser window itself remains wide. Chat history remains reachable through the existing conversation list and back link.
- Account and checkout form fields stack at narrow content widths; full-row spans are reset to prevent implicit extra columns. Checkout and cart summaries move below their forms/items as space decreases.
- Filters wrap between controls, grid children can shrink, and long headings wrap. Existing table scrolling, mobile logistics cards and whole-word action labels remain in effect.
- On phones the topbar reserves a second row for its primary action, keeping the logo, hamburger and communication icons on the first row. Header offsets and the floating message drawer account for that height. The mobile sidebar is capped to the viewport width.
- Crossing the mobile sidebar breakpoint closes the overlay, updates inert/expanded states and returns focus to the toggle if focus was in the sidebar.

The existing palettes, routes, data and workflows are retained. Automatic adaptation to content width requires CSS container query support in the browser.

Checks: CSS parsing reported zero warnings; JavaScript syntax check passed; the scoped Impeccable layout scan reported no findings. Browser resizing, zoom, orientation and visual review were unavailable because this session has no enabled browser surfaces. No automated tests or npm build were run; these are directly served public assets.
