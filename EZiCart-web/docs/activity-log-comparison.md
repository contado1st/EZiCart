# Activity log comparison

The activity log's “View changes” button expands a full-width row instead of placing two raw JSON blocks in a narrow cell. The panel identifies the log, actor, role, timestamp, activity code, record and recorded reason.

`resources/views/components/activity-changes.blade.php` compares recorded top-level fields. Object key ordering does not create a difference; list order and JSON value types remain meaningful. Changed fields show before/after values. Unchanged fields are grouped in a separate disclosure. Missing fields, explicit null, empty text and boolean values have distinct labels. Status codes receive readable display labels; recorded JSON remains available.

Snapshots may contain only selected fields. “Only in before/after” describes the recorded snapshots and does not claim that a database field was deleted or created. Unreadable JSON produces a comparison-unavailable message with escaped recorded content available below.

The existing controller, administration-domain restrictions, filtering, pagination and stored audit records are unchanged. No extra queries are made for the comparison. Without JavaScript, detail rows remain visible; with JavaScript, native buttons toggle them with `aria-expanded` and `aria-controls`. Narrow layouts stack the entries and comparison fields. Reduced-motion preferences disable the disclosure animation.

Assets: `public/css/activity-logs.css` and `public/js/activity-logs.js`, loaded by the activity view with cache-busting timestamps. No npm build is needed for these public assets.

Checks: two focused activity tests passed with 22 assertions, including domain access, presentation, escaped content and unchanged stored snapshots. Blade compilation, JavaScript syntax, CSS parsing (zero warnings), scoped layout scan (no findings) and whitespace diff checks passed. Browser visual/responsive review was unavailable in this session.
