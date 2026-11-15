# Variation entry, cart image previews and message controls

15 November 2026. Extend existing Laravel/Blade flows without replacing current features.

1. Expose explicit manual attribute/option entry beside the existing combination generator. Reuse the existing row template, full per-row price/stock/image fields, duplicate validation and 100-row cap. Retain blank-row entry and generation.
2. Add each active option's own image (or product-image fallback) to the cart's server-generated option data. Show the chosen photo before saving, without changing the persisted cart until Save variation. Keep quantity/price/ownership checks intact.
3. Add sender-only unsend-for-everyone controls for text and individual attachments, with confirmation. Keep timestamped tombstones, clear original message/product content and attachment names, revoke old file/image URLs, clean private bytes after commit, and remove matching unread notifications. Never allow a recipient/admin/outsider to retract another sender's content. Do not claim to remove already-seen/downloaded copies.
4. Attachment links open a private preview page; a separate explicit Download action returns a download. Images/PDF use authorized inline content; TXT and bounded DOCX/XLSX extraction render escaped local text/tables. No document is uploaded to an outside viewer. Legacy binary DOC/XLS get an honest unsupported-preview notice plus explicit download.
5. Add nullable retraction timestamps through a new reversible migration; do not edit historical schema files. Test in isolated databases before applying only the new migration to the configured local database.
6. Verify authorization, stale URLs, tombstone rendering, storage cleanup, file preview escaping/bounds, manual entry and cart image updates. Run the related existing suites, build, lint and Blade/CSS checks; record any unavailable populated browser states in the audit.
