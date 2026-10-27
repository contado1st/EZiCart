# Personal profiles and seller shops

## Where to use it

- **Account & profile** (`/account`): customize the profile photo, cover banner, display name, introduction and preset color. Existing account, address and password forms remain on this page.
- **Seller shop** (`/shops/{seller ID}`): public shop cover, avatar, introduction, product rating, follower count, available-product count and searchable catalog. Open it through **View my shop**, a product's seller name, or **Visit shop** in search results.
- **Buyer account**: private personal profile and paginated followed-shop list. There is no public buyer profile endpoint.

Approved sellers have public shops. Buyers can follow/unfollow a shop and start a private conversation using the existing messaging policy. Guests can browse shops and are directed to sign in for those actions. Administrative and operational accounts can customize their own private profile appearance as well.

The supplied Shopee link was not accessible through the browsing tool. The implementation uses the familiar banner/avatar, shop summary, category/search/sort and product-grid structure while retaining EZiCart colors, typography and existing workflows.

## Customization

Display names are optional, 2–50 characters. Introductions allow up to 1,000 characters and render as escaped plain text. Rose, wine, navy and teal presets apply to the profile/shop surface, rather than changing the application's global theme. Seller business identity remains visible below a customized shop title.

Photo, banner, color and name changes preview locally before saving. Previews are not persistent until **Save appearance** succeeds. Uploaded file selections cannot be restored by the browser after a validation failure; reselect the image if needed. Removal is selectable and can be undone in the preview before saving.

Both image uploads allow JPEG, PNG or WebP up to **2 MB**, matching the inspected local PHP upload limit. Avatars require 96–2048 px on each side and are center-cropped to 400 × 400. Banners require at least 600 × 200 px and at most 4096 × 2048 px; 1600 × 500 is recommended. They are center-cropped to 1600 × 500, with responsive cover cropping in the page. Common JPEG EXIF rotation is applied. GD re-encoding produces JPEG output and does not retain uploaded metadata or active SVG content. GD is required; it is present in the tested runtime. No image dependency was added.

Images are stored on the existing **private** disk. The media endpoint serves only the owner's approved account or an approved seller's public branding. Buyer and other non-seller images are not public. Invalid media types and inactive public shops return 404. Responses use JPEG content type, `nosniff` and private/no-store caching. Replaced/removed files are deleted after the database commit; new files are cleaned up if the update fails. Media paths are not mass assignable and are hidden from model serialization.

## Catalog and follows

The shop catalog scopes every query to the seller, then uses the existing `availableForMarketplace` scope: approved, non-archived, in-stock products with available active variations where applicable. Category filters, literal keyword search, newest/price/rating sorting and 20-product pagination operate within that scope. SQL wildcards in a keyword are escaped.

Shop rating aggregates actual reviews of approved, non-archived products, including products currently out of stock. The page labels this coverage. No response-rate, sales-volume, follower or rating figures are invented. The shop shows municipality/province, not private email, phone number, street address, documents or payout details.

Follows use a unique buyer/seller pair. The write endpoint accepts a desired following state, rather than a toggle, and the service rechecks approved roles under ordered row locks. Repeated follow requests do not create duplicate rows. Unfollow only detaches the authenticated buyer. The buyer's followed active shops paginate six per page; follower identities are not publicly listed. Following does not send notifications or grant access to private conversations.

## Database and deployment

Migration `2026_10_26_004957_add_profile_appearance_and_shop_followers.php` adds five profile appearance columns and the `shop_follows` table. It was applied to the local database during implementation. Other environments need their normal migration step:

```powershell
php artisan migrate
```

Keep the private storage disk writable and preserve it across deployments. No public storage symlink is required for profile images. Existing product images continue to use their established storage handling.

## Verification

The final feature regression run passed **46 tests / 367 assertions**, covering media dimensions/format, ownership, private media access, unapproved-shop hiding, upload rejection, escaped branding, product isolation, search/sort/category filters, follow permissions/idempotency, followed-shop pagination, replacement/removal, buyer addresses, search, home, loading profiles and messaging. The existing account/password rules also passed their focused check (**1 test / 12 assertions**). Node checks passed **17 tests**, exercising photo/name/theme previews, image rejection, removal cancellation, blob cleanup and existing loading behavior.

Production build, Blade compilation, CSS parsing and route inspection passed. The seeded seller shop returned **HTTP 200** from the local server. Live visual browser review was not completed; automated HTTP/DOM checks do not establish a visual sign-off.

Key files: `SellerShopController`, `ProfileAppearanceController`, `ProfileAppearanceService`, `ShopFollowService`, `resources/views/shops/show.blade.php`, `resources/views/account/appearance.blade.php`, `public/css/profiles.css`, and `public/js/profiles.js`.
