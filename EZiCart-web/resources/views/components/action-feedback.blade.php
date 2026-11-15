@php
    $actionMessages = [];
    foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'status' => 'info'] as $key => $tone) {
        $message = session($key);
        if (is_string($message) && trim($message) !== '') $actionMessages[] = ['message' => $message, 'tone' => $tone];
    }
    if ($errors->any()) $actionMessages[] = ['message' => 'Some details need attention. '.$errors->first(), 'tone' => 'error'];
@endphp
<link rel="stylesheet" href="{{ asset('css/action-feedback.css') }}?v={{ filemtime(public_path('css/action-feedback.css')) }}">
<script src="{{ asset('js/action-feedback.js') }}?v={{ filemtime(public_path('js/action-feedback.js')) }}" defer></script>
<script src="{{ asset('js/short-task-dialogs.js') }}?v={{ filemtime(public_path('js/short-task-dialogs.js')) }}" defer></script>
<script type="application/json" data-action-feedback-seed>@json($actionMessages)</script>
<div class="action-feedback-region" data-action-feedback-region aria-label="Action notifications"></div>
