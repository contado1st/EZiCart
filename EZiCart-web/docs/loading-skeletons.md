# Loading skeletons

## Coverage

The shared application layout supplies content-area skeletons for the role dashboards, storefront, search/product pages, forms, notifications and conversations. The embedded messages layout uses the same mechanism within its frame. Analytics has a skeleton while its chart module loads; the message drawer has a placeholder while its first frame loads. Unloaded images inside page content receive a neutral placeholder background until they load or fail.

Skeletons use neutral rose/slate, lavender logistics or cream editorial colors matching their surface. They leave the shared navigation outside the content overlay. Destination profiles distinguish dashboards, purchase cards, list tables, announcements with a creation form, notifications, inboxes, conversation threads, analytics, search with filters, storefronts, product details, cart, checkout, order details, login and other forms. Container queries adapt these arrangements to the actual content/frame width.

GET route names in `config/page-loading.php` select the profile. The shared Blade component sends only route patterns and shape names to the client; no destination request is prefetched. Static routes take priority over parameter routes, with application base paths and optional route parameters supported. Inert templates are cloned only when needed. The visible loading label is screen-reader-only so it does not add an extra row above the destination heading.

For closer matching on return visits, the loader measures up to 100 visible heading, field, image, chart and table-cell rectangles. Only positions, sizes and rounding are stored in sessionStorage, with an opaque session/account scope. No page text, field values, credentials, query strings or image URLs are copied. The cache retains at most 20 entries for the current scope; entries expire after 30 minutes and are ignored if the destination shape, viewport or content width differs. Invalid or blocked storage safely falls back to the destination template. This improves layout matching without caching private page HTML or replaying real content. The cache is a layout hint, not a historical snapshot; changed data may alter the final layout.

## Behavior

- Internal link navigation and native form submissions schedule a placeholder after 140 milliseconds. Fast navigation completes without a forced loading screen or minimum animation duration.
- Confirmation dialogs and asynchronous handlers retain control through normal event cancellation. Skeletons begin only after an uncancelled navigation/submission event. Native GET forms use the destination profile; POST forms retain the current page shape while awaiting the server, because the server decides any redirect.
- Same-page links, downloads, known export/proof/document endpoints, external destinations, alternate targets and modified/new-tab clicks are excluded.
- Initial content loading is shown only when an above-the-fold, non-lazy content image is still loading. It clears when those images settle or the document finishes loading. A client-side skeleton cannot appear before the server sends the initial HTML response.
- Back/Forward restoration, page hide, window refocus, Escape, offline changes and backgrounding clear the overlay. A 12-second recovery timeout restores the existing content if the browser cancels navigation or treats a response as a download; this does not assert that a request failed or completed.
- The messages frame clears its placeholder on load/error or its recovery timeout. Navigations inside the frame use its own content skeleton.
- Skeleton decorations are hidden from assistive technology, with a separate status message and temporary `aria-busy` on the content/frame. Prior content busy state is restored. Reduced-motion preferences remove shimmer, and IntersectionObserver pauses offscreen skeleton animations where supported.
- Without JavaScript, the page overlay remains hidden and server-rendered content stays accessible. Analytics shows a JavaScript requirement message with the normal data tables available. Loading placeholders do not print.

## Integration

Use `<x-loading-skeleton variant="product" />` (or another destination profile) for a decorative placeholder. Existing layouts include `<x-page-loading />` once and mark their content with `data-page-content`. `message-list`, `form-block`, `table-body` and `charts` are reusable subsection shapes. To map a new page, add its GET route name under the appropriate ordered profile in `config/page-loading.php`.

Add `data-no-loading` to an asynchronous form/link or its wrapper to explicitly opt out of the navigation overlay. Async code remains responsible for its own loading and error state. The loader does not fetch HTML, replace navigation, resubmit forms, change authorization or alter workflows.

Files: `public/js/page-loading.js`, `public/css/loading-skeleton.css`, the two loading Blade components, the application/embedded layouts, and the message drawer integration.

## Verification

- Node behavior checks: **13 passed**. Covers delayed navigation, tailored destination shapes, route metadata/base paths/static precedence, measured-layout reuse, invalid/expired cache fallback, geometry-only storage, POST handling, export/download exclusions, prevented events, form confirmations, alternate targets, prior busy-state restoration, Escape, offline/background changes and timeout recovery.
- Related Laravel feature checks: **36 passed / 324 assertions** across loading-profile markup, communication center, home, search and analytics.
- JavaScript syntax, CSS parsing, Blade view compilation, production build and whitespace checks passed.
- Desktop/mobile synthetic component preview files for product, announcements, search and conversation layouts were generated under ignored testing storage. Rendered visual review remains incomplete: no connected browser surface was available; the native preview attempt was stopped by Computer Use because it could not reliably determine the Windows browser URL for its policy checks. No visual sign-off is claimed.

```powershell
node --test tests/Frontend/page-loading.test.cjs
php artisan test --compact tests/Feature/LoadingProfileTest.php tests/Feature/CommunicationCenterTest.php tests/Feature/HomeControllerTest.php tests/Feature/SearchPageTest.php tests/Feature/AnalyticsTest.php
```
