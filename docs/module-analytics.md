# Role analytics

This module extends the existing authenticated workspace design described in `DESIGN.md`. It uses existing application records; it adds no visitor tracking, schema, dependency, or business workflow. This document records implementation evidence and delivery limits. Global product and design guidance remains in `PRODUCT.md` and `DESIGN.md`.

## Access and scope

The Analytics navigation entry and reporting endpoints serve six workspaces through seven stored role values:

| Workspace | Role values | Authorized order cohort |
| --- | --- | --- |
| Buyer | `buyer` | Orders owned by the signed-in buyer |
| Seller | `seller` | Orders belonging to the signed-in seller |
| Rider | `courier` | Orders with the rider's current pickup or delivery assignment |
| Sorting center | `sorting_center` | Orders assigned to the signed-in center or with tracking events recorded by that user |
| Marketplace administration | `admin`, `ecommerce_admin` | Marketplace orders |
| Logistics administration | `logistics_admin` | Network orders |

Server scope applies before filters, charts, tables, and exports. Seller filtering is restricted to commerce administrators. Rider filtering is available to commerce administrators, logistics administrators, and sorting centers. Supplying either restricted filter from another role returns validation feedback. Operational reports exclude commercial sales, commission, refund, and payout breakdowns. Buyer reports expose the buyer's recorded COD and refunds rather than marketplace finances.

## Definitions and filters

Reports select orders by placement date in the application timezone, then show their current recorded lifecycle state. They are not historical state snapshots. Refunds and payouts include current adjustments attached to those orders, even if processed outside the placement period. Exported adjustment rows include update timestamps.

The default period is the current month through today, grouped daily, compared with the previous equal period. Trends support day, week, month, quarter, and year. Comparison can use the previous equal period, the same dates last year, or none. Weekly and quarterly labels identify the group containing each selected date; empty periods retain zero counts. Percentage change is unavailable when the previous value is not positive.

Additional filters cover authorized seller/rider IDs, product ID, current category, order status, municipality, barangay, payment record, payment method, and voucher code. Product and category filters select whole orders, including other items in those orders. Payment-record filters distinguish verified remittance, recorded COD collection, absence of collection, and executed refunds.

Financial figures reuse `SalesReportService`, whose source values use integer cents. Gross merchandise value is recognized for completed orders and delivered orders with executed refunds. Seller net sales deduct seller-funded discounts, merchandise refunds, applicable platform subsidy reversal, and recognized commission. Commission requires completed, unrefunded orders with verified remittance. Shipping and platform-funded discounts remain separate. Net seller sales is not profit.

Product/category gross and fulfilled units precede returned-unit adjustments and order discounts. Categories come from current product records. Customer behavior is aggregate qualifying purchasing activity, with earlier qualifying purchases checked within the seller/domain scope; it is not visitor acquisition or a conversion funnel. Average recognized order value uses recognized gross divided by qualifying orders. Net recorded buyer spend is recorded COD less executed refunds, floored at zero.

Delivery duration includes only pickup/delivery timestamp pairs whose delivery is not earlier than pickup. Missing or invalid pairs are excluded; a missing duration displays as unavailable. Recorded delivery means a delivery timestamp exists, and does not establish an SLA result. Failed attempts use recorded attempt outcomes, restricted to the rider's own attempts for rider reports.

Current low/out-of-stock inventory is a separate parent-product snapshot for commercial reports. It uses `commerce.low_stock_threshold`, excludes archived products, respects seller ownership and seller/product/category filters, and is not date filtered. Parent stock does not establish variation availability.

## Interface and design

The page retains Instrument Sans, white bordered panels, slate text, and the workspace's rose or wine/lavender role accents. It uses the existing workspace tokens instead of adding a new global palette. Date and comparison controls precede metric cards; less frequent filters sit inside a disclosure. Definitions and unavailable measurements remain visible alongside the report.

React SVG charts load only for the analytics surface. The trend offers a measure selector, pointer readouts, and left/right arrow inspection with a live textual readout. Order status has a donut, named counts, and percentages. Rankings show the first ten rows for the selected product, category, seller, or municipality measure; the caption names the measure. Trend paths animate for 650 ms and ranking bars for 500 ms; reduced-motion preferences remove these animations. Report tables provide textual chart alternatives.

Metric cards use an adaptive grid. Charts start in two columns, with ranking across the full width. At a workspace container width of 850 px they stack; at 500 px panel padding tightens and export controls wrap across the available width. Tables scroll inside their panels. Native labeled controls and visible workspace focus styles support keyboard use.

Aggregate sections display the first 20 rows with an explicit export notice. Source orders paginate at 25 rows. Exports contain all permitted rows, independent of displayed pagination. “Print charts” produces a chart summary with reporting metadata, active filters, metrics, selected graph measures and observations. Detail tables and navigation are excluded from this print view; use the full PDF or Excel for all source rows.

## Exports and privacy

All formats contain report metadata, current/comparison metrics, filters, trends, every report section and source order, definitions, and observations.

- CSV combines named sections into one UTF-8 download with a report-section column, consistent column counts, metadata and explicit table headings. Amounts use two decimal places; changes are percentage points under the `Change %` heading. Typed negative numbers remain numeric; formula-like text is escaped using the shared serializer. CSV is plain text and cannot carry colors, column widths or print layout. Import identifiers as text when opening CSV in spreadsheet software to retain leading zeros.
- Excel is an actual OOXML `.xlsx` workbook with separate section sheets, report/period/timezone/comparison metadata, wine-colored table headings, alternating row fills, wrapped text and widths based on formatted values. Currency, counts, hours, percentages and source dates use typed values with appropriate number formats; identifiers retain their identity. Seven heading rows freeze above the table; each worksheet has filters, repeated print headings, landscape A4 setup and page-number footers. Missing values are labeled explicitly, and comparison values say `Not compared` when comparison is disabled. It requires PHP `ZipArchive`; temporary workbook files are removed after use. No generated formulas are used.
- PDF begins with a portrait vector graph summary and selected headline metrics. Landscape detail pages use aligned tables, alternating rows, repeated report/table headings and page counts. Tables wider than seven columns split into horizontal panels that repeat the first identifier column; every permitted row and column is retained. Long text wraps using measured Helvetica widths, and rows that fit on a fresh page are kept together. Its cover shows up to six statuses; the full status section follows. The PDF serializer uses Windows-1252 transliteration, so unsupported Unicode may lose fidelity.

Page and download responses use `Cache-Control: private, no-store`; downloads also use `nosniff`. Generation is recorded through `AdministrativeAuditService` as `analytics_report_exported`, with format and filters. Reports do not publish share links or introduce background collection.

## Limits and unavailable data

`config/analytics.php` limits each requested period to 366 inclusive days and 10,000 selected orders. The comparison cohort is checked independently. Oversized reports return validation feedback asking for narrower filters; they do not silently omit records. The current inventory snapshot also refuses more than 10,000 matching products. Reports eagerly load scoped records and related data for complete aggregation and export, so large permitted reports can still consume meaningful server memory and response time. Browser pagination reduces rendered source rows, not server aggregation work.

On-page observations currently identify cancellation share (at least five orders and at least 20% cancelled by default) and missing finance ledgers for buyer/commercial reports. The cancellation threshold and minimum sample live in configuration. Observations do not send automated alerts.

The report explicitly marks visitor/device/conversion funnels, advertising ROI/CAC/CPA/ROAS, COGS/profit/inventory turnover, on-time delivery, uptime monitoring, and cohort forecasting as unavailable. Required tracking, attribution, costs, inventory history, SLA targets, and instrumentation do not exist in the source records. No substitute rates or predictions are inferred from orders.

## Delivery evidence

The final analytics suite passed eight tests with 122 assertions. Three focused existing sales/refund regression tests passed with 22 assertions across two runs: 11 tests and 144 assertions in total. The production build, Blade view compilation, CSS parsing, route inspection, scoped layout detector, PHP formatting, and whitespace checks passed.

A synthetic PDF and workbook were inspected with Poppler/pypdf and openpyxl: the sample contained three PDF pages and seven workbook sheets, retained typed numeric cells, and contained zero formula cells. The first two PDF pages received visual inspection, including a 120-character wide-name case. PDF wrapping and invalid CSS font shorthand were corrected before the final artifact inspection. These artifact checks do not establish production dataset coverage or a browser visual review.

Authenticated browser visual review was unavailable. Responsive layout, motion, and accessibility descriptions above record source behavior rather than verified rendered screens.

## Export formatting revision — 2026-10-22

The revised layouts preserve AnalyticsService calculations, role restrictions, filters, and full export coverage. No dependency, schema or business workflow was changed. AnalyticsPdfService owns table layout; the shared PDF serializer now supports bold text and per-page dimensions while retaining its default text-report interface.

Validation: the analytics and formatting suites passed 11 tests / 228 assertions. Existing sales and full-refund regression checks passed 3 tests / 22 assertions across two focused runs. The production build and Blade compilation passed. Synthetic exports were opened with openpyxl and pypdf; the workbook's numeric/percentage styles, frozen panes and repeated print headings were verified. Workbook summary/product previews and PDF summary, long-name, continuation and final-definition pages received visual inspection. The 60-order PDF retained the last order in both horizontal panels. No connected browser was available to inspect the browser print dialog; print behavior is source-reviewed rather than browser-verified.

Implementation sources: `app/Services/AnalyticsService.php`, `app/Services/AnalyticsExportService.php`, `app/Services/AnalyticsPdfService.php`, `app/Services/TabularExportService.php`, `app/Http/Controllers/AnalyticsController.php`, `config/analytics.php`, `resources/views/analytics/index.blade.php`, `resources/js/analytics-charts.jsx`, and `public/css/analytics.css`.
