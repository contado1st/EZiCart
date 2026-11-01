# Marketplace search results

## Scope

`/search` (`search.index`) is the buyer and guest product discovery surface reached by the marketplace navbar search form. This is an extension of the existing storefront in Operate mode. Shopee/Lazada inform the dense browsing composition; EZiCart's logo, Instrument Sans, pink navigation and rose/slate presentation remain the visual authority.

Root `DESIGN.md` documents authenticated workspaces and explicitly excludes storefront surfaces. This note records the search page only; it does not replace that file or `.impeccable/design.json`.

## Implementation and behavior

- `resources/views/search/index.blade.php` renders the query heading, real result counts, filters, sorting, applied-filter links, product grid, pagination, validation errors and empty states.
- `public/css/catalog-search.css` scopes presentation through the search page body class. A pale canvas and white bordered cards support rose prices and selected controls. Product images retain their proportions with a square image area and contain sizing; missing images have an explicit placeholder.
- `public/js/catalog-search.js` opens the native filter disclosure at desktop widths and closes it below 900px. Without JavaScript the disclosure remains usable and initially open.
- `app/Http/Controllers/SearchController.php` validates query parameters, searches product names/descriptions/categories and seller business names, supplies category/province facets, aggregates real review averages/counts and paginates 24 products per page.
- `routes/web.php`, `resources/views/layouts/app.blade.php` and `app/Models/Product.php` supply the route, navbar entry and marketplace availability scope. Available listings are approved, unarchived and stocked; products with variations must have an active stocked variation.

The desktop layout uses a 210px filter sidebar beside results. The sidebar becomes a native collapsible filter section below 900px. Cards use four columns by default, five from 1500px, three at 1200px and below, and two at 600px and below. The narrow layout uses 14px side gutters; sort options can scroll horizontally.

Filters cover category, minimum/maximum price, minimum customer rating and seller province. Sorting offers relevance, latest, top rated and both price directions. Relevance promotes an exact product-name match; an empty query defaults to newest listings. Removing a filter preserves the other query parameters and resets pagination. The filter reset retains search and sort.

Cards show stored product/category/price data, real review aggregates, seller name and available province. Unreviewed products say “No ratings yet.” No sales totals, delivery promises or promotional claims are fabricated. Cards link to the existing product detail route; purchase and cart workflows remain owned by their existing screens. “My purchases” appears for guests and buyers.

Labels, fieldsets, breadcrumb and sorting navigation, visible keyboard focus, validation alerts and reduced-motion styling are present in source. The layout supplies the main landmark; this view uses a content wrapper rather than nesting another main element.

## Evidence and limitations

Documentation inspection covered the files above, `PRODUCT.md`, dashboard-scoped `DESIGN.md` and the Impeccable document reference. No tests or builds were run as part of this documentation pass.

The implementation pass reported 13 targeted tests with 79 assertions passing, successful Blade caching, JavaScript syntax checking, a Lightning CSS parse with zero warnings and a successful Vite build. The build retained a preexisting optional-fontaine warning. Browser review was blocked because the CUA inventory had no enabled apps or browsers; responsive, keyboard and visual descriptions here record source behavior, not rendered verification.

The single design detector pass reported the incumbent Instrument Sans as an overused font and advisory color/type differences against the dashboard-scoped design document. These are recorded without repair: the established storefront font and this surface's styling remain intentional. No global design tokens or other surfaces were restyled for those findings.
