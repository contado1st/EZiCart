<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>EZiCart · Private applicant drafts</title>
@vite(['resources/css/shared/app.css', 'resources/css/shared/dashboard.css'])
<script src="{{ asset('js/tab-sessions.js') }}?v={{ filemtime(public_path('js/tab-sessions.js')) }}" data-tab-login-url="{{ route('login') }}" data-tab-authenticated="0" defer></script>
@stack('styles')</head><body>
<header class="dash-header"><div><strong>EZiCart · Seller application under review</strong><p>{{ $applicant->business_name ?: $applicant->first_name }} · Private draft workspace</p><p>Save work before the 30-minute workspace expires. Selling, publication and operational modules require approval.</p></div><form method="POST" action="{{ route('logout') }}" data-decision="logout" data-decision-title="Sign out?" data-decision-message="Save your private draft before signing out. Unsaved changes will be lost." onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<button class="dash-btn-outline" type="submit">Sign out</button></form></header>
@yield('content')
</body></html>
