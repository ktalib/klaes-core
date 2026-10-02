<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\FileIndexing;
use App\Models\FileTracker;
use App\Services\EBulkSmsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MobileTrackerController extends Controller
{
    /**
     * POST /api/mobile/tracker/scan-and-log
     *
     * Resolves a QR code (tracking_id or file_number) then immediately
     * adds a movement entry for the scanning user's office.
     *
     * Body:
     *   qr_code      string  required  — the value encoded in the QR code
     *   office_code  string  required  — destination office code
     *   office_name  string  required  — destination office name
     *   notes        string  optional
     *   log_in_time  string  optional  — defaults to now (HH:mm)
     *   log_in_date  string  optional  — defaults to today
     *
     * Response includes sms_sent + sms_message so the mobile app can show
     * a Swal alert when the API key is missing.
     */
    public function scanAndLog(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'qr_code'     => 'required|string',
            // office_code / office_name are optional for New KANGIS trackers — auto-resolved from config
            'office_code' => 'nullable|string|max:150',
            'office_name' => 'nullable|string|max:255',
            'notes'       => 'nullable|string|max:500',
            'log_in_time' => 'nullable|string',
            'log_in_date' => 'nullable|date',
            // When true, the server defers SMS delivery so the caller can show
            // the applicant a Swal confirmation with an editable message.
            'skip_sms'    => 'nullable|boolean',
            // Optional custom SMS body (takes precedence over the step template).
            'sms_message' => 'nullable|string|max:480',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $identifier = trim($request->input('qr_code'));

        $tracker = FileTracker::where('tracking_id', $identifier)
            ->orWhere('file_number', $identifier)
            ->first();

        // If no tracker exists yet, try to auto-create from fileNumber / file_indexings
        if (!$tracker) {
            $needle = mb_strtoupper($identifier);
            $fileRecord = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where(function ($q) use ($needle) {
                    $q->whereRaw('UPPER(tracking_id) = ?', [$needle])
                      ->orWhereRaw('UPPER(NewKANGISFileNo) = ?', [$needle])
                      ->orWhereRaw('UPPER(kangisFileNo) = ?', [$needle])
                      ->orWhereRaw('UPPER(mlsfNo) = ?', [$needle])
                      ->orWhereRaw('UPPER(FileName) = ?', [$needle]);
                })
                ->select(['id', 'tracking_id', 'NewKANGISFileNo', 'kangisFileNo', 'mlsfNo', 'FileName', 'type', 'application_id'])
                ->first();

            if (!$fileRecord) {
                return response()->json([
                    'success' => false,
                    'message' => "No file tracker found for: {$identifier}",
                ], 404);
            }

            $fileNumber = $fileRecord->NewKANGISFileNo
                ?? $fileRecord->kangisFileNo
                ?? $fileRecord->mlsfNo
                ?? $identifier;

            $knConfig   = config('NewKangisFTUnitsWorkFlow', []);
            $step1      = $knConfig[1] ?? ['to_code' => 'CS', 'to_name' => 'Commission Section'];

            $tracker = new FileTracker();
            $tracker->tracking_id          = $fileRecord->tracking_id ?? $identifier;
            $tracker->file_number          = $fileNumber;
            $tracker->file_title           = $fileRecord->FileName ?? $fileNumber;
            $tracker->module               = 'new_kangis';
            $tracker->workflow_type        = FileTracker::WORKFLOW_KANGIS_NEW;
            $tracker->workflow_step        = 1;
            $tracker->status               = FileTracker::STATUS_ACTIVE;
            $tracker->priority             = FileTracker::PRIORITY_MEDIUM;
            $tracker->total_offices        = max(array_map('intval', array_keys($knConfig ?: [1 => 1])));
            $tracker->completed_offices    = 0;
            $tracker->current_office_code  = $step1['to_code'] ?? 'CS';
            $tracker->current_office_name  = $step1['to_name'] ?? 'Commission Section';
            $tracker->origin_office_code   = $step1['to_code'] ?? 'CS';
            $tracker->origin_office_name   = $step1['to_name'] ?? 'Commission Section';
            $tracker->movement_log         = [];
            $tracker->module_meta          = json_encode([
                'file_number'    => $fileNumber,
                'file_record_id' => $fileRecord->id,
                'application_id' => $fileRecord->application_id ?? null,
                'auto_created'   => true,
                'created_reason' => 'Auto-created on first QR scan',
            ]);
            $tracker->date_created = now();
            $tracker->save();

            Log::info('MobileTrackerController: auto-created FileTracker', [
                'tracking_id' => $tracker->tracking_id,
                'file_number' => $tracker->file_number,
            ]);
        }

        $actor     = $request->user();
        $actorName = trim(($actor?->first_name ?? '') . ' ' . ($actor?->last_name ?? ''))
            ?: ($actor?->name ?? $actor?->email ?? 'System');
        $now       = Carbon::now();

        $logInTime = $request->input('log_in_time', $now->format('H:i'));
        $logInDate = $request->input('log_in_date', $now->toDateString());

        // Resolve linked file indexing for SMS phone lookup
        $meta = is_array($tracker->module_meta)
            ? $tracker->module_meta
            : json_decode($tracker->module_meta ?? '{}', true);
        $linkedIndexing = null;
        if (!empty($meta['file_indexing_id'])) {
            $linkedIndexing = FileIndexing::find($meta['file_indexing_id']);
        }
        if (!$linkedIndexing) {
            $variants = array_values(array_unique([
                $tracker->file_number,
                strtoupper((string) $tracker->file_number),
                strtolower((string) $tracker->file_number),
            ]));
            $linkedIndexing = FileIndexing::on('sqlsrv')
                ->where(function ($q) use ($variants) {
                    $q->whereIn('file_number', $variants)
                      ->orWhereIn('new_kangis_file_no', $variants)
                      ->orWhereIn('kangis_file_no', $variants)
                      ->orWhereIn('mls_file_no', $variants);
                })
                ->orderByDesc('id')
                ->first();
        }

        $isKangisNewTracker = $tracker->isKangisNewFile()
            || ($tracker->module === 'new_kangis')
            || preg_match('/^KN\d+$/i', trim((string) $tracker->file_number));

        // For New KANGIS trackers: resolve next office from configured workflow map
        $knConfig    = config('NewKangisFTUnitsWorkFlow', []);
        $maxKnStep   = max(array_map('intval', array_keys($knConfig ?: [1 => 1])));
        $officeCode  = $request->input('office_code');
        $officeName  = $request->input('office_name');
        $smsTemplate = null;

        if ($isKangisNewTracker) {
            if (!$tracker->workflow_type) {
                $tracker->workflow_type = FileTracker::WORKFLOW_KANGIS_NEW;
            }

            $currentStep = (int) ($tracker->workflow_step ?? 1);
            $nextStep    = min($currentStep + 1, $maxKnStep);
            $tracker->workflow_step = $nextStep;

            // Auto-resolve destination from config when caller doesn't supply one
            if (!$officeCode && isset($knConfig[$nextStep])) {
                $stepDef    = $knConfig[$nextStep];
                $officeCode = $stepDef['to_code'] ?? $officeCode;
                $officeName = $stepDef['to_name'] ?? $officeName;
            }

            // Pick SMS template from config for this step
            if (isset($knConfig[$nextStep]['SMS'])) {
                $smsTemplate = str_replace(
                    '<file_number>',
                    $tracker->file_number,
                    $knConfig[$nextStep]['SMS']
                );
            }
        }

        // Cross-Registry Request workflow: auto-resolve next office from the bilateral chain
        // KANGIS → Lands : Land Registry (rec) → Director of Lands (approval) → KANGIS (final)
        // Lands  → KANGIS: KANGIS Registry (rec) → DG KANGIS (approval) → Land Registry (final)
        if (!$isKangisNewTracker && $tracker->workflow_type === FileTracker::WORKFLOW_CROSS_MODULE_REQUEST) {
            $cfg = is_array($tracker->workflow_config)
                ? $tracker->workflow_config
                : (json_decode($tracker->workflow_config ?? '{}', true) ?: []);
            $senderModule = strtolower((string) ($cfg['sender_module'] ?? ''));

            $crossChain = $senderModule === 'kangis'
                ? [
                    1 => ['to_code' => 'LANDS_REG', 'to_name' => 'Land Registry'],
                    2 => ['to_code' => 'DIR_LANDS', 'to_name' => 'Director of Lands'],
                    3 => ['to_code' => 'KANGIS',    'to_name' => 'KANGIS Registry'],
                ]
                : [
                    1 => ['to_code' => 'KANGIS',    'to_name' => 'KANGIS Registry'],
                    2 => ['to_code' => 'DG_KANGIS', 'to_name' => 'DG KANGIS'],
                    3 => ['to_code' => 'LANDS_REG', 'to_name' => 'Land Registry'],
                ];
            $maxCrossStep = max(array_keys($crossChain));

            $currentStep = (int) ($tracker->workflow_step ?? 1);
            $nextStep    = min($currentStep + 1, $maxCrossStep);
            $tracker->workflow_step = $nextStep;

            if (!$officeCode && isset($crossChain[$nextStep])) {
                $officeCode = $crossChain[$nextStep]['to_code'];
                $officeName = $crossChain[$nextStep]['to_name'];
            }

            // Once the chain reaches the final step, mark the cross-approval as complete
            if ($nextStep >= $maxCrossStep) {
                $tracker->status = FileTracker::STATUS_ACTIVE;
            }
        }

        // Fallback validation — we must have an office by now
        if (!$officeCode || !$officeName) {
            return response()->json([
                'success' => false,
                'message' => 'office_code and office_name are required for non-New-KANGIS trackers.',
            ], 422);
        }

        // Stamp log_out on the previous movement entry before adding the new one
        $existingLog = $tracker->movement_log ?: [];
        if (!empty($existingLog)) {
            $lastIndex = count($existingLog) - 1;
            $existingLog[$lastIndex]['log_out_time']  = $logInTime;
            $existingLog[$lastIndex]['log_out_date']  = $logInDate;
            $existingLog[$lastIndex]['status']        = 'log_out';
            $tracker->movement_log = $existingLog;
            // Don't save yet — addMovementLog will save the full array
        }

        $tracker->addMovementLog(
            $officeCode,
            $officeName,
            $logInTime,
            $logInDate,
            $request->input('notes', ''),
            $actor?->id,
            $actorName,
            [
                'receiving_officer_id'   => $actor?->id,
                'receiving_officer_name' => $actorName,
                'receiving_office_code'  => $officeCode,
                'receiving_office_name'  => $officeName,
                'status'                 => 'active',
                'requires_acceptance'    => false,
                'auto_accept'            => true,
            ]
        );

        $tracker->current_office_code    = $officeCode;
        $tracker->current_office_name    = $officeName;
        $tracker->receiving_office_code  = $officeCode;
        $tracker->receiving_office_name  = $officeName;
        $tracker->receiving_officer_id   = $actor?->id;
        $tracker->receiving_officer_name = $actorName;
        $tracker->assignment_status      = FileTracker::ASSIGNMENT_ACCEPTED;
        $tracker->assignment_accepted_at = now();

        // Keep completed_offices in sync with workflow_step; guard against null
        if (isset($tracker->workflow_step)) {
            $tracker->completed_offices = max((int) ($tracker->workflow_step ?? 0) - 1, 0);
            if ($isKangisNewTracker && (!$tracker->total_offices || $tracker->total_offices < $maxKnStep)) {
                $tracker->total_offices = $maxKnStep;
            }
        }

        $tracker->save();

        // SMS attempt
        $smsSent    = false;
        $smsMessage = null;
        $phone = $linkedIndexing?->phone;

        $skipSms = $request->boolean('skip_sms');
        $customSms = trim((string) $request->input('sms_message', ''));
        $defaultSms = $smsTemplate
            ?? "Dear Applicant, your file {$tracker->file_number} has been logged at {$officeName}. - KANGIS Registry.";
        $smsBody = $customSms !== '' ? $customSms : $defaultSms;

        if ($phone && !$skipSms) {
            try {
                $smsSent = app(EBulkSmsService::class)->send($phone, $smsBody);
            } catch (\Throwable $e) {
                Log::warning('MobileTrackerController: SMS failed', ['error' => $e->getMessage()]);
            }
            $smsMessage = $smsSent
                ? 'SMS sent successfully.'
                : 'Successful, but SMS did not send — no valid API key.';
        } elseif ($phone && $skipSms) {
            $smsMessage = 'SMS deferred — confirm the message before sending.';
        }

        return response()->json([
            'success'              => true,
            'message'              => 'File logged successfully.',
            'next_office'          => $officeName,
            'workflow_step'        => $tracker->workflow_step ?? null,
            'tracker'              => [
                'id'             => $tracker->id,
                'tracking_id'    => $tracker->tracking_id,
                'file_number'    => $tracker->file_number,
                'file_title'     => $tracker->file_title,
                'status'         => $tracker->status,
                'current_office' => $tracker->current_office_name,
                'priority'       => $tracker->priority,
            ],
            'sms_sent'             => $smsSent,
            'sms_message'          => $smsMessage,
            'sms_deferred'         => (bool) ($skipSms && $phone),
            'phone'                => $phone,
            'default_sms_message'  => $phone ? $smsBody : null,
        ]);
    }

    /**
     * POST /api/mobile/tracker/{id}/send-sms
     *
     * Sends an ad-hoc SMS message to the applicant linked to the given tracker.
     * Used by the scan-and-log UI so officers can review/edit the body before
     * it goes out.
     */
    public function sendSms(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:480',
            'phone'   => 'nullable|string|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $tracker = FileTracker::find($id);
        if (!$tracker) {
            return response()->json([
                'success' => false,
                'message' => 'Tracker not found.',
            ], 404);
        }

        $phone = trim((string) $request->input('phone', ''));
        if ($phone === '') {
            $meta = is_array($tracker->module_meta)
                ? $tracker->module_meta
                : json_decode($tracker->module_meta ?? '{}', true);
            $linkedIndexing = null;
            if (!empty($meta['file_indexing_id'])) {
                $linkedIndexing = FileIndexing::find($meta['file_indexing_id']);
            }
            if (!$linkedIndexing) {
                $variants = array_values(array_unique([
                    $tracker->file_number,
                    strtoupper((string) $tracker->file_number),
                ]));
                $linkedIndexing = FileIndexing::on('sqlsrv')
                    ->where(function ($q) use ($variants) {
                        $q->whereIn('file_number', $variants)
                          ->orWhereIn('new_kangis_file_no', $variants)
                          ->orWhereIn('kangis_file_no', $variants)
                          ->orWhereIn('mls_file_no', $variants);
                    })
                    ->orderByDesc('id')
                    ->first();
            }
            $phone = (string) ($linkedIndexing?->phone ?? '');
        }

        if ($phone === '') {
            return response()->json([
                'success' => false,
                'message' => 'No phone number on file for this applicant.',
            ], 422);
        }

        $sent = false;
        try {
            $sent = app(EBulkSmsService::class)->send($phone, $request->input('message'));
        } catch (\Throwable $e) {
            Log::warning('MobileTrackerController::sendSms failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => $sent,
            'message' => $sent
                ? 'SMS sent successfully.'
                : 'Failed to send SMS — check the API key configuration.',
            'phone'   => $phone,
        ]);
    }

    /**
     * GET /api/mobile/tracker/{id}/sms-preview
     *
     * Returns the applicant phone and default SMS body for the current
     * step so the UI can show a confirmation Swal after creating or
     * updating a tracker.
     */
    public function smsPreview(int $id): JsonResponse
    {
        $tracker = FileTracker::find($id);
        if (!$tracker) {
            return response()->json([
                'success' => false,
                'message' => 'Tracker not found.',
            ], 404);
        }

        // Resolve linked file indexing for phone lookup.
        $meta = is_array($tracker->module_meta)
            ? $tracker->module_meta
            : json_decode($tracker->module_meta ?? '{}', true);
        $linkedIndexing = null;
        if (!empty($meta['file_indexing_id'])) {
            $linkedIndexing = FileIndexing::find($meta['file_indexing_id']);
        }
        if (!$linkedIndexing) {
            $variants = array_values(array_unique(array_filter([
                $tracker->file_number,
                $tracker->file_number ? strtoupper((string) $tracker->file_number) : null,
            ])));
            if (!empty($variants)) {
                $linkedIndexing = FileIndexing::on('sqlsrv')
                    ->where(function ($q) use ($variants) {
                        $q->whereIn('file_number', $variants)
                          ->orWhereIn('new_kangis_file_no', $variants)
                          ->orWhereIn('kangis_file_no', $variants)
                          ->orWhereIn('mls_file_no', $variants);
                    })
                    ->orderByDesc('id')
                    ->first();
            }
        }

        $phone = (string) ($linkedIndexing?->phone ?? '');

        // Build default SMS body from the step template when available.
        $step       = (int) ($tracker->workflow_step ?? 1);
        $knConfig   = config('NewKangisFTUnitsWorkFlow', []);
        $stepCfg    = $knConfig[$step] ?? null;
        $officeName = $tracker->current_office_name
            ?? ($stepCfg['to_name'] ?? 'KANGIS Registry');

        $smsBody = $stepCfg['SMS'] ?? null;
        if ($smsBody) {
            $smsBody = str_replace('<file_number>', (string) $tracker->file_number, $smsBody);
        } else {
            $smsBody = "Dear Applicant, your file {$tracker->file_number} is now with {$officeName}. - KANGIS Registry.";
        }

        return response()->json([
            'success'             => true,
            'phone'               => $phone,
            'default_sms_message' => $smsBody,
            'next_office'         => $officeName,
            'workflow_step'       => $step,
        ]);
    }
}
