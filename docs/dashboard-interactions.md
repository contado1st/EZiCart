# Dashboard interactions

This implementation extends the existing workspace styles in [DESIGN.md](../DESIGN.md). It adds form and panel feedback, bounded motion, and confirmation of important actions across authenticated modules. It does not establish a new global visual system. Existing role colors, routes, server authorization, and application services remain authoritative.

## Sources and integration

| Source | Responsibility |
| --- | --- |
| `resources/js/interactions/decision-dialog.jsx` | React confirmation dialog and form submission handling |
| `resources/js/app.js` | Dynamically imports the dialog only when a `form[data-decision]` exists |
| `resources/css/shared/interactions.css` | Tailwind v4 refinements for workspace panels, labels, headings, and dialog typography |
| `resources/css/shared/app.css` | Imports the shared interaction utilities |
| `public/css/interaction-feedback.css` | Form states, panel focus, button feedback, dialog styling, and motion |
| `resources/views/layouts/app.blade.php` | Loads interaction styles and the existing Vite application entrypoint |
| `resources/views/seller/orders/waybill.blade.php` | Loads interaction styles and JavaScript for the standalone waybill surface |

React and ReactDOM were explicitly approved for this work; the installed versions are (19.3.1). React owns the added dialog mount, while Blade continues to render the original forms and pages. The mount uses [`createRoot`](https://react.dev/reference/react-dom/client/createRoot).

## Confirmation behavior

Important forms carry explicit `data-decision` metadata. Covered actions include logout, checkout, order receipt and cancellation, parcel custody, assignments and transfers, account moderation, deletion, payouts and refunds, and password changes. Ordinary search, chat, and simple inputs do not acquire a confirmation merely because they are forms.

Native form validation precedes the dialog. The dialog initially focuses **Go back**, describes the action, and displays a selected status or decision when the form supplies `status`, `decision`, `resolution`, or `outcome`. A named submit button takes precedence for its matching field, preserving the distinction between actions such as approving and rejecting a cancellation.

Confirmation submits the original form with `requestSubmit()` and its original submitter. This preserves normal validation, CSRF fields, uploads, method overrides, and named button values. Cancellation and Escape close the native modal and restore focus to the trigger when it remains visible and usable, with the workspace navigation toggle as a fallback. Native modal behavior makes the background inert; navigation Escape handlers defer while a dialog is open.

Approved forms receive `aria-busy` and the submitting button receives a spinner. The button is not disabled, so its submitted name and value remain available. Repeated submissions are guarded while the form is pending. The `pageshow` handler clears dialog and busy state, including after restoration from the browser's back/forward cache.

An inline `window.confirm()` fallback remains active until React is ready, including when the native dialog API is unavailable. Without JavaScript, forms retain normal server submission; confirmations are therefore a usability feature, never an authorization or business validation boundary.

## Extending a form

Use the existing pattern on an important form, retaining its route, method, CSRF directive, fields, and submit buttons:

```blade
<form method="POST" action="{{ route('logout') }}"
    data-decision="logout"
    data-decision-title="Sign out?"
    data-decision-message="You will leave your current session. You can sign in again when you are ready."
    data-decision-label="Sign out"
    data-decision-tone="normal"
    onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
    @csrf
    <button type="submit">Sign out</button>
</form>
```

Use a stable action identifier for `data-decision`, a direct title, a concrete consequence in the message, and a specific action verb for the confirmation label. Set `data-decision-tone="danger"` for destructive consequences. Render dynamic text through Blade's normal escaped output. Do not embed a second confirmation or replace submission with `form.submit()`, which skips the normal validation and submit event path.

## Visual and motion guidance

Workspace refinements are scoped to `.dashboard-workspace`. Role accent and soft-fill custom properties supply focus, selected details, and normal confirmation colors; destructive confirmation uses its separate danger treatment. Fields have clear focus, user-invalid, disabled, and file-picker states. Labels and panel borders respond to focus within. Existing primary and operational controls have a minimum height of (40px), and pressed buttons move by (1px).

The dialog is centered, scrollable, and bounded to the viewport: maximum width (28rem), viewport gutters (16px), and rounded corners (16px). Small screens at (480px) reduce padding and allow action buttons to share or wrap across the available width. Its shadow and backdrop distinguish a pending decision from the surrounding workspace.

Motion communicates state: control color transitions use (150ms), pressed feedback uses (100ms), dialog arrival uses (180ms), the backdrop uses (150ms), success feedback uses (500ms), and the busy indicator rotates over (700ms). Reduced-motion rules remove dialog, backdrop, busy, and success animations and the pressed translation. Preserve reduced-motion handling when extending these effects.

## Validation and limits

The implementation handoff reports successful production builds, CSS parsing with zero warnings, Blade compilation, JavaScript syntax checks, and whitespace diff checks. The existing optional Fontaine warning remains. No automated tests were run. An authenticated browser visual review was unavailable, so responsive presentation, keyboard interactions, and actual screen rendering still require browser review.

These notes describe source behavior and the supplied checks. They do not claim a visually verified result or replace server authorization, order transition services, or validation.
