<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class EBulkSmsService
{
    private string $endpoint = 'https://api.ebulksms.com/sendsms.json';

    /**
     * Send an SMS to a single Nigerian phone number via EBulkSMS JSON API.
     *
     * @param  string  $phone   Raw phone number (0xx, +234xx, or 234xx)
     * @param  string  $message Text to send (max 160 chars, truncated automatically)
     * @return bool             True only when API returns SUCCESS
     */
    public function send(string $phone, string $message): bool
    {
        $username = config('services.ebulksms.username');
        $apikey   = config('services.ebulksms.apikey');
        $sender   = config('services.ebulksms.sender', 'KANGIS');

        if (!$username || !$apikey) {
            Log::warning('EBulkSmsService: username or apikey not configured.');
            return false;
        }

        $mobile = $this->normalizePhone($phone);
        if (!$mobile) {
            Log::warning('EBulkSmsService: invalid phone number', ['phone' => $phone]);
            return false;
        }

        $payload = json_encode([
            'SMS' => [
                'auth'       => ['username' => $username, 'apikey' => $apikey],
                'message'    => [
                    'sender'      => substr($sender, 0, 11),
                    'messagetext' => substr($message, 0, 160),
                    'flash'       => '0',
                ],
                'recipients' => [
                    'gsm' => [
                        ['msidn' => $mobile, 'msgid' => substr((string) Str::uuid(), 0, 30)],
                    ],
                ],
                'dndsender'  => '0',
            ],
        ]);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error('EBulkSmsService: cURL error', ['error' => $curlError, 'phone' => $mobile]);
            return false;
        }

        $result = json_decode($response, true);
        $status = $result['response']['status'] ?? '';

        Log::info('EBulkSmsService: API response', [
            'status'  => $status,
            'phone'   => $mobile,
            'message' => substr($message, 0, 50),
        ]);

        return $status === 'SUCCESS';
    }

    /**
     * Normalise any Nigerian phone format to 234xxxxxxxxxx (no leading +).
     */
    private function normalizePhone(string $phone): ?string
    {
        $phone = trim($phone);
        // Remove all non-digit characters except leading +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        $phone = ltrim($phone, '+');

        if (str_starts_with($phone, '0') && strlen($phone) === 11) {
            // 0xxxxxxxxxx  →  234xxxxxxxxxx
            return '234' . substr($phone, 1);
        }

        if (str_starts_with($phone, '234') && strlen($phone) === 13) {
            return $phone;
        }

        return null;
    }
}
