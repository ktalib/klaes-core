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

    public function index()
    {
        return view('secretariat_file_log.index', [
            'offices' => $this->log->offices(),
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
                ->orderByDesc('updated_at')
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

    public function lists(Request $request): JsonResponse
    {
        $request->validate([
            'office' => 'required|string|max:50',
            'days'   => 'nullable|integer|min:1|max:365',
        ]);

        try {
            return response()->json(['success' => true] + $this->log->lists($request->input('office'), (int) $request->input('days', 7)));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function receive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'office'       => 'required|string|max:50',
            'entry_type'   => 'required|in:file,unindexed,non_file',
            'tracker_id'   => 'nullable|integer',
            'file_number'  => 'nullable|string|max:255',
            'file_title'   => 'nullable|string|max:255',
            'from_office'  => 'nullable|string|max:50',
            'sender'       => 'nullable|string|max:255',
            'reference'    => 'nullable|string|max:255',
            'notes'        => 'nullable|string|max:1000',
            'received_via' => 'nullable|in:scan,manual',
        ]);

        try {
            $result = $this->log->receive($data['office'], $data, $request->user());
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
            'office'     => 'required|string|max:50',
            'tracker_id' => 'required|integer',
            'to_office'  => 'required|string|max:50',
            'purpose'    => 'nullable|string|max:255',
            'notes'      => 'nullable|string|max:1000',
        ]);

        try {
            $tracker = $this->log->forward($data['office'], (int) $data['tracker_id'], $data['to_office'], $data['purpose'] ?? null, $data['notes'] ?? null, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Secretariat file log forward failed', ['error' => $e->getMessage(), 'data' => $data]);
            return response()->json(['success' => false, 'message' => 'Could not send the file: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'File sent to ' . $tracker->receiving_office_name . '.',
            'tracker' => $tracker->only(['id', 'tracking_id', 'file_number', 'file_title', 'receiving_office_name']),
        ]);
    }
}
