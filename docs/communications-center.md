# Communications center

Implemented October 21, 2026. This surface extends the existing authenticated workspace with separate Messages and Notifications frames, using marketplace inbox patterns inspired by Shopee. It retains the workspace font and role palette through `--workspace-accent`, `--workspace-accent-hover`, and `--workspace-soft`. The global design documentation and tokens remain the authority for the surrounding workspace.

## Entry points and access

The topbar chat entry opens `GET /chat` (`chat.index`); couriers also have a sidebar entry. The shared communications tabs link Messages and `GET /notifications` (`notifications.index`). Existing order message links continue to open the role-specific `*.orders.messages.show` route; sending uses the corresponding `*.orders.messages.store` route.

`/chat` supports the seven existing operational roles: `buyer`, `seller`, `courier`, `sorting_center`, `admin`, `ecommerce_admin`, and `logistics_admin`. Buyers and sellers list their own orders. Riders list orders for which they are the current pickup or delivery courier. Sorting centers list assigned orders and orders with their tracking involvement. Existing administrator access and per-thread participant authorization are retained. The interface does not create private buyer/seller/rider channels: each order has a shared conversation visible to its authorized participants.

## Messages

Desktop uses a conversation list beside the selected thread, with an empty selection panel on the inbox route. At widths of 760px and below, the inbox shows the list and a selected conversation shows the thread with an All conversations link. The list presents the partner, order number, actual latest message preview, and latest message date; orders without messages can start a conversation.

Search matches an order number, seller business name, or buyer first name. Input is limited to 100 characters, and SQL wildcard characters are escaped so they are treated literally. The list paginates 15 orders per page and sorts by latest message, then order ID. Threads paginate 40 messages per page, presenting each page chronologically.

The thread includes order context, a role-appropriate View order link, sender and role labels, timestamps, and distinct own/other message bubbles. The composer has a 2,000-character limit, a live counter, and Ctrl+Enter or Command+Enter submission. Sending redirects to the canonical role-specific thread URL so a message sent while viewing older history returns to the newest page. Refresh is manual.

Message read state keeps the existing shared `read_at` behavior: opening a conversation marks unread messages from other senders as read. It is not a per-participant receipt. There is no realtime transport, presence, typing indicator, or delivery receipt.

## Notifications

Notifications use a category sidebar and feed, with categories for all updates, orders, messages, stock, and account activity. All/Unread filters can be combined with a category. The feed paginates 20 notifications per page; displayed total and unread counts cover the account's notifications.

Read operations use CSRF-protected POST routes:

| Route | Behavior |
| --- | --- |
| `/notifications/{notification}/read` | Marks one owned notification read. |
| `/notifications/read-all` | Marks all unread notifications for the account read. |
| `/notifications/{notification}/open` | Marks an owned notification read and opens its safe internal destination. |

Individual operations resolve notifications through the authenticated user's relationship. Opening an update accepts internal paths or URLs on the request/configured application host, then redirects using the path and query; unsafe destinations return to Notifications. On mobile, category navigation wraps above the feed and notification actions reflow beneath the content.

## Implementation references

- `resources/views/layouts/communications.blade.php`: shared heading, tabs, status, and validation feedback.
- `resources/views/components/conversation-list.blade.php`: conversation search, previews, selection, and pagination.
- `resources/views/orders/inbox.blade.php` and `messages.blade.php`: inbox selection and shared thread.
- `resources/views/notifications/index.blade.php`: categories, filters, feed, and read actions.
- `app/Services/MessagingInboxService.php`: actor-scoped list, search, ordering, and role-specific destinations.
- `ChatInboxController`, `OrderMessageController`, and `NotificationController`: rendering and protected operations; `OrderConversation.latestMessage` supplies real previews.
- `public/css/communications.css` and `public/js/communications.js`: scoped responsive styles and composer behavior. These assets are served directly by the shared layout; Vite entrypoints were not changed.

The implementation adds no schema or dependency changes. Styles provide visible keyboard focus, labeled controls, wrapping for long content, and reduced-motion rules. Local differences in color, typography, and radius flagged by the design detector were reviewed as an extension of this workspace surface; they did not trigger a global token rewrite.

## Recorded checks and limits

The following results were supplied by the implementation and review work; documentation inspection is not an additional test run.

| Check | Result |
| --- | --- |
| Communications center and existing order messaging coverage | Passed: 10 tests, 108 assertions. |
| Focused roadmap chat and logistics notification ownership coverage | Passed: 2 tests, 14 assertions. |
| Regression for sending from older message history | Passed: 1 test, 11 assertions. |
| Blade compilation | Passed. |
| JavaScript syntax | Passed. |
| CSS parsing | Passed, zero warnings. |
| `npm run build` | Passed after the user authorized a retry. Existing optional `fontaine` warning remains. |
| Browser and responsive visual review | Blocked: no enabled browser or app surfaces were available. |

The first review identified the older-history send redirect issue described above; it was fixed and covered by the regression check. The follow-up reviewer scored that finding resolved at code scope. Existing roadmap documentation is preserved in `docs/roadmap-implementation-report.md`.
