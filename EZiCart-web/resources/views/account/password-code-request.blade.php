<form action="{{ route('account.password.code') }}" method="POST" class="web-section web-form">
    @csrf
    <p>First verify access to your registered email. Request a code, then enter it with your new password below. Codes expire in {{ config('account-security.password_otp_minutes') }} minutes and allow {{ config('account-security.password_otp_attempts') }} attempts.</p>
    <label for="password-code-current">Current password to request a code</label>
    <input id="password-code-current" type="password" name="current_password" class="form-control" required autocomplete="current-password">
    <button type="submit" class="dash-btn-sm dash-btn-outline">Send / resend verification code</button>
    <small>Wait {{ config('account-security.password_otp_cooldown_seconds') }} seconds before resending. A new code replaces the previous one.</small>
</form>
