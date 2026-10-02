<?php

namespace App\Http\Controllers;

use App\Services\BulkSmsNgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AlaesVfcController extends Controller
{
    public function login()
    {
        return view('alaes_vfc.vfc-login');
    }

    public function dashboard()
    {
        return view('alaes_vfc.vfc');
    }

    /**
     * Notify the designated administrator after a successful VFC login.
     *
     * The VFC login is a browser-side demonstration flow, so the browser calls
     * this endpoint only after its own credential check succeeds. The recipient
     * is server-configured and the cooldown prevents a public browser from
     * consuming the SMS wallet through repeated submissions.
     */
    public function sendLoginSms(Request $request, BulkSmsNgService $sms): JsonResponse
    {
        if (! config('alaes_vfc.login_sms.enabled')) {
            return response()->json(['sent' => false, 'status' => 'disabled']);
        }

        $recipient = (string) config('alaes_vfc.login_sms.recipient');
        $cooldown  = max(1, (int) config('alaes_vfc.login_sms.cooldown_minutes', 5));
        $cacheKey  = 'alaes-vfc-login-sms:'.sha1($recipient);

        if (! Cache::add($cacheKey, true, now()->addMinutes($cooldown))) {
            return response()->json(['sent' => false, 'status' => 'cooldown']);
        }

        $time = now((string) config('alaes_vfc.login_sms.timezone', 'Africa/Lagos'))
            ->format('d M Y, H:i');

        try {
            $sent = $sms->send(
                $recipient,
                "ALAES VFC login successful on {$time} WAT.",
                (string) config('alaes_vfc.login_sms.sender', 'ALAES')
            );
        } catch (\Throwable $exception) {
            Cache::forget($cacheKey);
            Log::error('ALAES VFC login SMS failed unexpectedly.', ['exception' => $exception->getMessage()]);

            return response()->json(['sent' => false, 'status' => 'failed'], 502);
        }

        if (! $sent) {
            Cache::forget($cacheKey);
            Log::warning('ALAES VFC login SMS was not accepted by the gateway.', [
                'code'   => $sms->lastStatusCode(),
                'reason' => $sms->lastFailureReason(),
            ]);

            return response()->json(['sent' => false, 'status' => 'failed'], 502);
        }

        return response()->json(['sent' => true, 'status' => 'accepted']);
    }
}
