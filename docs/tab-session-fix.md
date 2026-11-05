# Browser-tab account isolation

The previous client treated a copied `__tab` URL as authoritative. Browser tab duplication also copied session storage. Both could select the same Laravel cookie, while an old global remember cookie could silently sign a new context into the previous account.

The updated client claims a tab identifier through Web Locks, with BroadcastChannel coordination as a fallback. A duplicate active identifier forks to a fresh login. Session storage preserves the account during refresh and navigation, and a bootstrap response restores it before rendering an unscoped browser request. Embedded messaging uses the parent's identifier. Tokens select HttpOnly server sessions; they do not replace login credentials.

Same-origin fetch requests carry the selected identifier and use the page's CSRF token for writes. Scoped requests ignore legacy global remember cookies. Login discards saved destinations belonging to another module, while backend role authorization remains in force.

To try it, reload existing pages, choose **Account → Sign in another account**, and sign in as a different role. Repeat as needed. Refresh each dashboard, use its messages, then log out one account and confirm the other dashboards remain signed in. Duplicating a dashboard tab should show a new login rather than share its active account.

Automated checks cover six simultaneous module logins, role boundaries, independent logout, legacy remember cookies, compatible login redirects, duplicated storage, refresh, copied URLs, embedded messages, link opening and fetch/CSRF propagation. Live browser interaction has not been verified for this revision.

Validation: 37 backend tests passed (289 assertions), including registration and messaging regressions; 11 frontend tests passed, including the BroadcastChannel fallback. PHP formatting, JavaScript syntax and Blade compilation checks also completed successfully. No schema migration or dependency installation is required.
