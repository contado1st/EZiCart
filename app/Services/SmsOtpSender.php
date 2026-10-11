<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsOtpSender
{
    public function isConfigured(): bool
    {
        return filled(config('services.twilio.account_sid'))
            && filled(config('services.twilio.auth_token'))
            && (filled(config('services.twilio.from')) || filled(config('services.twilio.messaging_service_sid')));
    }

    /** @throws RequestException */
    public function send(string $mobile, string $code): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Twilio SMS delivery is not configured.');
        }

        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        if (! preg_match('/^09\d{9}$/', $digits)) {
            throw new RuntimeException('The registered mobile number is not a valid Philippine mobile number.');
        }
        $to = '+63'.substr($digits, 1);
        $accountSid = (string) config('services.twilio.account_sid');
        $payload = [
            'To' => $to,
            'Body' => "Your EZiCart verification code is {$code}. It expires in five minutes.",
        ];
        $messagingServiceSid = config('services.twilio.messaging_service_sid');
        if (filled($messagingServiceSid)) {
            $payload['MessagingServiceSid'] = $messagingServiceSid;
        } else {
            $payload['From'] = config('services.twilio.from');
        }

        Http::asForm()
            ->acceptJson()
            ->withBasicAuth($accountSid, (string) config('services.twilio.auth_token'))
            ->timeout(10)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $payload)
            ->throw();
    }
}
