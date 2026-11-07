<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GmailApiOtpSender
{
    public function isConfigured(): bool
    {
        return filled(config('services.gmail_api.client_id'))
            && filled(config('services.gmail_api.client_secret'))
            && filled(config('services.gmail_api.refresh_token'))
            && filled(config('services.gmail_api.sender'));
    }

    public function send(string $recipient, string $subject, string $body): void
    {
        $sender = (string) config('services.gmail_api.sender');
        if (! $this->isConfigured()) {
            throw new RuntimeException('Gmail API delivery is not configured.');
        }
        if (filter_var($sender, FILTER_VALIDATE_EMAIL) === false
            || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Gmail API sender and recipient must be valid email addresses.');
        }

        $accessToken = $this->accessToken();
        $fromName = str_replace(["\r", "\n"], '', (string) config('services.gmail_api.from_name'));
        $encodedSubject = '=?UTF-8?B?'.base64_encode($subject).'?=';
        $mimeMessage = implode("\r\n", [
            "From: {$fromName} <{$sender}>",
            "To: {$recipient}",
            "Subject: {$encodedSubject}",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ])."\r\n\r\n".$body;

        $rawMessage = rtrim(strtr(base64_encode($mimeMessage), '+/', '-_'), '=');

        $this->apiRequest($accessToken)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $rawMessage,
            ])
            ->throw();
    }

    private function accessToken(): string
    {
        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.gmail_api.client_id'),
                'client_secret' => config('services.gmail_api.client_secret'),
                'refresh_token' => config('services.gmail_api.refresh_token'),
                'grant_type' => 'refresh_token',
            ])
            ->throw()
            ->json();

        $accessToken = is_array($response) ? ($response['access_token'] ?? null) : null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google OAuth did not return an access token.');
        }

        return $accessToken;
    }

    private function apiRequest(string $accessToken): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($accessToken)
            ->connectTimeout(3)
            ->timeout(10);
    }
}
