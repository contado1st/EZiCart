# Product image and video carousel

## Seller workflow

Open **Products → Add New Product** or edit an existing listing.

- **Cover image:** one JPEG, PNG or WebP image, up to 2 MB. Product cards, cart items and other existing thumbnail surfaces continue using this image.
- **Gallery images:** up to eight additional JPEG, PNG or WebP photos, up to 2 MB each. New files are appended in their selected order. If a product has no cover and you upload gallery images without a separate cover, the first selected photo becomes its cover and is not duplicated in the gallery.
- **Product video:** one MP4 or WebM file, up to 20 MB, subject to the server limits shown on the form. A new video replaces the saved video. Playback depends on the browser supporting the video's codec; the application does not transcode uploads.
- Selected files have local previews. On edit, select saved files to remove and save the product. Cover replacement/removal uses the cover field and its removal checkbox.
- A reviewed listing returns to **pending review** when photos or video are added, replaced or removed. Administrators can inspect its carousel under **Product compliance → Review photos & video** before approving it.

The product details page displays the cover first, followed by saved gallery media in upload order. It provides thumbnails, previous/next buttons, a media counter, left/right/Home/End keyboard navigation and horizontal touch swipes on photos. Videos use native controls, do not autoplay, and pause when another slide is selected or the page is left. Without JavaScript, the media strip remains horizontally scrollable.

Existing single-image products work without a media backfill. No sample media or seller listings were changed by this feature.

## Local PHP upload limits

At implementation time, the installed CLI PHP configuration has **2M per file / 8M per request**. The form reports the current running server's limits and prevents an oversized selection before submission. PHP or a reverse proxy may reject a request before Laravel validation runs.

To use videos above 2 MB locally, stop the existing development web server and run this from the project root:

```powershell
.\scripts\serve-media.ps1
```

The script starts PHP's development server on `127.0.0.1:8000` with **24M per file / 64M per request**. It uses Laravel's existing router and affects only that server process. It does not edit the installed `php.ini`. An alternate local port is supported:

```powershell
.\scripts\serve-media.ps1 -Port 8002
```

Use this launcher in place of the current development web server. Queue and scheduler workers can continue in their own terminals. For a hosted environment, configure equivalent PHP and proxy body limits with that host's normal deployment settings. The application's 2 MB image and 20 MB video limits remain authoritative.

## Persistence and permissions

The `product_media` table stores gallery metadata in a `Product::media()` relationship. The existing `products.image_path` remains the cover. Product media is stored on the existing public disk with generated filenames; uploads are product presentation content, not private verification documents.

Only the owning seller can update a product or remove its media. Removal IDs are validated against that product. Server validation checks image/video MIME types, per-file sizes and retained-plus-new gallery capacity. Product row locks and transactions protect gallery changes. Failed transactions clean staged files; committed removals clean old files. Archiving keeps gallery files and review history; an actual product deletion cleans associated files after commit.

For other databases, run the normal migration command:

```powershell
php artisan migrate
```

The new migration was applied to the local database on **2026-10-27**.

## Verification

- `ProductMediaTest`: **8 tests / 79 assertions passed** — uploads, cover promotion, admin/public rendering, replacement, removals, review invalidation, capacity rollback and cleanup, MIME/size rejection, ownership and foreign-media checks, hard deletion and archival retention.
- `ProductComplianceTest` plus `HomeControllerTest`: **7 tests / 66 assertions passed**.
- Existing variation-edit regression: **1 test / 4 assertions passed**.
- Existing complete purchase-to-review journey: **1 test / 85 assertions passed**.
- Carousel interaction tests: **3 passed**, covering wrapping, selection, keyboard/touch navigation, and video pause behavior.
- Production frontend build, Blade compilation, JavaScript syntax and PHP formatting passed.
- Live browser visual review was blocked: the native Chrome inspection timed out. No desktop/mobile visual approval or real video-codec playback claim is made from the automated tests. The development launcher was provided without stopping the existing running server.
