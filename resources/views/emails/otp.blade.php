<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your EZiCart Verification Code</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #fff7f8;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #211216;
            -webkit-text-size-adjust: 100%;
        }
        table {
            border-spacing: 0;
            border-collapse: collapse;
        }
        .wrapper {
            width: 100%;
            background-color: #fff7f8;
            padding: 40px 16px;
        }
        .container {
            max-width: 560px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #efd9de;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(92, 24, 43, 0.06);
        }
        .header {
            background: linear-gradient(135deg, #ef6f79 0%, #f08091 58%, #8e1e3b 100%);
            padding: 32px 36px;
            text-align: left;
        }
        .brand {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.05em;
            color: #ffffff;
            text-decoration: none;
            display: inline-block;
        }
        .brand span {
            color: #ffd6df;
        }
        .tagline {
            color: #fff0f3;
            font-size: 13px;
            margin-top: 4px;
            font-weight: 500;
            letter-spacing: 0.02em;
        }
        .content {
            padding: 36px;
        }
        h1 {
            font-size: 22px;
            font-weight: 700;
            color: #211216;
            margin: 0 0 16px 0;
            letter-spacing: -0.02em;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            color: #4a3b40;
            margin: 0 0 20px 0;
        }
        .otp-box {
            background-color: #fff0f2;
            border: 2px dashed #e8aab5;
            border-radius: 10px;
            padding: 24px;
            text-align: center;
            margin: 28px 0;
        }
        .otp-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #8e1e3b;
            margin-bottom: 8px;
        }
        .otp-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 38px;
            font-weight: 800;
            letter-spacing: 0.3em;
            color: #8e1e3b;
            padding-left: 0.3em;
            margin: 4px 0;
        }
        .otp-expiry {
            font-size: 13px;
            color: #725f64;
            margin-top: 8px;
        }
        .notice-box {
            background-color: #fbfafc;
            border-left: 4px solid #ef6f79;
            padding: 14px 16px;
            border-radius: 4px;
            margin-bottom: 24px;
        }
        .notice-box p {
            font-size: 13px;
            color: #5c474d;
            margin: 0;
            line-height: 1.5;
        }
        .footer {
            border-top: 1px solid #f1dce0;
            padding: 24px 36px;
            background-color: #faf5f6;
            text-align: center;
        }
        .footer p {
            font-size: 12px;
            color: #8a777c;
            margin: 0 0 6px 0;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <table class="container" role="presentation" width="100%">
            <tr>
                <td class="header">
                    <div class="brand">EZiCart<span>.</span></div>
                    <div class="tagline">The trusted Philippine marketplace</div>
                </td>
            </tr>
            <tr>
                <td class="content">
                    <h1>Verify Your Email Address</h1>
                    <p>Hello <strong>{{ $userName }}</strong>,</p>
                    <p>Thank you for creating an account with <strong>EZiCart</strong>. To complete your registration and activate your account, please enter the one-time verification code below:</p>

                    <div class="otp-box">
                        <div class="otp-label">One-Time Password (OTP)</div>
                        <div class="otp-code">{{ $code }}</div>
                        <div class="otp-expiry">Valid for {{ $expiresInMinutes }} minutes</div>
                    </div>

                    <div class="notice-box">
                        <p><strong>Security Reminder:</strong> Never share this 6-digit code with anyone. EZiCart support representatives and administrators will never ask for your verification code or password.</p>
                    </div>

                    <p>If you did not initiate this registration on EZiCart, you can safely disregard this email. No changes will be made to your email address or account without this code.</p>
                </td>
            </tr>
            <tr>
                <td class="footer">
                    <p>&copy; {{ date('Y') }} EZiCart. All rights reserved.</p>
                    <p>This is an automated security email. Please do not reply directly to this message.</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

