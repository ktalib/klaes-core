<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BulkSMS Nigeria API integration.
 *
 * Docs: https://www.bulksmsnigeria.com/resources/api-integration-tutorial
 * Endpoint: https://www.bulksmsnigeria.com/api/v2/sms
 *
 * Used as a fallback SMS provider when the primary provider (EBulkSMS) fails
 * or is not configured.
 */
class BulkSmsNigeriaService
{
    /**
     * Send an SMS via BulkSMS Nigeria.
     *
     * @param  string  $phone
     * @param  string  $message
     * @return bool
     */
    public function send(string $phone, string $message): bool
    {
        $apiToken = config('services.bulksmsnigeria.api_token');
        $sender   = config('services.bulksmsnigeria.sender', 'KANGIS');
        $sandbox  = (bool) config('services.bulksmsnigeria.sandbox', false);

        $endpoint = $sandbox
            ? 'https://www.bulksmsnigeria.com/api/sandbox/v2/sms'
            : 'https://www.bulksmsnigeria.com/api/v2/sms';

        if (!$apiToken) {
            Log::warning('BulkSmsNigeriaService: api_token not configured.');
            return false;
        }

        $mobile = $this->normalizePhone($phone);
        if (!$mobile) {
            Log::warning('BulkSmsNigeriaService: invalid phone number', ['phone' => $phone]);
            return false;
        }

        $payload = [
            'from' => substr($sender, 0, 11),
            'to'   => $mobile,
            'body' => substr($message, 0, 1530),
        ];

        try {
            $response = Http::acceptJson()
                ->withToken($apiToken)
                ->withoutVerifying()
                ->timeout(30)
                ->post($endpoint, $payload);
        } catch (\Throwable $e) {
            Log::error('BulkSmsNigeriaService: HTTP error', [
                'error' => $e->getMessage(),
                'phone' => $mobile,
            ]);
            return false;
        }

        $result = $response->json();

        Log::info('BulkSmsNigeriaService: API response', [
            'http_code' => $response->status(),
            'sandbox'   => $sandbox,
            'phone'     => $mobile,
            'message'   => substr($message, 0, 50),
            'response'  => $result,
        ]);

        if ($response->successful()) {
            $success = $result['success']
                ?? ($result['data']['status'] ?? $result['status'] ?? null);

            if (is_bool($success)) {
                return $success;
            }

            if ($success === null) {
                return isset($result['data']);
            }

            return strtolower((string) $success) === 'success';
        }

        return false;
    }

    /**
     * Normalise any Nigerian phone format to 234xxxxxxxxxx (no leading +).
     */
    private function normalizePhone(string $phone): ?string
    {
        $phone = trim($phone);
        $phone = preg_replace('/[^\d+]/', '', $phone);
        $phone = ltrim($phone, '+');

        if (str_starts_with($phone, '0') && strlen($phone) === 11) {
            return '234' . substr($phone, 1);
        }

        if (str_starts_with($phone, '234') && strlen($phone) === 13) {
            return $phone;
        }

        if (strlen($phone) === 10) {
            return '234' . $phone;
        }

        return null;
    }
}
