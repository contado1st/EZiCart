<?php

return [
    'password_otp_minutes' => (int) env('PASSWORD_CHANGE_OTP_MINUTES', 5),
    'password_otp_attempts' => 5,
    'password_otp_cooldown_seconds' => 60,
];
