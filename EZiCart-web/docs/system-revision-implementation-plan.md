# System revision brief: assessment and implementation plan

**Prepared:** 15 November 2026 (Asia/Manila), before implementation. **Scope:** Laravel web and shared services. Source: the user's supplied System Revision and Implementation Brief. Preserve the current role permissions, physical parcel evidence and financial controls.

| Goal | Existing implementation | Remaining work |
| --- | --- | --- |
| Need Actions | Role-scoped WorkflowAttentionService, badge polling and Next steps component already precede workspace content | Make the section open and clearly named; add structured reference/status/owner/date context, and missing operational approval queues without duplicating mutations |
| Password-change OTP | Registration/recovery OTP and Gmail API sender exist; signed-in AccountController password change has no OTP gate | Dedicated hashed, expiring, single-use challenge with persistent attempt/cooldown limits, registered-email binding, password fingerprint, and atomic consume/password update; update both account forms |
| Product images | MIME/size limits and cover/gallery/variation/private draft paths already exist | Centralize decoding, aspect-preserving 1280-by-720 resizing and optimization across all new uploads; preserve historical media |
| Product videos | MP4/WebM, size limits, one video and private drafts already exist | Server-side duration/stream validation and bounded FFmpeg optimization, reject over 60 seconds, apply on draft and publish/edit paths; approved local FFmpeg/FFprobe dependency |
| Autoplay | Gallery already pauses hidden slides and on page exit | Add muted visible-video playback, one video at a time, intersection/page visibility handling, controls and reduced-motion/data-saving behavior |
| UI duplicate controls | Existing workspace tabs, buttons, pagination and action confirmations | Review actual action targets and consolidate proven duplicate controls only; preserve role and physical workflow actions |
| Landing hero | Existing editorial hero uses hero_asset.jpg, responsive positioning and paper wash | Enlarge subject emphasis and introduce a bottom fade using the established cream/editorial style; inspect current asset before choosing positioning |
| Rider automation | Eligibility/position/coverage/capacity, timed offers, three-candidate cascade, Unassigned queue, manual recovery, notifications/audits already exist | Reuse and verify; add guarded scheduled initiation for eligible staged work only if the current commands do not initiate it; do not restart exhausted cycles automatically |
| Logistics approval | Existing scoped intake, sorting, transfer, retry, recovery, profile/capacity and finance approvals with audit/events | Surface relevant waiting work in Need Actions and reuse existing approval/rejection routes; never introduce a competing generic parcel status or bypass scans |

## Execution order

1. Add password challenge schema/config/service and registered-email delivery, integrate request/confirmation controls, test expiry/attempt/replay/ownership/mail failures.
2. Add the shared product upload processor using installed PHP GD and approved FFmpeg/FFprobe; connect cover, gallery, variation and private-draft writers with cleanup on failure.
3. Refine the existing attention component and its payload/polling, video playback and hero. Keep existing visual systems per surface.
4. Complete safe dispatch initiation and approval queue visibility after checking current command behavior. Preserve manual intervention and all existing state transitions.
5. Run focused backend/frontend checks, migration roundtrips and relevant integration regression, then build assets. Back up before applying additive local schema changes. Finish with a dated audit listing implemented, reused/skipped, verified and externally unverified outcomes.

## Constraints and acceptance

- No OTP may be returned or flashed/logged. Reject logging/array email transports for real password OTP delivery; test notification fakes remain isolated. Gmail/API or configured SMTP credentials remain environment-owned.
- Store only challenge hashes, never new passwords in challenge storage. Current-password validation and existing device/session protections remain required.
- Reject video validation if the configured probe/transcoder is unavailable; never trust browser metadata or accept an unchecked file. Keep subprocess arguments structured, time bounded and output private.
- Limit image decode memory and video processing resources. No upscaling, no stretching and no silent truncation of long videos.
- Existing active media is not retroactively rewritten. Any historical migration requires a separate reviewed operation.
- Deployment mail delivery, real browser/device playback, actual scheduler health and physical GPS/scans require separate acceptance evidence; automated checks must not be described as those live observations.
