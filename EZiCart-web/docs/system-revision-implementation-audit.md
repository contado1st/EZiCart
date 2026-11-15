# System revision brief: implementation audit

**Date:** 15 November 2026 (Asia/Manila). **Scope:** Laravel web and shared services. Based on the supplied System Revision and Implementation Brief and the [assessment and plan](system-revision-implementation-plan.md) prepared before implementation.

## Outcome

The remaining brief goals have been integrated with existing workflows. Existing dispatch eligibility, rider acceptance, custody scans, administrative approvals, notifications, account permissions, product variations and financial controls were reused. **Final regression: 869 PHP tests passed with 7,172 assertions; 78 frontend tests passed; production build passed.** External mail delivery, real-device playback and browser visual acceptance remain unverified.

| Brief goal | Disposition | Result |
| --- | --- | --- |
| Need Actions near dashboard top | Enhanced existing feature | Existing role-scoped Next steps panel is now open by default, named Need Actions, and presented as an accessible table layout before workspace content. Order entries include reference, status, responsibility and submission time. Approval groups link to their existing detailed queues. |
| Registered-email OTP for signed-in password changes | Added | A separate challenge protects both buyer and other-module password forms; current-password and password-strength protections remain in force. |
| Product images fitting 1280 × 720 | Added | Accepted new cover, gallery, variation and private-draft images are decoded, resized with their aspect ratio and optimized without upscaling. |
| Product videos up to 60 seconds | Added | Server probes duration/streams, rejects unverifiable/long inputs, transcodes accepted MP4/WebM to bounded MP4, and verifies the output. |
| Visible product-video autoplay | Enhanced gallery | Muted visible playback, single-video coordination, hidden-slide/page/background pauses, controls, reduced-motion and data-saving behavior. |
| Redundant controls/labels | Reviewed and refined | Removed repeated action-count wording next to the count badge; each action row now has one primary task link and one metadata cell. Kept distinct navigation, return, retry, scan, proof and approval actions. |
| Hero subject and bottom fade | Refined existing surface | Existing hero image enlarged through its scale/anchor, with a bottom mask and paper-gradient blend. Existing editorial typography, assets and responsive rules retained. Visual acceptance is pending. |
| Automatic eligible rider assignment | Reused and extended | Existing nearest-rider offer/capacity/coverage/acceptance engine remains authoritative. Scheduled processing now initiates eligible stages once; exhausted work is not automatically restarted. |
| Logistics/sorting approval workflow | Reused, visibility enhanced | Existing intake, sorting, transfer, retry, recovery, profile and capacity review paths remain authoritative. Own-hub retry approvals and unresolved assignment queues are surfaced in Need Actions. |
| Status, notifications and audit history | Reused and extended | Existing parcel statuses and events retained; password change gets a non-sensitive audit event. No new generic parcel Approved/Rejected status was introduced. |

## Password security

`PasswordChangeService` stores one account-bound challenge in `password_change_challenges`: hashed random six-digit code, registered email, password fingerprint, expiry, attempt count and reference. The default expiry is five minutes, incorrect-code limit five, resend cooldown 60 seconds. User-row locking serializes challenge issuance and final updates across tabs/devices.

Account settings provide a current-password code-request form and a code field on the existing change-password form. The new password is accepted only when the current password, strong-password validation, registered-email binding, unchanged password fingerprint, expiry and code all pass. Successful verification consumes the challenge in the password-update transaction and retains device-session/remember-token protections. A repeated or concurrent request cannot consume the same code twice.

Incorrect-code attempts persist even when the response is a validation error. Resends invalidate the prior challenge; expiration is enforced at the exact deadline. Email changes or an intervening password change invalidate the binding. The code is excluded from flashed input, returned messages and password audit data. Sender exceptions are replaced with a generic reported delivery failure because raw transport exceptions can contain message bodies. Failed delivery deletes only that issuance's pending challenge.

Delivery prefers the existing Gmail API channel when configured, otherwise the configured real mail transport. Log, array and transports that could fall back to logging are blocked for real password OTP issuance. Notification fakes are used only in isolated tests. No new credential, sender address or SMTP password was invented. The configured runtime currently reports **SMTP**, with Gmail API **not configured**; live email delivery was not attempted or claimed.

New routes: `account.password.code` (POST, authenticated, throttled) and the updated existing `account.password.update` (PATCH, authenticated, throttled). Registration and forgotten-password OTPs keep their existing independent workflows.

## Product media

`ProductUploadProcessor` centralizes new product media writes. Images accept the existing JPEG/PNG/WebP MIME and size rules, then require valid decoding and a maximum source area of 12 megapixels to bound memory. Existing upstream variation/draft source-dimension restrictions are retained. GD resizes to fit **1280 pixels wide and 720 pixels high**, preserving aspect ratio, alpha where supported, and JPEG orientation. Small files are not upscaled. Re-encoding strips source metadata; JPEG/WebP use controlled quality and PNG compression is enabled.

The processor is called by cover creation/replacement, gallery staging, variation-image preparation and private-draft uploads. Draft images remain private; hydrated draft files are checked again when submitted. Transaction-failure cleanup and historical image-reference protection remain in the existing services.

For video, FFprobe requires a readable duration above zero and at most **60 seconds**, one video stream and supported source dimensions. Browser metadata is not used as the security boundary. FFmpeg converts accepted input into MP4 with H.264, optional AAC audio, metadata removal, fast-start placement, bounded dimensions and two encoding threads. Probe and encoding calls have time limits, structured arguments and a local-file protocol whitelist. Optimized output is probed again and must meet the existing 20 MB configured cap. Missing tools, malformed input, encoding failure and oversized output fail with a validation message. Long videos are rejected, not silently trimmed.

FFmpeg/FFprobe **9.0.2** were installed per explicit user approval using the Windows package registry's `Gyan.FFmpeg` package. The installer verified its package hash. The actual executable paths were configured in the local private `.env`; no binaries or credentials were put in application source. Deployment supports `FFMPEG_PATH` and `FFPROBE_PATH` or executable discovery from PATH. Existing PHP/webserver upload/request limits still apply and are shown in the upload form.

Existing published media and purchased-image snapshots were not rewritten. These restrictions govern new uploads and draft submission; this is not a historical media migration. An existing published video is not retroactively shortened. A separate reviewed migration would be needed to bring every historical asset under the new limits.

## Playback, action panel and UI review

Gallery videos retain native controls and inline playback. At least half of a video must be in view, its slide must be visible, and the page must be active before muted autoplay is attempted. Other product videos pause when one plays. Hidden slides, off-screen videos and page exit pause playback; browser autoplay rejection is tolerated so manual controls remain useful. Reduced-motion preference, data-saving mode, or missing intersection support prevents automatic playback. No forced looping was added.

The Need Actions panel reuses its existing polling URL, role-scoped service, badges and current task links. It does not submit mutations from a summary row. Order rows display available metadata; group rows represent an aggregate queue and do not invent individual request dates. Open the queue to see the existing record-level details and review controls. Own-hub retry proposals and Unassigned jobs are added without exposing other hubs' review actions. Failed polling retains last-known counts and connection feedback.

The redundancy review checked the shared workspace, account password forms, product media controls and landing navigation/CTA. Count wording duplicated beside its badge was consolidated. The two password actions have distinct purposes (request a code, then change the password). Product gallery controls, scan versus handover controls, and manual versus automatic dispatch each serve distinct operations and were retained. No system-wide deletion of similar-looking buttons is claimed.

The hero uses the existing `hero_asset.jpg` and cream editorial design. Its subject scale rises from 1.04 to 1.12 at rest, the transform anchor favors the subject, and the bottom blends into the existing page surface. Desktop/tablet/mobile and reduced-motion code paths were preserved. Source inspection and a successful build establish implementation, not a browser-verified composition.

## Dispatch and logistics approvals

`dispatch:process --apply` now considers up to 25 oldest staged requests per approved hub per run when `DISPATCH_AUTOMATIC_START=true` (default). New cycles require the existing hub scope, pickup request/first-attempt Sorted stage, recorded weight, payment readiness, no active incident/cancellation/transfer, and a current hub-verified origin pin. Work without those prerequisites remains in its existing operational queue. Processing is per scheduled run, not a promise of immediate assignment at request time.

The service locks the hub/fleet and order, then rechecks whether a cycle already exists for that physical stage. It uses the established eligibility/ranking/offer engine and therefore retains freshness, explicit rider availability, area coverage, verified capacity, active workload, notification and manual intervention rules. The selected rider must still accept the offer; no automated step invents parcel possession, hub release, a delivery scan, COD collection or payout.

Once a stage has an exhausted/Unassigned cycle, the scheduler does not restart it. The hub reviews the reason and deliberately starts a new cycle or assigns through its existing authorized manual path. Pickup and later delivery are distinct stages. Retry/recovery and dedicated hub-transfer work keep their existing guarded scheduling. The existing expiry/decline cascade, auditing and Unassigned notification remain in use.

Sorting-center/logistics approvals reuse existing operation-specific routes and history. For example, a retry proposal stays pending until the scoped hub approves through validated dispatch or rejects with a reason; scanned custody/capacity rules still govern assignment. Recovery exceptions, profile revisions, rider capacities and account applications retain their own administrators and review semantics. A summary-panel click is not an approval, and no generic direct status override was added.

## Verification

| Check | Result |
| --- | --- |
| Complete installed PHP suite | **869 passed, 7,172 assertions** |
| All frontend scripts/tests | **78 passed**, including four new autoplay tests |
| OTP/media focused checks | 13 passed, 35 assertions; real tiny video fixtures, not invalid placeholder MP4 headers |
| Earlier security/dispatch/attention checks | 33 passed, 175 assertions, before the last two additional security cases; included in the full suite |
| Earlier media/catalog regression | 34 passed, 231 assertions; included in the full suite |
| Production build | Passed; existing optional fontaine warning and Vite timing advice retained |
| New MySQL copy probe | Migration up/down/reapply; hash-only challenge storage; two workers produce one committed password change, one rejection and one audit; source business counts unchanged |
| Existing dispatch MySQL copy probe | Acceptance replay, automatic/manual capacity, expiry cascade, incident hold/money preservation; results logged separately |
| Syntax/style/whitespace | Changed PHP syntax checks, Pint and `git diff --check` passed |
| Configured runtime | GD and both FFmpeg tools available; 23 users, 15 products, 21 orders retained; zero password challenges and zero dispatch cycles |
| Browser/device visual acceptance | Unverified: discovery retried, Chrome present, browser-control host unavailable |

The new scheduler initiation is exercised with SQLite integration tests: preview does not mutate, a valid stage starts once, repeated processing does not duplicate/restart exhausted work, and missing origin pins do not initiate a cycle. The MySQL dispatch race probe exercises the shared existing offer/assignment boundaries, not two concurrent scheduler-initiation commands. Those scopes must not be conflated.

The older catalogue/media fixtures contained MP4 placeholder bytes. They now use small real MP4/WebM fixtures so the new duration validation tests actual media. The existing signed-in password-success regression now requests and supplies a real test notification code; its password, remember-token and stale-device assertions remain. Other existing test assertions were retained. Test counts overlap and must not be added together.

## Schema, local rollout and configuration

One additive migration creates `password_change_challenges`. The local private backup is `storage/app/private/backups/before-system-revision-brief-20261115-074910.sql`; the migration then applied successfully. No account password, mail credential, parcel assignment, balance or historical media was changed in the configured database by verification. Disposable MySQL schemas and workers were cleaned. No live OTP was sent. No production deployment is claimed.

Deployment requirements:

1. Apply `2026_11_15_070911_create_password_change_challenges_table` before serving the new password-change paths.
2. Configure working SMTP or the existing `GMAIL_API_*` credentials and sender. `MAIL_MAILER=log` cannot issue password-change codes.
3. Set `PASSWORD_CHANGE_OTP_MINUTES` as needed (default 5). Attempts/cooldown use the shared security config.
4. Install FFmpeg/FFprobe with H.264/AAC support and set `FFMPEG_PATH`/`FFPROBE_PATH` for the PHP service account if PATH differs. Keep GD available.
5. Build frontend assets and ensure the actual server scheduler runs. `DISPATCH_AUTOMATIC_START=false` disables only automatic cycle initiation while existing offer processing/manual tools remain available.

Dropping the new challenge table invalidates outstanding password codes and is incompatible with the new password paths until code/schema are rolled back together. It does not restore earlier account passwords. Historical media backfills, deployed cron health, real GPS/permission behavior, external mail delivery and authenticated visual/device checks remain separate acceptance work.

## Evidence and files

Evidence prefix: `storage/logs/system-revision-`: `regression.txt`, `junit.xml`, `errors.txt`, `frontend.txt`, `build.txt`, `otp-media.txt`, `focused.txt`, `security-dispatch.txt`, `mysql.json`, `dispatch-mysql.json`, `runtime.json`, `migration.txt`, `pint.txt`, `ffmpeg-install.txt` and `design-context.txt`. Local logs are not public downloads.

Main changed files: `AccountController`, `PasswordChangeService`, `PasswordChangeCodeNotification`, `config/account-security.php`, the challenge migration and account partials; `ProductUploadProcessor`, the four product upload writers and `config/product-media.php`; `WorkflowAttentionService`, its component/script/styles; `DispatchOfferService`, `ProcessDispatchOffers`, the Order relation and dispatch config; storefront hero CSS/JS; route/exception/config examples; focused and existing regression fixtures/tests.

Reproducible probes: `php scripts/check-system-revision-brief.php` (local/testing disposable MySQL only) and `php scripts/verify-system-revision-brief.php` (read-only runtime). The existing `scripts/check-final-missing-revisions.php` was rerun for shared dispatch safeguards.

Design metadata remains stale relative to DESIGN.md; it was reported and not regenerated as a side effect of these feature revisions.
