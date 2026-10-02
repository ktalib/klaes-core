<?php

namespace App\Services;

use App\Models\LandOfficer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LandOfficerSignatureService
{
    public function sendOtp(LandOfficer $officer, string $method): array
    {
        if (!in_array($method, ['email', 'sms'], true)) {
            return ['success' => false, 'message' => 'Invalid verification method.'];
        }

        $code = (string) random_int(100000, 999999);
        $officer->verification_code = $code;
        $officer->verification_code_expires_at = now()->addMinutes(10);
        $officer->notification_type = $method;
        $officer->save();

        $message = "Your digital signature approval OTP is {$code}. It expires in 10 minutes.";
        $recipientEmail = null;

        if ($officer->user_id) {
            $recipientEmail = User::where('id', $officer->user_id)->value('email');
        }

        if ($method === 'email') {
            if (!$recipientEmail) {
                return ['success' => false, 'message' => 'Signing officer email is not configured.'];
            }

            Mail::raw($message, function ($mail) use ($recipientEmail) {
                $mail->to($recipientEmail)->subject('Digital Signature Approval OTP');
            });
        }

        if ($method === 'sms') {
            // Hook your SMS gateway here. For now, we log the OTP dispatch.
            Log::info('Digital Signature SMS OTP generated', [
                'signing_officer_id' => $officer->id,
                'code' => $code,
            ]);
        }

        return ['success' => true, 'message' => strtoupper($method) . ' OTP sent successfully.'];
    }

    public function verifySecondLevelAuth(LandOfficer $officer, string $method, ?string $otpCode, ?string $password): array
    {
        if (!in_array($method, ['email', 'sms', 'password'], true)) {
            return ['success' => false, 'message' => 'Invalid verification method selected.'];
        }

        if ($method === 'password') {
            $authUser = Auth::user();

            // Prefer currently authenticated user password confirmation.
            if ($authUser) {
                if (empty($password) || !Hash::check($password, $authUser->password)) {
                    return ['success' => false, 'message' => 'Password confirmation failed.'];
                }

                return ['success' => true, 'message' => 'Password confirmed successfully.'];
            }

            if (empty($officer->user_id)) {
                return ['success' => false, 'message' => 'No system user is linked to this signing officer.'];
            }

            $officerUser = User::find($officer->user_id);
            if (!$officerUser) {
                return ['success' => false, 'message' => 'Linked signing officer user account was not found.'];
            }

            if (empty($password) || !Hash::check($password, $officerUser->password)) {
                return ['success' => false, 'message' => 'Password confirmation failed.'];
            }

            return ['success' => true, 'message' => 'Password confirmed successfully.'];
        }

        if (empty($otpCode)) {
            return ['success' => false, 'message' => 'OTP code is required.'];
        }

        if (empty($officer->verification_code) || empty($officer->verification_code_expires_at)) {
            return ['success' => false, 'message' => 'No OTP found. Please request a new OTP.'];
        }

        if (now()->greaterThan($officer->verification_code_expires_at)) {
            return ['success' => false, 'message' => 'OTP has expired. Please request another one.'];
        }

        if ((string) $officer->verification_code !== (string) $otpCode) {
            return ['success' => false, 'message' => 'Invalid OTP code.'];
        }

        // Consume OTP after successful verification
        $officer->verification_code = null;
        $officer->verification_code_expires_at = null;
        $officer->save();

        return ['success' => true, 'message' => 'OTP verified successfully.'];
    }
}
