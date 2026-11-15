# Variation entry, cart photos and message controls audit

15 November 2026. Implements [the scoped plan](variation-and-message-updates-plan.md).

## Delivered behavior

- **Manual variation entry:** an explicit attribute/option form sits beside the optional combination generator. It adds a row to the existing price/stock/SKU/photo table and focuses its price field. Generation and blank-row entry remain available. Empty/duplicate entries and the 100-row cap are checked; existing server validation still governs product saves.
- **Cart photo preview:** changing the dropdown previews that option's own photo, falling back to the product photo with an honest caption. Missing/broken images have a text fallback. Preview changes do not submit or change the saved cart; Save variation retains the existing server stock, ownership, price, quantity and merge checks.
- **Unsend for everyone:** senders can retract their own text message (including its files and product enquiry snapshot) or a single file. Confirmation explains that already-seen/downloaded copies cannot be recalled. Recipients, other admins and outsiders cannot retract another sender's content.
- **Retraction integrity:** nullable timestamps keep tombstones; original message text/product context and retracted filenames are cleared. Old attachment/inline/product-image URLs are revoked. Private attachment bytes are deleted after commit; cleanup failures are logged while access remains revoked. Matching unread notifications are removed; unread message state is cleared when no attachment-only content remains.
- **Open-thread updates:** a visible conversation checks retraction status every 15 seconds for its currently rendered messages. It replaces removed content without a page reload or loss of the composer draft. Background/failed requests preserve readable history and do not refresh online activity. No original text or filenames appear in the status JSON.
- **Preview before download:** clicking a file opens a private in-platform preview; Download is a separate explicit action. Images, PDF, TXT, DOCX text and XLSX saved-cell previews are supported locally. XML/network entities are blocked, text/XML/archive/sheet/row/cell processing is bounded, rendered content is escaped, and spreadsheet formulas are not executed. Original Office layout/images are not reproduced. Legacy binary DOC/XLS and unsupported/corrupt/oversized previews show an honest notice plus Download; clicking them does not automatically download.
- **Account/panel continuity:** preview opens in the current page/frame, keeps the panel return link, and forwards the tab selector to inline PDF/image resources. No document goes to an external viewer.

## Fresh checks

| Check | Result |
| --- | --- |
| Messaging/retraction/preview/cart/pricing regression run | **85 tests, 711 assertions passed** |
| Final changed messaging/preview/activity subset | **27 tests, 304 assertions passed**; overlaps the 85-test run, do not add as unique coverage |
| All frontend tests | **90 passed**, including manual entry, image previews and open-thread retraction updates |
| Production build | **Passed**, Vite 2.94 seconds; existing optional fontaine notice |
| PHP formatting | Scoped Pint passed |
| Blade compilation | `php artisan view:cache --no-interaction` passed |
| CSS and frame hierarchy | All 48 CSS files parsed; eight canonical pink-frame records unchanged |
| Diff whitespace | `git diff --check` passed |
| New migration | Applied successfully using only `2026_11_15_170000_add_unsent_timestamps_to_messages.php` |

New tests initially exposed a test-client identity switch carrying the previous password-hash session state. Tests now use the project's existing flush-session-before-switch convention. Session security was not disabled or changed. The final tests passed.

## Browser verification and limits

Native Chrome review completed for the available states:

- Seller create-product form: explicit manual entry and the generator were observed at desktop width and a 400 × 768 responsive viewport. The manual fields and button stack with space between them; the variation table stays within its horizontal scroll container. No product or draft was submitted.
- Existing private conversation: the sender's Unsend for everyone control and separate attachment preview/Download links were observed. No real message or file was unsent; destructive behavior was verified in isolated tests.
- Existing image attachment: its private embedded preview displayed the image, Back to conversation and a separate Download file button at 400 × 768. It opened as a page rather than triggering a download, preserving the tab and panel parameters.
- Buyer cart: Change variation displayed the preview caption and Save variation separately. Changing the selected option updated the caption without saving the cart. The current varied cart product has no photos, so the visible state was the honest No photo available fallback. Own-variant photos, product-photo fallbacks and broken-image recovery were verified by automated tests rather than a populated native-browser photo case.

A fresh read-only check found 21 active variations and four active image attachments. TXT/DOCX/XLSX and PDF response paths were covered by isolated tests; native PDF/Office rendering was not manually exercised with existing data. Native PDF support depends on the browser and has an explicit download fallback. This audit does not claim exhaustive visual/assistive-technology/performance acceptance or production deployment.

## Change boundaries

The existing seller variation editor, saved-cart/checkout services, private one-to-one conversations, role permissions, frame colors and notification exceptions remain intact. No package was added. One reversible migration adds two nullable columns without editing historical migrations. Retraction is intentionally irreversible for removed content, and rolling back the schema cannot restore it. Do not roll back retraction schema while leaving code that uses it deployed.
