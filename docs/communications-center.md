# Communications center

Updated October 21, 2026. Messages are private conversations between exactly two users, alongside a separate Notifications feed. The existing drawer, sidebar, workspace typography, and commerce/logistics palettes are retained. `PRODUCT.md`, `DESIGN.md`, and `.impeccable/design.json` remain unchanged.

## Entry points and authorization

The topbar chat icon and floating Messages button toggle the drawer. Workspace sidebar Chat/Messages entries open the full inbox. Shared communications tabs link Messages and Notifications.

| Route | Behavior |
| --- | --- |
| `GET /chat` | Lists conversations containing the authenticated user. |
| `GET /chat/contacts` | Lists authorized contacts. |
| `POST /chat/start/{recipient}` | Creates or reopens the unique private conversation with that recipient. |
| `GET /chat/conversations/{conversation}` | Opens history for one of its two participants. |
| `POST /chat/conversations/{conversation}/messages` | Sends to the other participant after current contact authorization. |

Supported roles are `buyer`, `seller`, `courier`, `sorting_center`, `admin`, `ecommerce_admin`, and `logistics_admin`. Contacts must be approved accounts. Contact eligibility includes current order participants, historical sorting centers recorded in parcel tracking, and sorting centers/riders linked through active service areas. Buyers can initiate pre-sale chats with sellers; sellers can reply once the pair's conversation exists. Commerce users can contact relevant commerce administrators, and operational logistics users can contact logistics administrators. Administrators can contact accounts in their managed role domains and other administrators.

Administrative role or order access does not grant access to another pair's private history. Reads and sends enforce participant membership on the server. Sending also rechecks current contact eligibility. Participants retain their own history if the relationship ends, while the composer becomes unavailable.

Optional `order_id` context is authorized for both participants. It adds an order panel and role-appropriate View order link without creating another conversation. Each pair shares one private history across orders.

### Legacy order routes

Existing role-specific `*.orders.messages.show` links open a recipient chooser for an authorized order. They no longer expose shared order history. The corresponding `*.orders.messages.store` POST requires `recipient_id`, checks both users' order access and contact eligibility, and sends one private message. It never broadcasts to order participants.

Old `OrderConversation` and `OrderMessage` records remain stored, inaccessible through messaging routes and unmigrated. Group history is not copied into private conversations. Existing order-message notification links resolve through recipient selection.

## Messages interface

Desktop shows the conversation list beside a selected thread. At 760px and below, the inbox shows the list and a selected thread provides an All conversations link. Lists show the contact, real latest message preview and date, and recipient-specific unread count. New private chat opens the contact chooser.

Inbox and contact search match names or business names, with a 100-character limit and literal SQL wildcard handling. The inbox paginates 15 conversations ordered by activity and ID; contacts paginate 20 people. Threads paginate 40 messages per page, presenting each page chronologically.

The thread identifies the other participant and their role, with timestamps and own/other bubbles. The composer has a 2,000-character limit, live counter, and Ctrl+Enter or Command+Enter submission. Sending returns to the newest page, retaining optional order context and compact mode. Refresh is manual; there is no realtime transport, presence, or typing indicator.

### Drawer

The floating Messages button remains on the right; the buyer's For You entry remains on the left. The nonmodal `aside` closes through its toggle, close button, or Escape, including from the embedded inbox. Dismissal restores launcher focus; controls expose expanded state.

The drawer loads `/chat?panel=1` once on first opening. Hiding the existing frame preserves its draft while the workspace page stays open. Compact mode uses the same private routes and authorization. Search, contacts, pagination, navigation, and successful sends retain `panel=1`. View order opens the parent window; Open full inbox remains available.

### Read state and badges

Messages badges count unread `DirectMessage` rows addressed to the current user. Only the selected recipient receives a `DirectMessageNotification`. Opening an authorized private conversation marks only messages addressed to that user read and clears only that user's direct-message notifications for the conversation. A sender opening history does not mark the recipient's messages read.

Notifications badges count all of the user's unread notification records. Marking one or all notifications read does not mark direct messages read or reduce Messages counts. Legacy group-message notifications do not contribute to the new Messages count.

Zero badges are hidden; values above 99 display `99+`, with full counts in accessible labels. Badges remain visible in collapsed navigation. Initial layout counts refresh through authenticated `GET /communication-counts` with `Cache-Control: private, no-store`. Polling runs every 30 seconds while visible, on focus/visibility changes, and after iframe loads. Failure preserves the last count.

## Notifications

The existing feed retains all updates, orders, messages, stock, and account categories, combinable All/Unread filters, and 20-item pagination. Read operations use CSRF-protected POST routes:

| Route | Behavior |
| --- | --- |
| `/notifications/{notification}/read` | Marks one owned notification read. |
| `/notifications/read-all` | Marks the account's unread notifications read. |
| `/notifications/{notification}/open` | Marks an owned notification read and opens a safe internal destination. |

Operations resolve through the authenticated user's notification relationship. Opening accepts internal paths or URLs on the request/configured application host, redirecting using path and query; unsafe destinations return to Notifications. A direct-message destination independently enforces private history authorization. Mobile categories wrap above the feed and actions reflow beneath content.

## Implementation and operations

- `app/Models/DirectConversation.php` and `DirectMessage.php`: immutable two-user pairs, messages, and recipient read timestamps. IDs are ordered before creation, with a unique pair key preventing duplicates systemwide.
- `app/Services/DirectMessagingService.php`: contact rules, authorized creation, locked sending, optional order authorization, and sole-recipient notification delivery.
- `MessagingInboxService`, `ChatInboxController`, and `DirectMessageController`: participant-scoped search, inbox, contacts, history, and private routes. `OrderMessageController` adapts legacy routes to recipient selection and private sending.
- `CommunicationUnreadService`, `CommunicationCountController`, and `DirectMessageNotification`: recipient message counts and alerts, independent of notification read state.
- `resources/views/orders/{inbox,contacts,messages}.blade.php`, shared communications layouts, and conversation-list components: inbox, recipient chooser, and private thread. Existing drawer, compact shell, navigation/count components, and notification feed remain in use.
- `public/css/communications.css`, `message-drawer.css`, and `communication-badges.css`, with corresponding JavaScript: scoped styles, composer behavior, drawer controls, and polling. These assets are served directly; Vite entrypoints and dependencies are unchanged.

The additive migration `database/migrations/2026_10_21_074125_create_direct_conversations_and_messages_tables.php` creates `direct_conversations` and `direct_messages`. It was applied successfully to the local database. Other environments must apply it before exposing private chat. Old group tables and records are retained. Existing transaction-aware notification delivery and queue configuration continue to apply.

Styles inherit the established role palettes. Source provides labeled controls, visible focus, long-content wrapping, and reduced-motion rules. These are implementation descriptions, not visually verified results.

## Recorded checks and limits

The implementation owner supplied these results; documentation inspection did not run checks:

| Check | Result |
| --- | --- |
| Combined CommunicationsCenterTest and OrderMessagingTest | Passed: 17 tests, 168 assertions, including historical sorting-center and service-area coverage. |
| Focused RoadmapFeaturesTest chat test | Passed: 1 test, 6 assertions; a separate run. |
| Blade template compilation | Passed. |
| Chat route listing | Passed: five chat routes confirmed. |
| CSS parsing | Passed: zero warnings. |
| `git diff --check` | Passed. |
| PHP formatting | Pint applied to affected PHP. |
| Browser and responsive visual review | Blocked: no enabled browser or app surfaces. |
| npm build for private-chat public-asset changes | Not run. |

Fresh code review identified a historical sorting-center contact issue. It was corrected and the reviewer reported it resolved through source inspection. Earlier shared-chat, drawer, and badge test/build totals describe historical implementations and do not establish private-chat coverage. Live mail delivery and production worker operation were not verified by this documentation pass.
