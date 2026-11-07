<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Opening EZiCart</title>
    <script src="{{ request()->getBasePath() }}/js/tab-sessions.js?v={{ filemtime(public_path('js/tab-sessions.js')) }}" data-tab-bootstrap="1" data-tab-target="{{ $targetUrl }}" data-tab-login-url="{{ route('login') }}" defer></script>
</head>
<body>
    <p role="status">Opening your tab’s account…</p>
    <noscript>Enable JavaScript to use separate accounts in different tabs.</noscript>
</body>
</html>
