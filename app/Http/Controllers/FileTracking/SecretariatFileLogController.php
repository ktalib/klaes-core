<?php

namespace App\Http\Controllers\FileTracking;

use App\Http\Controllers\Controller;
use App\Services\FileTracking\FileProfileService;
use App\Services\FileTracking\FileQrResolver;
use App\Services\FileTracking\SecretariatFileLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * File Movement (Department) — standalone in/out file register for the
 * HC, PS and Directors' offices. Thin: the work is in SecretariatFileLogService
 * (writes) and FileQrResolver (scan lookup, read-only).
 */
class SecretariatFileLogController extends Controller
{
    public const MODULE = 'File Movement (Department)';

    public function __construct(private SecretariatFileLogService $log, private FileQrResolver $resolver)
    {
        // The route middleware only enforces delete for now, so gate view here.
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            abort_unless($user && ($user->isSuperAdmin() || $user->canDo(self::MODULE, 'view')), 403, 'You do not have access to File Movement (Department).');

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $user = $request->user();

        return view('secretariat_file_log.index', [
            'offices'         => $this->log->offices(),
            // Same lists the main Log a File page offers when sending a file on.
            'requestPurposes' => \App\Models\RequestPurpose::active()->orderBy('name')->get(['id', 'name']),
            'officers'        => $this->log->receivingOfficers(),
            // Only a super admin may choose an office; everyone else is fixed to the
            // office their Rank/Department maps to (config/file_movement.php).
            'canChooseOffice' => $user->isSuperAdmin(),
            'canDeleteLogs'   => $user->isSuperAdmin(),
            'myOffice'        => $this->log->officeFor($user),
            'PageTitle'       => 'File Movement (Department)',
            'PageDescription' => 'Receive and send files between offices — scan any KLAES QR or log manually.',
        ]);
    }

    public function resolve(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|max:4000']);

        $result = $this->resolver->resolve($request->input('q'));

        // Attach the live tracker state so the card can say where the file is now.
        foreach ($result['matches'] as &$match) {
            $match['tracker'] = null;
            if (!$match['file_number']) {
                continue;
            }
            $tracker = \App\Models\FileTracker::whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper($match['file_number'])])
                ->whereRaw("UPPER(ISNULL(status,'')) NOT IN ('CANCELLED')")
                ->orderByDesc('id')
                ->first(['id', 'tracking_id', 'current_office_code', 'current_office_name', 'movement_log', 'file_title']);
            if ($tracker) {
                $log = $tracker->movement_log ?: [];
                $last = $log ? end($log) : null;
                $match['tracker'] = [
                    'id'          => $tracker->id,
                    'tracking_id' => $tracker->tracking_id,
                    'office_code' => $tracker->current_office_code,
                    'office_name' => $tracker->current_office_name,
                    'status'      => $last['status'] ?? null,
                ];
                $match['file_title'] = $match['file_title'] ?: $tracker->file_title;
            }
        }
        unset($match);

        return response()->json(['success' => true] + $result);
    }

    public function profile(Request $request, FileProfileService $profiles): JsonResponse
    {
        $request->validate([
            'file_number' => 'nullable|string|max:255',
            'tracker_id'  => 'nullable|integer',
        ]);

        if (!$request->filled('file_number') && !$request->filled('tracker_id')) {
            return response()->json(['success' => false, 'message' => 'A file number or tracker is required.'], 422);
        }

        return response()->json(['success' => true] + $profiles->build($request->input('file_number'), $request->integer('tracker_id') ?: null));
    }

    /** Admin only: delete one log entry from a tracker (audited). */
    public function deleteLog(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only an administrator can delete logs.');

        $data = $request->validate([
            'tracker_id' => 'required|integer',
            'index'      => 'required|integer|min:0',
            'key'        => 'required|string|size:32',
            'reason'     => 'required|string|max:500',
        ]);

        try {
            $tracker = $this->log->deleteLogEntry((int) $data['tracker_id'], (int) $data['index'], $data['key'], $data['reason'], $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Log deleted. The file is now at ' . ($tracker->current_office_name ?: 'its previous office') . '.',
        ]);
    }

    /** Admin only: delete a whole tracker and its logs (audited). */
    public function deleteTracker(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only an administrator can delete trackers.');

        $data = $request->validate([
            'tracker_id' => 'required|integer',
            'reason'     => 'required|string|max:500',
        ]);

        try {
            $deleted = $this->log->deleteTracker((int) $data['tracker_id'], $data['reason'], $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tracker for ' . ($deleted['file_number'] ?: ($deleted['file_title'] ?: 'the document')) . ' deleted.',
        ]);
    }

    /** Indexed files for the manual log's file-number dropdown. */
    public function indexed(Request $request): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:100']);

        return response()->json(['success' => true, 'files' => $this->log->searchIndexed((string) $request->input('q', ''))]);
    }

    /**
     * The office this request acts for. A super admin's choice is honoured; for
     * anyone else the office sent by the browser is ignored and their own is used,
     * so a user can never receive or send files on another office's behalf.
     */
    private function actingOffice(Request $request): string
    {
        $user = $request->user();

        if ($user->isSuperAdmin() && $request->filled('office')) {
            return (string) $request->input('office');
        }

        $office = $this->log->officeFor($user);
        abort_unless($office, 403, 'No office is assigned to your account. Ask the administrator to set your Department and Rank.');

        return $office['code'];
    }

    public function lists(Request $request): JsonResponse
    {
        $request->validate([
            'office' => 'nullable|string|max:50',
            'days'   => 'nullable|integer|min:1|max:365',
        ]);

        $office = $this->actingOffice($request);

        try {
            return response()->json(['success' => true] + $this->log->lists($office, (int) $request->input('days', 7)));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function receive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'office'       => 'nullable|string|max:50',
            'entry_type'   => 'required|in:file,unindexed,non_file',
            'tracker_id'   => 'nullable|integer',
            'file_number'  => 'nullable|string|max:255',
            'related_file_number' => 'nullable|string|max:255',
            // A manual log names who took the file and why, as a send does.
            // A manual log names who took the file and why, as a send does; "Other"
            // sends the typed text instead of an id (checked in the service).
            'receiving_officer_id'    => 'nullable|integer',
            'receiving_officer_other' => 'nullable|string|max:255',
            'request_purpose_id'      => 'nullable|integer|exists:sqlsrv.request_purposes,id',
            'request_purpose_other'   => 'nullable|string|max:255',
            'from_office_other'       => 'nullable|string|max:255',
            'file_title'   => 'nullable|string|max:255',
            'from_office'  => 'nullable|string|max:50',
            'sender'       => 'nullable|string|max:255',
            'reference'    => 'nullable|string|max:255',
            'notes'        => 'nullable|string|max:1000',
            'received_via' => 'nullable|in:scan,manual',
        ]);

        $office = $this->actingOffice($request);

        try {
            $result = $this->log->receive($office, $data, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Secretariat file log receive failed', ['error' => $e->getMessage(), 'data' => $data]);
            return response()->json(['success' => false, 'message' => 'Could not log the file: ' . $e->getMessage()], 500);
        }

        $messages = [
            'created'      => 'File logged in and received.',
            'accepted'     => 'File received.',
            'logged_in'    => 'Movement completed and file received at this office.',
            'already_here' => 'This file is already held at this office.',
        ];

        return response()->json([
            'success' => true,
            'action'  => $result['action'],
            'message' => $messages[$result['action']] ?? 'Done.',
            'tracker' => $result['tracker']->only(['id', 'tracking_id', 'file_number', 'file_title', 'current_office_name']),
        ]);
    }

    public function forward(Request $request): JsonResponse
    {
        $data = $request->validate([
            'office'     => 'nullable|string|max:50',
            'tracker_id' => 'required|integer',
            'to_office'            => 'required|string|max:50',
            'request_purpose_id'      => 'nullable|required_without:request_purpose_other|integer|exists:sqlsrv.request_purposes,id',
            'request_purpose_other'   => 'nullable|string|max:255',
            'receiving_officer_id'    => 'nullable|required_without:receiving_officer_other|integer',
            'receiving_officer_other' => 'nullable|string|max:255',
            'notes'                => 'nullable|string|max:1000',
        ]);

        $office = $this->actingOffice($request);

        try {
            $tracker = $this->log->forward($office, (int) $data['tracker_id'], $data['to_office'],
                isset($data['request_purpose_id']) ? (int) $data['request_purpose_id'] : null,
                isset($data['receiving_officer_id']) ? (int) $data['receiving_officer_id'] : null,
                $data['notes'] ?? null, $request->user(),
                $data['request_purpose_other'] ?? null, $data['receiving_officer_other'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Secretariat file log forward failed', ['error' => $e->getMessage(), 'data' => $data]);
            return response()->json(['success' => false, 'message' => 'Could not send the file: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'File sent to ' . $tracker->receiving_office_name
                . ($tracker->receiving_officer_name ? ' (' . $tracker->receiving_officer_name . ')' : '') . '.',
            'tracker' => $tracker->only(['id', 'tracking_id', 'file_number', 'file_title', 'receiving_office_name']),
        ]);
    }
}
