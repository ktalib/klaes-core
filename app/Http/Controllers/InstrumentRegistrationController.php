<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\InstrumentRegistrationService;

class InstrumentRegistrationController extends Controller
{
    protected $instrumentService;

    public function __construct(InstrumentRegistrationService $instrumentService)
    {
        $this->instrumentService = $instrumentService;
    }

    /**
     * Weighted timeline counts for the rows currently on screen.
     *
     * This register cannot carry the counts in its page payload the way the other
     * listings do. It renders 50 rows server-side and re-renders every other page
     * in the browser out of window.serverCofoData, so a count that only covered
     * the server-rendered rows would vanish on page 2 - and counting the whole set
     * is not an option either: 9,596 rows measured at roughly four and a half
     * minutes, on a page that is already close to its memory ceiling.
     *
     * So the badge asks for what it is showing. The browser posts the identifiers
     * of the rows it just drew, and gets back one number each.
     */
    public function timelineCounts(Request $request, \App\Services\TimelineWeightingService $timelineService): \Illuminate\Http\JsonResponse
    {
        $rows = $request->input('rows', []);

        if (!is_array($rows) || empty($rows)) {
            return response()->json(['success' => true, 'counts' => []]);
        }

        // A page of rows, never the whole register: the cost is in the number of
        // identifiers asked about, and an unbounded post would hand a caller the
        // same four-minute query this endpoint exists to avoid.
        $rows = array_slice($rows, 0, 100, true);

        $targets = [];
        foreach ($rows as $key => $row) {
            $targets[(string) $key] = [
                'fileNumber' => trim((string) ($row['fileNumber'] ?? '')),
                'propId' => trim((string) ($row['propId'] ?? '')),
            ];
        }

        try {
            return response()->json([
                'success' => true,
                'counts' => $timelineService->getWeightedCountsForMany($targets),
            ]);
        } catch (\Throwable $e) {
            Log::error('Timeline counts failed', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'rows' => count($targets),
            ]);

            // The badge is decoration on a working link - it must never break the
            // register, so a failure comes back as "no counts" and the badges stay
            // as they were rendered.
            return response()->json(['success' => false, 'counts' => []]);
        }
    }

    private function generateBatchRegistrationSessionId(): string
    {
        return 'IRB-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
    }

    private function getActiveBatchSessionRegistrations(string $sessionId)
    {
        return DB::connection('sqlsrv')->table('deed_registrations')
            ->where('batch_session_id', $sessionId)
            ->whereNull('batch_printed_at')
            ->orderBy('id')
            ->get();
    }

    private function buildRdsTrackingLookup($registrations): array
    {
        $instrumentIds = collect($registrations)
            ->map(fn($row) => 'deed_reg_' . $row->id)
            ->values()
            ->all();

        if (empty($instrumentIds)) {
            return [];
        }

        return DB::connection('sqlsrv')->table('rds_tracking')
            ->whereIn('instrument_id', $instrumentIds)
            ->get()
            ->keyBy('instrument_id')
            ->all();
    }

    private function buildBatchSessionDescriptor(string $sessionId, $registrations, array $rdsTrackingLookup): array
    {
        $rows = collect($registrations)->values();
        $count = $rows->count();
        $createdAt = $rows->min('created_at');
        $createdAtLabel = $createdAt ? \Carbon\Carbon::parse($createdAt)->format('d M Y, g:i A') : 'Unknown date';

        $rdsGeneratedCount = $rows->filter(function ($row) use ($rdsTrackingLookup) {
            return isset($rdsTrackingLookup['deed_reg_' . $row->id]);
        })->count();
        $corGeneratedCount = $rows->filter(fn($row) => (bool) ($row->cor_exists ?? false))->count();
        $rdsPrintedCount = $rows->filter(function ($row) use ($rdsTrackingLookup) {
            $tracking = $rdsTrackingLookup['deed_reg_' . $row->id] ?? null;
            return $tracking && (int) ($tracking->print_count ?? 0) > 0;
        })->count();

        $allRdsGenerated = $count > 0 && $rdsGeneratedCount === $count;
        $allRdsPrinted = $count > 0 && $rdsPrintedCount === $count;
        $allCorGenerated = $count > 0 && $corGeneratedCount === $count;

        return [
            'session_id' => $sessionId,
            'record_count' => $count,
            'created_at' => $createdAt ? \Carbon\Carbon::parse($createdAt)->toIso8601String() : null,
            'created_at_label' => $createdAtLabel,
            'label' => sprintf('%s (%d instrument%s)', $sessionId, $count, $count === 1 ? '' : 's'),
            'rds_generated_count' => $rdsGeneratedCount,
            'rds_printed_count' => $rdsPrintedCount,
            'cor_generated_count' => $corGeneratedCount,
            'all_rds_generated' => $allRdsGenerated,
            'all_rds_printed' => $allRdsPrinted,
            'all_cor_generated' => $allCorGenerated,
            'can_generate_rds' => $count > 0,
            'can_download_rds_pdf' => $allRdsGenerated,
            'can_generate_cor' => $allRdsPrinted,
            'can_download_cor_pdf' => $allRdsPrinted && $allCorGenerated,
        ];
    }

    private function getBatchSessionDescriptor(string $sessionId): ?array
    {
        $registrations = $this->getActiveBatchSessionRegistrations($sessionId);
        if ($registrations->isEmpty()) {
            return null;
        }

        return $this->buildBatchSessionDescriptor($sessionId, $registrations, $this->buildRdsTrackingLookup($registrations));
    }

    private function validateBatchSessionOrFail(string $sessionId)
    {
        $registrations = $this->getActiveBatchSessionRegistrations($sessionId);
        if ($registrations->isEmpty()) {
            throw new \RuntimeException('No active batch registration session was found for the selected session ID.');
        }

        return $registrations;
    }

    private function ensureBatchSessionRdsPrinted($registrations, array $rdsTrackingLookup): void
    {
        $unprinted = collect($registrations)->filter(function ($row) use ($rdsTrackingLookup) {
            $tracking = $rdsTrackingLookup['deed_reg_' . $row->id] ?? null;
            return !$tracking || (int) ($tracking->print_count ?? 0) < 1;
        });

        if ($unprinted->isNotEmpty()) {
            throw new \RuntimeException('Open and print the full RDS batch in the official print tab first before generating or printing the batch CoR documents.');
        }
    }

    private function buildBatchRunnerDocuments($registrations, string $scope): array
    {
        $documents = [];

        foreach ($registrations as $registration) {
            $registeredId = 'deed_reg_' . $registration->id;
            $titleSuffix = $registration->fileno ?: ('Record ' . $registration->id);

            if (in_array($scope, ['rds', 'combined'], true)) {
                $documents[] = [
                    'type' => 'RDS',
                    'title' => 'RDS - ' . $titleSuffix,
                    'url' => route('rds.view', ['id' => $registeredId]) . '?autoprint=1',
                ];
            }

            if (in_array($scope, ['cor', 'combined'], true)) {
                $documents[] = [
                    'type' => 'CoR',
                    'title' => 'CoR - ' . $titleSuffix,
                    'url' => route('coroi.index') . '?id=' . urlencode($registeredId) . '&autoprint=1&is_unit=1',
                ];
            }
        }

        return $documents;
    }

    private function buildBatchPopupDocuments($registrations, string $scope, string $sessionId): array
    {
        // Return a single document URL that opens the multi-page batch print host.
        // That host renders one iframe per record inside ONE tab and triggers a single
        // print dialog covering all pages.
        $total = collect($registrations)->count();
        $label = $scope === 'rds' ? 'RDS' : 'CoR';

        return [[
            'type' => $label,
            'title' => sprintf('%s Batch Print (%d record%s)', $label, $total, $total === 1 ? '' : 's'),
            'url' => route('instrument_registration.batch-print-pages', [
                'session_id' => $sessionId,
                'scope' => $scope,
                'autoprint' => 1,
            ]),
        ]];
    }

    /**
     * Build the list of per-record iframe URLs embedded inside the batch print host.
     * Each iframe loads the existing official RDS/CoR template WITHOUT autoprint so
     * that only the host tab triggers a single print dialog for all pages.
     */
    private function buildBatchIframeDocuments($registrations, string $scope, string $sessionId): array
    {
        $documents = [];
        $total = collect($registrations)->count();

        foreach ($registrations as $registration) {
            $registeredId = 'deed_reg_' . $registration->id;
            $titleSuffix = $registration->fileno ?: ('Record ' . $registration->id);

            if ($scope === 'rds') {
                $documents[] = [
                    'type' => 'RDS',
                    'title' => 'RDS - ' . $titleSuffix,
                    'url' => route('rds.print', ['id' => $registeredId]) . '?' . http_build_query([
                        'batch_session_id' => $sessionId,
                        'batch_scope' => 'rds',
                        'batch_total' => $total,
                        'batch_doc_id' => $registeredId,
                        'embedded' => 1,
                    ]),
                ];
                continue;
            }

            $documents[] = [
                'type' => 'CoR',
                'title' => 'CoR - ' . $titleSuffix,
                'url' => route('coroi.index') . '?' . http_build_query([
                    'id' => $registeredId,
                    'is_unit' => 1,
                    'batch_session_id' => $sessionId,
                    'batch_scope' => 'cor',
                    'batch_total' => $total,
                    'batch_doc_id' => $registeredId,
                    'embedded' => 1,
                ]),
            ];
        }

        return $documents;
    }

    private function renderBatchPrintRunner(string $sessionId, $registrations, array $documents, ?string $errorMessage = null, array $options = [])
    {
        return view('instrument_registration.batch_print_runner', array_merge([
            'sessionId' => $sessionId,
            'registrations' => $registrations,
            'documents' => $documents,
            'errorMessage' => $errorMessage,
            'runnerTitle' => 'Batch Print Runner',
            'runnerDescription' => 'This page opens the official print templates for the selected registration batch one after another.',
            'queueCompletedMessage' => 'Batch print queue completed. If any browser print dialog was blocked, use the manual document links below.',
            'completionUrl' => null,
            'completionSessionId' => null,
            'completionButtonLabel' => 'Mark Session Complete',
            'completionPendingMessage' => 'Finalizing the batch session...',
            'completionSuccessMessage' => 'Batch session finalized successfully.',
            'completionFailureMessage' => 'The documents opened, but the batch session could not be finalized automatically. Use the completion button after printing manually.',
        ], $options));
    }

    private function markBatchSessionPrinted(string $sessionId): int
    {
        return DB::connection('sqlsrv')->table('deed_registrations')
            ->where('batch_session_id', $sessionId)
            ->whereNull('batch_printed_at')
            ->update([
                'batch_printed_at' => now(),
                'batch_printed_by' => Auth::id(),
                'updated_at' => now(),
            ]);
    }

    private function ensureBatchRdsReady(string $registeredId): void
    {
        $response = app(RDSController::class)->generateRDS($registeredId);
        $payload = method_exists($response, 'getData') ? $response->getData(true) : [];
        $statusCode = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;

        $alreadyExists = !empty($payload['rds_id']);
        $success = (bool) ($payload['success'] ?? false);

        if (!$success && !$alreadyExists && $statusCode >= 400) {
            throw new \RuntimeException($payload['error'] ?? 'Failed to generate batch RDS document.');
        }
    }

    private function ensureBatchCorReady(string $registeredId): void
    {
        $response = app(CoroiController::class)->generate(request(), $registeredId);
        $payload = method_exists($response, 'getData') ? $response->getData(true) : [];

        if (!(bool) ($payload['success'] ?? false)) {
            throw new \RuntimeException($payload['error'] ?? 'Failed to generate batch CoR document.');
        }
    }

    public function getBatchPrintSessions()
    {
        try {
            $registrations = DB::connection('sqlsrv')->table('deed_registrations')
                ->select('id', 'batch_session_id', 'created_at', 'rds_exists', 'cor_exists')
                ->whereNotNull('batch_session_id')
                ->whereNull('batch_printed_at')
                ->get()
                ->sortByDesc('created_at')
                ->values();

            $rdsTrackingLookup = $this->buildRdsTrackingLookup($registrations);

            $sessions = $registrations
                ->groupBy('batch_session_id')
                ->map(function ($rows, $sessionId) use ($rdsTrackingLookup) {
                    return $this->buildBatchSessionDescriptor((string) $sessionId, $rows, $rdsTrackingLookup);
                })
                ->sortByDesc('created_at')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $sessions,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch batch print sessions', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load batch print sessions: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function batchGenerateRds(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string|max:100',
        ]);

        $sessionId = trim((string) $request->input('session_id'));

        try {
            $registrations = $this->validateBatchSessionOrFail($sessionId);
            $rdsTrackingLookup = $this->buildRdsTrackingLookup($registrations);
            $generated = 0;
            $alreadyReady = 0;

            foreach ($registrations as $registration) {
                if (isset($rdsTrackingLookup['deed_reg_' . $registration->id])) {
                    $alreadyReady++;
                    continue;
                }

                $this->ensureBatchRdsReady('deed_reg_' . $registration->id);
                $generated++;
            }

            return response()->json([
                'success' => true,
                'message' => sprintf('RDS generated for %d record%s. %d already existed.', $generated, $generated === 1 ? '' : 's', $alreadyReady),
                'session' => $this->getBatchSessionDescriptor($sessionId),
            ]);
        } catch (\Throwable $e) {
            Log::error('Batch RDS generation failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function batchGenerateCor(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string|max:100',
        ]);

        $sessionId = trim((string) $request->input('session_id'));

        try {
            $registrations = $this->validateBatchSessionOrFail($sessionId);
            $rdsTrackingLookup = $this->buildRdsTrackingLookup($registrations);

            if ($registrations->contains(fn($row) => !isset($rdsTrackingLookup['deed_reg_' . $row->id]))) {
                throw new \RuntimeException('Generate RDS for every record in this session before generating batch CoR documents.');
            }

            $this->ensureBatchSessionRdsPrinted($registrations, $rdsTrackingLookup);

            $generated = 0;
            $alreadyReady = 0;

            foreach ($registrations as $registration) {
                if ((bool) ($registration->cor_exists ?? false)) {
                    $alreadyReady++;
                    continue;
                }

                $this->ensureBatchCorReady('deed_reg_' . $registration->id);
                $generated++;
            }

            return response()->json([
                'success' => true,
                'message' => sprintf('CoR generated for %d record%s. %d already existed.', $generated, $generated === 1 ? '' : 's', $alreadyReady),
                'session' => $this->getBatchSessionDescriptor($sessionId),
            ]);
        } catch (\Throwable $e) {
            Log::error('Batch CoR generation failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function batchDownloadRdsPdf(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string|max:100',
        ]);

        $sessionId = trim((string) $request->input('session_id'));

        try {
            $registrations = $this->validateBatchSessionOrFail($sessionId);
            $rdsTrackingLookup = $this->buildRdsTrackingLookup($registrations);

            if ($registrations->contains(fn($row) => !isset($rdsTrackingLookup['deed_reg_' . $row->id]))) {
                throw new \RuntimeException('Generate RDS for all records in this session before opening the batch RDS print tab.');
            }

            return response()->json([
                'success' => true,
                'message' => 'Official RDS print tabs are ready.',
                'documents' => $this->buildBatchPopupDocuments($registrations, 'rds', $sessionId),
                'session' => $this->getBatchSessionDescriptor($sessionId),
            ]);
        } catch (\Throwable $e) {
            Log::error('Batch RDS print runner failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to prepare the official RDS print tabs: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function batchDownloadCorPdf(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string|max:100',
        ]);

        $sessionId = trim((string) $request->input('session_id'));

        try {
            $registrations = $this->validateBatchSessionOrFail($sessionId);
            $rdsTrackingLookup = $this->buildRdsTrackingLookup($registrations);

            if ($registrations->contains(fn($row) => !isset($rdsTrackingLookup['deed_reg_' . $row->id]))) {
                throw new \RuntimeException('Generate RDS for all records in this session before opening the batch CoR print tab.');
            }

            $this->ensureBatchSessionRdsPrinted($registrations, $rdsTrackingLookup);

            if ($registrations->contains(fn($row) => !(bool) ($row->cor_exists ?? false))) {
                throw new \RuntimeException('Generate CoR for all records in this session before opening the batch CoR print tab.');
            }

            return response()->json([
                'success' => true,
                'message' => 'Official CoR print tabs are ready.',
                'documents' => $this->buildBatchPopupDocuments($registrations, 'cor', $sessionId),
                'session' => $this->getBatchSessionDescriptor($sessionId),
            ]);
        } catch (\Throwable $e) {
            Log::error('Batch CoR print runner failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to prepare the official CoR print tabs: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Render the single-tab batch print host page that embeds each record's official
     * print template in its own iframe. One print dialog, N pages.
     */
    public function batchPrintPages(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string|max:100',
            'scope' => 'required|in:rds,cor',
        ]);

        $sessionId = trim((string) $request->input('session_id'));
        $scope = (string) $request->input('scope');
        $autoprint = $request->boolean('autoprint', true);

        try {
            $registrations = $this->validateBatchSessionOrFail($sessionId);
            $rdsTrackingLookup = $this->buildRdsTrackingLookup($registrations);

            if ($registrations->contains(fn($row) => !isset($rdsTrackingLookup['deed_reg_' . $row->id]))) {
                throw new \RuntimeException('Generate RDS for all records in this session before opening the batch print page.');
            }

            // Increment print_count for RDS when batch print page loads
            if ($scope === 'rds') {
                foreach ($registrations as $registration) {
                    $trackingKey = 'deed_reg_' . $registration->id;
                    $tracking = $rdsTrackingLookup[$trackingKey] ?? null;
                    if ($tracking) {
                        // rdsTrackingLookup uses DB::table(...) so results are stdClass; update via query
                        DB::connection('sqlsrv')
                            ->table('rds_tracking')
                            ->where('instrument_id', $trackingKey)
                            ->increment('print_count', 1);
                    }
                }
                // refresh lookup after increment (not strictly necessary here, but keeps state consistent)
                $rdsTrackingLookup = $this->buildRdsTrackingLookup($registrations);
            }

            if ($scope === 'cor') {
                $this->ensureBatchSessionRdsPrinted($registrations, $rdsTrackingLookup);

                if ($registrations->contains(fn($row) => !(bool) ($row->cor_exists ?? false))) {
                    throw new \RuntimeException('Generate CoR for all records in this session before opening the batch CoR print page.');
                }
            }

            $documents = $this->buildBatchIframeDocuments($registrations, $scope, $sessionId);

            return view('instrument_registration.batch_print_pages', [
                'documents' => $documents,
                'scope' => $scope,
                'sessionId' => $sessionId,
                'autoprint' => $autoprint,
                'batchCompleteUrl' => route('instrument_registration.batch-complete-print-session'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Batch print pages failed', [
                'session_id' => $sessionId,
                'scope' => $scope,
                'error' => $e->getMessage(),
            ]);

            $message = e('Unable to open batch print page: ' . $e->getMessage());
            return response(
                '<!doctype html><html><head><title>Batch Print Error</title><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;padding:32px;color:#111827;max-width:720px;margin:auto}h1{color:#b91c1c}a{color:#1e3a8a}</style></head><body>'
                . '<h1>Batch Print Unavailable</h1><p>' . $message . '</p>'
                . '<p><button onclick="window.close()">Close</button></p>'
                . '</body></html>',
                422
            )->header('Content-Type', 'text/html; charset=UTF-8');
        }
    }

    public function completeBatchPrintSession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string|max:100',
        ]);

        $sessionId = trim((string) $request->input('session_id'));

        try {
            $registrations = $this->getActiveBatchSessionRegistrations($sessionId);

            if ($registrations->isEmpty()) {
                $alreadyCompleted = DB::connection('sqlsrv')->table('deed_registrations')
                    ->where('batch_session_id', $sessionId)
                    ->whereNotNull('batch_printed_at')
                    ->exists();

                if ($alreadyCompleted) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Batch session was already finalized.',
                        'already_completed' => true,
                    ]);
                }

                throw new \RuntimeException('No active batch registration session was found for the selected session ID.');
            }

            $rdsTrackingLookup = $this->buildRdsTrackingLookup($registrations);

            if ($registrations->contains(fn($row) => !isset($rdsTrackingLookup['deed_reg_' . $row->id]))) {
                throw new \RuntimeException('Generate RDS for every record in this session before finalizing the print session.');
            }

            $this->ensureBatchSessionRdsPrinted($registrations, $rdsTrackingLookup);

            if ($registrations->contains(fn($row) => !(bool) ($row->cor_exists ?? false))) {
                throw new \RuntimeException('Generate CoR for every record in this session before finalizing the print session.');
            }

            $updatedRows = $this->markBatchSessionPrinted($sessionId);

            return response()->json([
                'success' => true,
                'message' => $updatedRows > 0 ? 'Batch session finalized successfully.' : 'Batch session was already finalized.',
                'updated_rows' => $updatedRows,
            ]);
        } catch (\Throwable $e) {
            Log::error('Batch session finalization failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function batchPrintRunner(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string|max:100',
        ]);

        $sessionId = trim((string) $request->input('session_id'));

        try {
            $registrations = DB::connection('sqlsrv')->table('deed_registrations')
                ->where('batch_session_id', $sessionId)
                ->whereNull('batch_printed_at')
                ->orderBy('id')
                ->get();

            if ($registrations->isEmpty()) {
                return view('instrument_registration.batch_print_runner', [
                    'sessionId' => $sessionId,
                    'registrations' => collect(),
                    'documents' => [],
                    'errorMessage' => 'No unprinted registration batch was found for the selected session.',
                ]);
            }

            $documents = [];

            foreach ($registrations as $registration) {
                $registeredId = 'deed_reg_' . $registration->id;

                if (!(bool) ($registration->rds_exists ?? false)) {
                    $this->ensureBatchRdsReady($registeredId);
                }

                if (!(bool) ($registration->cor_exists ?? false)) {
                    $this->ensureBatchCorReady($registeredId);
                }
            }

            $documents = $this->buildBatchRunnerDocuments($registrations, 'combined');

            return $this->renderBatchPrintRunner($sessionId, $registrations, $documents, null, [
                'runnerTitle' => 'Batch RDS and CoR Print Runner',
                'runnerDescription' => 'This tab opens the official RDS and CoR templates for the selected registration batch one after another.',
                'queueCompletedMessage' => 'The batch RDS and CoR queue completed. The session will be finalized automatically when the queue finishes.',
                'completionUrl' => route('instrument_registration.batch-complete-print-session'),
                'completionSessionId' => $sessionId,
                'completionButtonLabel' => 'Mark Batch Complete',
                'completionPendingMessage' => 'Finalizing the batch session...',
                'completionSuccessMessage' => 'The batch session has been finalized.',
                'completionFailureMessage' => 'The documents opened, but the batch session could not be finalized automatically. Use the completion button after printing manually.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Batch print runner failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->renderBatchPrintRunner(
                $sessionId,
                collect(),
                [],
                'Failed to prepare the batch documents: ' . $e->getMessage(),
                [
                    'runnerTitle' => 'Batch RDS and CoR Print Runner',
                    'runnerDescription' => 'This tab opens the official RDS and CoR templates for the selected registration batch one after another.',
                ]
            );
        }
    }

    /**
     * Build grouped ST data: one group per mother application (with its PUA units)
     * and one group per standalone SUA. Each group tracks Fragmentation, Assignment,
     * and CofO status independently, enforcing the registration order rules.
     */
    private function buildSTGroups($approvedSubapplications, $approvedMotherApplications): \Illuminate\Support\Collection
    {
        $groups = collect();

        // Collect all file numbers for a single batch deed_registrations query
        $subFilenos       = $approvedSubapplications->pluck('fileno')->filter()->unique()->values()->toArray();
        $motherFilenos    = $approvedMotherApplications->pluck('fileno')->filter()->unique()->values()->toArray();
        $motherNpFilenos  = $approvedMotherApplications->pluck('np_fileno')->filter()->unique()->values()->toArray();
        $allFilenos       = array_unique(array_merge($subFilenos, $motherFilenos, $motherNpFilenos));

        if (empty($allFilenos)) {
            return $groups;
        }

        $deedRegs = DB::connection('sqlsrv')->table('deed_registrations')
            ->whereIn('fileno', $allFilenos)
            ->whereIn('instrument_type', [
                'ST Assignment (Transfer of Title)',
                'Sectional Titling CofO',
                'ST Fragmentation',
            ])
            ->where('status', 'registered')
            ->get();

        // Lookup: "fileno::instrument_type" => registration row
        $deedLookup = [];
        foreach ($deedRegs as $reg) {
            $deedLookup[$reg->fileno . '::' . $reg->instrument_type] = $reg;
        }

        // Partition subapplications into PUA (grouped by mother) and SUA (standalone)
        $puaByMother = [];
        $suaList     = [];

        foreach ($approvedSubapplications as $sub) {
            if ((int) ($sub->is_sua_unit ?? 0) === 1) {
                $suaList[] = $sub;
            } elseif (!empty($sub->main_application_id)) {
                $puaByMother[$sub->main_application_id][] = $sub;
            }
        }

        // --- Build Mother + PUA groups ---
        foreach ($approvedMotherApplications as $m) {
            $units = $puaByMother[$m->id] ?? [];

            // Mother-level ST Fragmentation (try both np_fileno and fileno)
            $fragReg = $deedLookup[($m->np_fileno ?? $m->fileno) . '::ST Fragmentation']
                    ?? $deedLookup[$m->fileno . '::ST Fragmentation']
                    ?? null;

            $unitData        = [];
            $assignRegistered = 0;
            $cofoRegistered  = 0;

            foreach ($units as $sub) {
                $isPrimary = (int) ($sub->is_primary_the_owner ?? 0) === 1;

                if ($isPrimary) {
                    // Primary-owner-retention unit: gets a Fragmentation instead of Assignment
                    $uFragReg         = $deedLookup[$sub->fileno . '::ST Fragmentation'] ?? null;
                    $assignStatus     = 'fragmentation';
                    $assignDeedRegId  = $uFragReg ? 'deed_reg_' . $uFragReg->id : null;
                    $cofoEnabled      = (bool) $uFragReg; // CofO only enabled once the unit-level fragmentation is registered
                    if ($uFragReg) {
                        $assignRegistered++;
                    }
                } else {
                    $aReg            = $deedLookup[$sub->fileno . '::ST Assignment (Transfer of Title)'] ?? null;
                    $assignStatus    = $aReg ? 'registered' : 'pending';
                    $assignDeedRegId = $aReg ? 'deed_reg_' . $aReg->id : null;
                    $cofoEnabled     = (bool) $aReg;
                    if ($aReg) {
                        $assignRegistered++;
                    }
                }

                $cReg = $deedLookup[$sub->fileno . '::Sectional Titling CofO'] ?? null;
                if ($cReg) {
                    $cofoRegistered++;
                }

                // Separate reg data for each instrument type
                $fragRegForUnit = $isPrimary
                    ? ($deedLookup[$sub->fileno . '::ST Fragmentation'] ?? null)
                    : null;

                $unitData[] = [
                    'subapp_id'              => $sub->id,
                    'fileno'                 => $sub->fileno,
                    'applicant'              => $this->buildApplicantDisplayName(
                        $sub->sub_applicant_title ?? null,
                        $sub->sub_applicant_first_name ?? null,
                        $sub->sub_applicant_middle_name ?? null,
                        $sub->sub_applicant_surname ?? null,
                        $sub->sub_applicant_corporate_name ?? null,
                        $sub->sub_applicant_rc_number ?? null,
                        $sub->sub_applicant_multiple_owners ?? null
                    ) ?: ($sub->sub_applicant ?? 'N/A'),
                    'is_primary_owner'       => $isPrimary,
                    'captured_date'          => $sub->created_at,
                    'assignment_status'      => $assignStatus,
                    'assignment_deed_reg_id' => $assignDeedRegId,
                    'cofo_status'            => $cReg ? 'registered' : 'pending',
                    'cofo_enabled'           => $cofoEnabled,
                    'cofo_deed_reg_id'       => $cReg ? 'deed_reg_' . $cReg->id : null,
                    'cofo_rds_exists'        => (bool) ($cReg ? ($cReg->rds_exists ?? false) : false),
                    'cofo_cor_exists'        => (bool) ($cReg ? ($cReg->cor_exists ?? false) : false),
                    // Fragmentation reg (primary-owner units only)
                    'frag_registered'        => (bool) $fragRegForUnit,
                    'frag_reg_particulars'   => $fragRegForUnit ? ($fragRegForUnit->registration_number ?? null) : null,
                    'frag_reg_date'          => $fragRegForUnit ? ($fragRegForUnit->instrument_date ?? null) : null,
                    'frag_reg_time'          => $fragRegForUnit ? ($fragRegForUnit->deeds_time ?? null) : null,
                    // Assignment reg (non-primary units only)
                    'assign_registered'      => (bool) ($aReg ?? null),
                    'assign_reg_particulars' => ($aReg ?? null) ? ($aReg->registration_number ?? null) : null,
                    'assign_reg_date'        => ($aReg ?? null) ? ($aReg->instrument_date ?? null) : null,
                    'assign_reg_time'        => ($aReg ?? null) ? ($aReg->deeds_time ?? null) : null,
                    // CofO reg (all units)
                    'cofo_reg_particulars'   => $cReg ? ($cReg->registration_number ?? null) : null,
                    'cofo_reg_date'          => $cReg ? ($cReg->instrument_date ?? null) : null,
                    'cofo_reg_time'          => $cReg ? ($cReg->deeds_time ?? null) : null,
                ];
            }

            // CofO at group level is enabled when at least one unit has its prerequisite met:
            // primary-owner units need their unit-level fragmentation; non-primary units need assignment.
            $cofoGroupEnabled = $assignRegistered > 0;

            $motherName = $this->buildApplicantDisplayName(
                $m->applicant_title ?? null,
                $m->first_name ?? null,
                $m->middle_name ?? null,
                $m->surname ?? null,
                $m->corporate_name ?? null,
                $m->rc_number ?? null,
                $m->multiple_owners_names ?? null
            ) ?: ($m->mother_applicant ?? 'N/A');

            $groups->push([
                'id'                 => 'grp_m_' . $m->id,
                'type'               => 'mother_pua',
                'display_fileno'     => $m->np_fileno ?? $m->fileno,
                'applicant'          => $motherName,
                'unit_count'         => count($units),
                'lga'                => $m->lga ?? null,
                'district'           => $m->district ?? null,
                'captured_date'      => $m->created_at,
                'frag_status'        => $fragReg ? 'registered' : 'pending',
                'frag_deed_reg_id'   => $fragReg ? 'deed_reg_' . $fragReg->id : null,
                'frag_serial'        => $fragReg ? ($fragReg->registration_number ?? '0/0/0') : null,
                'frag_rds_exists'    => (bool) ($fragReg ? ($fragReg->rds_exists ?? false) : false),
                'frag_cor_exists'    => (bool) ($fragReg ? ($fragReg->cor_exists ?? false) : false),
                'units'              => $unitData,
                'assign_registered'  => $assignRegistered,
                'assign_total'       => count($units),
                'cofo_registered'    => $cofoRegistered,
                'cofo_total'         => count($units),
                'cofo_enabled'       => $cofoGroupEnabled,
            ]);
        }

        // --- Build SUA groups (no mother, no fragmentation) ---
        foreach ($suaList as $sub) {
            $aReg = $deedLookup[$sub->fileno . '::ST Assignment (Transfer of Title)'] ?? null;
            $cReg = $deedLookup[$sub->fileno . '::Sectional Titling CofO'] ?? null;

            $applicant = $this->buildApplicantDisplayName(
                $sub->sub_applicant_title ?? null,
                $sub->sub_applicant_first_name ?? null,
                $sub->sub_applicant_middle_name ?? null,
                $sub->sub_applicant_surname ?? null,
                $sub->sub_applicant_corporate_name ?? null,
                $sub->sub_applicant_rc_number ?? null,
                $sub->sub_applicant_multiple_owners ?? null
            ) ?: ($sub->sub_applicant ?? 'N/A');

            $groups->push([
                'id'                => 'grp_sua_' . $sub->id,
                'type'              => 'sua',
                'display_fileno'    => $sub->fileno,
                'applicant'         => $applicant,
                'unit_count'        => 1,
                'lga'               => $sub->lga ?? null,
                'district'          => $sub->district ?? null,
                'captured_date'     => $sub->created_at,
                'frag_status'       => null,
                'frag_deed_reg_id'  => null,
                'frag_serial'       => null,
                'frag_rds_exists'   => false,
                'frag_cor_exists'   => false,
                'units'             => [[
                    'subapp_id'              => $sub->id,
                    'fileno'                 => $sub->fileno,
                    'applicant'             => $applicant,
                    'is_primary_owner'       => false,
                    'captured_date'          => $sub->created_at,
                    'assignment_status'      => $aReg ? 'registered' : 'pending',
                    'assignment_deed_reg_id' => $aReg ? 'deed_reg_' . $aReg->id : null,
                    'cofo_status'            => $cReg ? 'registered' : 'pending',
                    'cofo_enabled'           => (bool) $aReg,
                    'cofo_deed_reg_id'       => $cReg ? 'deed_reg_' . $cReg->id : null,
                    'cofo_rds_exists'        => (bool) ($cReg ? ($cReg->rds_exists ?? false) : false),
                    'cofo_cor_exists'        => (bool) ($cReg ? ($cReg->cor_exists ?? false) : false),
                    'frag_registered'        => false,
                    'frag_reg_particulars'   => null,
                    'frag_reg_date'          => null,
                    'frag_reg_time'          => null,
                    'assign_registered'      => (bool) $aReg,
                    'assign_reg_particulars' => $aReg ? ($aReg->registration_number ?? null) : null,
                    'assign_reg_date'        => $aReg ? ($aReg->instrument_date ?? null) : null,
                    'assign_reg_time'        => $aReg ? ($aReg->deeds_time ?? null) : null,
                    'cofo_reg_particulars'   => $cReg ? ($cReg->registration_number ?? null) : null,
                    'cofo_reg_date'          => $cReg ? ($cReg->instrument_date ?? null) : null,
                    'cofo_reg_time'          => $cReg ? ($cReg->deeds_time ?? null) : null,
                ]],
                'assign_registered' => $aReg ? 1 : 0,
                'assign_total'      => 1,
                'cofo_registered'   => $cReg ? 1 : 0,
                'cofo_total'        => 1,
                'cofo_enabled'      => (bool) $aReg,
            ]);
        }

        return $groups->sortByDesc('captured_date')->values();
    }

    private function getApplication($id)
    {
        $application = DB::connection('sqlsrv')->table('mother_applications')
            ->where('id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Application not found'], 404);
        }

        return $application;
    }

    private function generateSTMReference()
    {
        $year = date('Y');
        $latestRef = DB::connection('sqlsrv')->table('registered_instruments')
            ->where('STM_Ref', 'like', "STM-$year-%")
            ->orderBy('id', 'desc')
            ->value('STM_Ref');

        if ($latestRef) {
            $matches = [];
            if (preg_match('/STM-\\d{4}-(\\d{4})/', $latestRef, $matches)) {
                $sequence = (int) $matches[1] + 1;
            } else {
                $sequence = 1;
            }
        } else {
            $sequence = 1;
        }

        return "STM-{$year}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    private function normalizeApplicantComponent($value)
    {
        if (is_null($value)) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return null;
            }

            $lower = strtolower($trimmed);
            if (in_array($lower, ['null', '[null]', 'n/a', 'na', '[]'], true)) {
                return null;
            }

            if ((str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) || (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}'))) {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->normalizeApplicantComponent($decoded);
                }
            }

            return $trimmed;
        }

        if (is_array($value)) {
            $normalizedItems = [];
            foreach ($value as $item) {
                $normalizedItem = $this->normalizeApplicantComponent($item);
                if (!is_null($normalizedItem)) {
                    $normalizedItems[] = $normalizedItem;
                }
            }

            return empty($normalizedItems) ? null : implode(', ', $normalizedItems);
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    private function buildApplicantDisplayName($title, $firstName, $middleName, $surname, $corporateName, $rcNumber, $multipleOwners = null)
    {
        $corporate = $this->normalizeApplicantComponent($corporateName);
        $rc = $this->normalizeApplicantComponent($rcNumber);
        $multiple = $this->normalizeApplicantComponent($multipleOwners);

        if ($corporate) {
            $parts = [$corporate];

            if ($multiple) {
                $parts[] = $multiple;
            }

            return implode(' ', array_filter($parts));
        }

        $parts = array_filter([
            $this->normalizeApplicantComponent($title),
            $this->normalizeApplicantComponent($firstName),
            $this->normalizeApplicantComponent($middleName),
            $this->normalizeApplicantComponent($surname),
        ]);

        if (empty($parts) && $rc) {
            $parts[] = $rc;
        }

        if ($multiple) {
            $parts[] = $multiple;
        }

        return implode(' ', $parts);
    }

    /**
     * Fill in prop_id on the rows that do not carry one of their own.
     *
     * The register is built from several sources and only two of them
     * (instrument_capture, deed_registrations) keep a prop_id column; ST and
     * instrument_registration rows have none. Rather than look each one up,
     * every outstanding file number goes to PropID_Master in a handful of
     * batched queries - at most one per file-number column, and fewer once a
     * column has answered for everything.
     *
     * A file can hold more than one parcel, so a file number can match several
     * prop_ids. The first by id wins - the value is shown as a hint and as one
     * of the two keys the property timeline accepts, and the timeline falls
     * back to the file number regardless.
     *
     * Best effort: a failure here leaves the column blank and must never take
     * the register down with it.
     */
    private function attachPropIds($rows): void
    {
        $wanted = [];

        foreach ($rows as $row) {
            if (!isset($row->prop_id)) {
                $row->prop_id = null;
            }

            if (!empty($row->prop_id)) {
                continue;
            }

            // Same placeholders PropertyIdAllocationService rejects: these
            // collide across thousands of unrelated files and would hand back a
            // prop_id belonging to none of them.
            $fileNo = strtoupper(trim((string) ($row->fileno ?? '')));
            if ($fileNo !== '' && !in_array($fileNo, ['N/A', 'NA', 'NONE', 'NULL', 'NIL', '-', '.', '0', 'TBD'], true)) {
                $wanted[$fileNo] = $fileNo;
            }
        }

        if (empty($wanted)) {
            return;
        }

        // Matched on the _norm columns, not the plain ones: those are the only
        // file-number columns PropID_Master indexes, and _norm holds exactly
        // the trimmed, upper-cased form $wanted is keyed on.
        //
        // The plain column is named beside each one because four of those five
        // indexes are FILTERED on the plain column being NOT NULL. A predicate
        // on _norm alone does not prove that filter, so SQL Server refuses the
        // index and scans all 138k rows - 218ms a column against 62ms once the
        // IS NOT NULL is stated.
        $columns = [
            'primary_file_number_norm' => 'primary_file_number',
            'mlsFNo_norm' => 'mlsFNo',
            'kangisFileNo_norm' => 'kangisFileNo',
            'NewKANGISFileno_norm' => 'NewKANGISFileno',
            'temp_fileno_norm' => 'temp_fileno',
        ];

        $map = [];
        $remaining = array_keys($wanted);

        try {
            // One column at a time, each pass carrying only what the passes
            // before it could not answer. OR-ing all five into a single
            // statement costs 5 seeks per file number whatever the first one
            // found - measured at 1,535ms against 667ms for the same 78 file
            // numbers and the same 61 matches.
            foreach ($columns as $column => $plainColumn) {
                if (empty($remaining)) {
                    break;
                }

                // Chunked so a large register cannot push the statement past
                // the 2,100-parameter ceiling sqlsrv enforces.
                foreach (array_chunk($remaining, 1000) as $chunk) {
                    $matches = DB::connection('sqlsrv')->table('PropID_Master')
                        ->whereNotNull('prop_id')
                        ->whereNotNull($plainColumn)
                        ->whereIn($column, $chunk)
                        ->orderBy('id')
                        ->get(['prop_id', $column]);

                    foreach ($matches as $match) {
                        $key = strtoupper(trim((string) ($match->$column ?? '')));
                        if ($key !== '' && !isset($map[$key])) {
                            $map[$key] = $match->prop_id;
                        }
                    }
                }

                $remaining = array_values(array_filter(
                    $remaining,
                    fn ($fileNo) => !isset($map[$fileNo])
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Instrument Registration: PropID lookup failed', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($rows as $row) {
            if (!empty($row->prop_id)) {
                continue;
            }

            $key = strtoupper(trim((string) ($row->fileno ?? '')));
            if ($key !== '' && isset($map[$key])) {
                $row->prop_id = $map[$key];
            }
        }
    }

    private function mapCaptureTypeToRegistry($captureType)
    {
        $mapping = [
            'Power of Attorney' => 'Power of Attorney',
            'Irrevocable Power of Attorney' => 'Power of Attorney',
            'Deed of Mortgage' => 'Deed of Mortgage',
            'Tripartite Mortgage' => 'Deed of Mortgage',
            'Deed of Assignment' => 'Deed of Assignment',
            'Deed of Lease' => 'Deed of Lease',
            'Deed of Sub-Lease' => 'Sub Lease',
            'Deed of Surrender / Release' => 'Deed of Surrender and Release',
            'Occupancy Permit' => 'Occupancy Permit (OP)',
            'Devolution Order' => 'Devolution Order',
            'Deed of Gift' => 'Deed of Gift',
            'Deed of Sub-Division' => 'Deed of Sub-Division',
            'Deed of Merger' => 'Deed of Merger',
        ];

        return $mapping[$captureType] ?? $captureType;
    }

    public function InstrumentRegistration()
    {
        $isStDeeds = request()->query('url') === 'st_deeds';
        $PageTitle = $isStDeeds ? 'ST Deeds Registration' : 'Instrument Registration ';
        $PageDescription = '';

        try {
            // Initialize default completion status for subapplications that don't have it set
            $this->initializeDefaultCompletionStatus();

            // Automatically insert ST Fragmentation details for approved mother applications
            $this->autoRegisterSTFragmentation();
            $this->autoRegisterSTFragmentationForSubApps();

            // Get approved subapplications and create both ST Assignment and Sectional Titling records for each
            $approvedSubapplications = DB::connection('sqlsrv')->table('subapplications as s')
                ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                ->leftJoin('users', 's.created_by', '=', 'users.id')
                ->where('s.application_status', 'Approved')
                ->where(function ($query) {
                    $query->where('s.planning_recommendation_status', 'Approved')
                        ->orWhere(function ($q) {
                            $q->where('m.planning_recommendation_status', 'Approved')
                                ->where('s.is_sua_unit', 0); // Assuming logic for PUA vs SUA, adjust if column differs or is handled differently
                        });
                })
                ->select(
                    's.id',
                    's.fileno',
                    's.unit_type',
                    's.allocation_entity',
                    's.allocation_source',
                    's.address as sub_address',
                    's.np_fileno as sub_np_fileno',
                    's.deeds_completion_status',
                    's.applicant_title as sub_applicant_title',
                    's.first_name as sub_applicant_first_name',
                    's.middle_name as sub_applicant_middle_name',
                    's.surname as sub_applicant_surname',
                    's.corporate_name as sub_applicant_corporate_name',
                    's.rc_number as sub_applicant_rc_number',
                    's.multiple_owners_names as sub_applicant_multiple_owners',
                    DB::raw("CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.middle_name,''), ' ', COALESCE(s.surname,''), COALESCE(s.corporate_name,''), COALESCE(s.rc_number,''), COALESCE(s.multiple_owners_names,'')) as sub_applicant"),
                    DB::raw("CONCAT(COALESCE(m.applicant_title,''), ' ', COALESCE(m.first_name,''), ' ', COALESCE(m.middle_name,''), ' ', COALESCE(m.surname,''), COALESCE(m.corporate_name,''), COALESCE(m.rc_number,''), COALESCE(m.multiple_owners_names,'')) as mother_applicant"),
                    'm.applicant_title as mother_applicant_title',
                    'm.first_name as mother_applicant_first_name',
                    'm.middle_name as mother_applicant_middle_name',
                    'm.surname as mother_applicant_surname',
                    'm.corporate_name as mother_applicant_corporate_name',
                    'm.rc_number as mother_applicant_rc_number',
                    'm.multiple_owners_names as mother_applicant_multiple_owners',
                    'm.property_lga as lga',
                    'm.property_district as district',
                    'm.plot_size as size',
                    'm.property_plot_no as plotNumber',
                    'm.np_fileno', // Add np_fileno for parent_fileNo
                    'm.address as mother_address',
                    // Add property description fields from mother application
                    'm.property_house_no',
                    'm.property_street_name',
                    's.created_by as reg_created_by',
                    's.created_at',
                    's.is_sua_unit',
                    's.is_primary_the_owner',
                    's.main_application_id',
                    DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                )
                ->get();

            // Get approved mother applications for ST Fragmentation records
            $approvedMotherApplications = DB::connection('sqlsrv')->table('mother_applications as m')
                ->leftJoin('users', 'm.created_by', '=', 'users.id')
                ->where('m.planning_recommendation_status', 'Approved')
                ->where('m.application_status', 'Approved')
                ->select(
                    'm.id',
                    'm.fileno',
                    'm.np_fileno',
                    'm.applicant_title',
                    'm.first_name',
                    'm.middle_name',
                    'm.surname',
                    'm.corporate_name',
                    'm.rc_number',
                    'm.multiple_owners_names',
                    'm.owner_fullname as mother_applicant',
                    'm.property_lga as lga',
                    'm.property_district as district',
                    'm.plot_size as size',
                    'm.property_plot_no as plotNumber',
                    'm.property_house_no',
                    'm.property_street_name',
                    'm.address as mother_address',
                    'm.created_by as reg_created_by',
                    'm.created_at',
                    DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                )
                ->get();

            // Get unregistered instruments from instrument_registration table
            $unregisteredInstrumentsQuery = DB::connection('sqlsrv')->table('instrument_registration')
                ->leftJoin('users', 'instrument_registration.created_by', '=', 'users.id')
                ->where(function ($q) {
                    $q->where('instrument_registration.status', '!=', 'registered')
                        ->orWhereNull('instrument_registration.status');
                });

            if ($isStDeeds) {
                $unregisteredInstrumentsQuery->where(function ($q) {
                    $q->where('instrument_registration.MLSFileNo', 'like', 'ST-%')
                        ->orWhere('instrument_registration.KAGISFileNO', 'like', 'ST-%')
                        ->orWhere('instrument_registration.NewKANGISFileNo', 'like', 'ST-%')
                        ->orWhere('instrument_registration.temp_fileno', 'like', 'ST-%');
                });
            }

            $unregisteredInstruments = $unregisteredInstrumentsQuery->select(
                'instrument_registration.*',
                DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
            )
            ->get();

            // Create collection for all instruments
            $allInstruments = collect();

            // Add unregistered instruments from instrument_registration table
            foreach ($unregisteredInstruments as $instrument) {
                // Determine the file number to use
                $fileNo = $instrument->MLSFileNo ?? $instrument->KAGISFileNO ?? $instrument->NewKANGISFileNo ?? 'N/A';

                $instrumentRecord = (object) [
                    'id' => 'instr_reg_' . $instrument->id,
                    'fileno' => $fileNo,
                    'parent_fileNo' => null, // Other instruments don't have parent file numbers
                    'Deeds_Serial_No' => $instrument->particularsRegistrationNumber ?? null,
                    'volume_no' => $instrument->volumeNo ?? $instrument->volume_no ?? $instrument->oldTitleVolumeNo ?? null,
                    'instrument_type' => $instrument->instrument_type ?? 'Other Instrument',
                    'Grantor' => $instrument->Grantor ?? 'N/A',
                    'Grantee' => $instrument->Grantee ?? 'N/A',
                    'GrantorAddress' => $instrument->GrantorAddress ?? '',
                    'GranteeAddress' => $instrument->GranteeAddress ?? '',
                    'duration' => $instrument->duration ?? $instrument->leasePeriod ?? '',
                    'leasePeriod' => $instrument->leasePeriod ?? '',
                    'propertyDescription' => $instrument->propertyDescription ?? '',
                    'lga' => $instrument->lga ?? '',
                    'district' => $instrument->district ?? '',
                    'size' => $instrument->size ?? '',
                    'plotNumber' => $instrument->plotNumber ?? '',
                    'deeds_date' => $instrument->instrumentDate ?? null,
                    'solicitorName' => $instrument->solicitorName ?? '',
                    'solicitorAddress' => $instrument->solicitorAddress ?? '',
                    'status' => $instrument->status ?? 'pending',
                    'land_use' => $instrument->landUseType ?? '',
                    'reg_created_by' => $instrument->created_by,
                    'created_at' => $instrument->created_at,
                    'reg_creator_name' => $instrument->reg_creator_name ?? '',
                    'instrument_category' => 'Other Instruments',
                    'STM_Ref' => null, // Other instruments don't have STM_Ref until registered
                    'original_instrument_id' => $instrument->id,
                    'registered_instrument_id' => null,
                    'application_type' => 'Other',
                    // Add captured date and reg date fields
                    'captured_date' => $instrument->created_at ?? $instrument->instrumentDate, // Prefer system capture time
                    'reg_date' => null, // Other instruments don't have reg_date until registered
                    'reg_time' => null
                ];

                $allInstruments->push($instrumentRecord);
            }

            // Get unregistered instruments from instrument_capture table
            if ($isStDeeds) {
                $capturedInstruments = collect();
            } else {
                $capturedInstruments = DB::connection('sqlsrv')->table('instrument_capture')
                    ->leftJoin('users', 'instrument_capture.created_by', '=', 'users.id')
                    ->where(function ($q) {
                        $q->where('instrument_capture.is_deed_registered', 0)
                            ->orWhereNull('instrument_capture.is_deed_registered');
                    })
                    // Land Registration keeps its own register; its rows must not
                    // also appear on the Digitization screen.
                    ->where('instrument_capture.instrument_type', '<>', config('land_registration.instrument_type'))
                    ->select(
                        'instrument_capture.*',
                        DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                    )
                    ->get();
            }

            foreach ($capturedInstruments as $instrument) {
                // Determine the file number to use
                $fileNo = $instrument->mlsFNo ?? $instrument->kangisFileNo ?? $instrument->NewKANGISFileno ?? $instrument->temp_fileno ?? 'N/A';

                // Map hardcoded type to Registry managed type
                $mappedType = $this->mapCaptureTypeToRegistry($instrument->instrument_type);

                $instrumentRecord = (object) [
                    'id' => 'instr_capture_' . $instrument->id,
                    'fileno' => $fileNo,
                    'parent_fileNo' => null,
                    'Deeds_Serial_No' => $instrument->registration_number ?? null,
                    'volume_no' => $instrument->volume_no ?? null,
                    'instrument_type' => $mappedType,
                    'Grantor' => $instrument->party_1_name ?? 'N/A',
                    'Grantee' => $instrument->party_2_name ?? 'N/A',
                    'GrantorAddress' => $instrument->party_1_address ?? '',
                    'GranteeAddress' => $instrument->party_2_address ?? '',
                    'duration' => $instrument->duration ?? '',
                    'leasePeriod' => $instrument->duration ?? '',
                    'propertyDescription' => $instrument->property_description ?? '',
                    'lga' => $instrument->lga ?? '',
                    'district' => $instrument->district ?? '',
                    'size' => $instrument->plot_size ?? '',
                    'plotNumber' => $instrument->plot_number ?? '',
                    'deeds_date' => $instrument->instrument_date ?? null,
                    'solicitorName' => $instrument->solicitor_name ?? '',
                    'solicitorAddress' => $instrument->solicitor_address ?? '',
                    'status' => 'pending',
                    'land_use' => $instrument->land_use ?? '',
                    'reg_created_by' => $instrument->created_by,
                    'created_at' => $instrument->created_at,
                    'reg_creator_name' => $instrument->reg_creator_name ?? '',
                    'instrument_category' => 'Captured Instruments',
                    'STM_Ref' => null,
                    'original_instrument_id' => $instrument->id,
                    'registered_instrument_id' => null,
                    'application_type' => 'Captured',
                    'captured_date' => $instrument->created_at,
                    'reg_date' => null,
                    'reg_time' => null,
                    'prop_id' => $instrument->prop_id ?? null,
                    'op_type' => (!empty($instrument->op_type) && !str_starts_with($instrument->op_type, 'OP ')) ? 'OP ' . $instrument->op_type : ($instrument->op_type ?? null),
                ];

                $allInstruments->push($instrumentRecord);
            }

            // For each approved subapplication, create both ST Assignment and Sectional Titling records
            foreach ($approvedSubapplications as $subApp) {
                $unitTypeRaw = trim((string) ($subApp->unit_type ?? ''));
                $unitType = $unitTypeRaw !== '' ? strtoupper($unitTypeRaw) : null;
                $displayApplicationType = ((int) ($subApp->is_sua_unit ?? 0) === 1) ? 'SUA' : 'PUA';
                $subApplicationParentFile = $subApp->sub_np_fileno ?? null;

                $subApp->sub_applicant = $this->buildApplicantDisplayName(
                    $subApp->sub_applicant_title ?? null,
                    $subApp->sub_applicant_first_name ?? null,
                    $subApp->sub_applicant_middle_name ?? null,
                    $subApp->sub_applicant_surname ?? null,
                    $subApp->sub_applicant_corporate_name ?? null,
                    $subApp->sub_applicant_rc_number ?? null,
                    $subApp->sub_applicant_multiple_owners ?? null
                ) ?: null;

                $subApp->mother_applicant = $this->buildApplicantDisplayName(
                    $subApp->mother_applicant_title ?? null,
                    $subApp->mother_applicant_first_name ?? null,
                    $subApp->mother_applicant_middle_name ?? null,
                    $subApp->mother_applicant_surname ?? null,
                    $subApp->mother_applicant_corporate_name ?? null,
                    $subApp->mother_applicant_rc_number ?? null,
                    $subApp->mother_applicant_multiple_owners ?? null
                ) ?: null;

                $allocationEntity = $this->normalizeApplicantComponent($subApp->allocation_entity ?? null);

                // Get registration details from registered_instruments table
                // Get registration details from deed_registrations table (New Architecture)
                $stRegistration = DB::connection('sqlsrv')->table('deed_registrations')
                    ->where('fileno', $subApp->fileno)
                    ->where('instrument_type', 'ST Assignment (Transfer of Title)')
                    ->where('status', 'registered')
                    ->select('*', 'registration_number as particularsRegistrationNumber', 'deeds_date as instrumentDate')
                    ->first();

                $sectionalRegistration = DB::connection('sqlsrv')->table('deed_registrations')
                    ->where('fileno', $subApp->fileno)
                    ->where('instrument_type', 'Sectional Titling CofO')
                    ->where('status', 'registered')
                    ->select('*', 'registration_number as particularsRegistrationNumber', 'deeds_date as instrumentDate')
                    ->first();

                $stFragmentationRegistration = DB::connection('sqlsrv')->table('deed_registrations')
                    ->where('fileno', $subApp->fileno)
                    ->where('instrument_type', 'ST Fragmentation')
                    ->where('status', 'registered')
                    ->select('*', 'registration_number as particularsRegistrationNumber', 'deeds_date as instrumentDate')
                    ->first();

                // Decode JSON data for STM_Ref if needed
                if ($stRegistration && !empty($stRegistration->data)) {
                    $stRegistration->STM_Ref = json_decode($stRegistration->data)->stm_ref ?? null;
                }
                if ($sectionalRegistration && !empty($sectionalRegistration->data)) {
                    $sectionalRegistration->STM_Ref = json_decode($sectionalRegistration->data)->stm_ref ?? null;
                }
                if ($stFragmentationRegistration && !empty($stFragmentationRegistration->data)) {
                    $stFragmentationRegistration->STM_Ref = json_decode($stFragmentationRegistration->data)->stm_ref ?? null;
                }

                // Build property description from mother application details
                $propertyDescription = '';
                $propertyParts = [];

                if (!empty($subApp->property_house_no)) {
                    $propertyParts[] = 'House No: ' . $subApp->property_house_no;
                }
                if (!empty($subApp->plotNumber)) {
                    $propertyParts[] = 'Plot No: ' . $subApp->plotNumber;
                }
                if (!empty($subApp->property_street_name)) {
                    $propertyParts[] = $subApp->property_street_name;
                }
                if (!empty($subApp->district)) {
                    $propertyParts[] = $subApp->district;
                }
                if (!empty($subApp->lga)) {
                    $propertyParts[] = $subApp->lga;
                }

                $propertyDescription = implode(', ', $propertyParts);
                if (empty($propertyDescription)) {
                    $propertyDescription = 'Property details not available';
                }

                $assignmentParentFileNo = $unitType === 'SUA'
                    ? ($subApplicationParentFile ?: $subApp->fileno)
                    : ($subApp->np_fileno ?? null);

                $assignmentGrantor = $unitType === 'SUA'
                    ? ($allocationEntity ?: $subApp->mother_applicant)
                    : $subApp->mother_applicant;

                // Determine if this should be ST Assignment or ST Fragmentation (Primary Owner Retention)
                $isPrimaryOwnerRetention = (int) ($subApp->is_primary_the_owner ?? 0) === 1;

                if ($isPrimaryOwnerRetention) {
                    // CASE 1: Primary Owner Retention -> ST Fragmentation (Auto-Registered 0/0/0)

                    if ($stFragmentationRegistration) {
                        // Use persisted record
                        $stAssignmentRecord = (object) [
                            'id' => $subApp->id . '_st_assignment',
                            'fileno' => $subApp->fileno,
                            'parent_fileNo' => $assignmentParentFileNo,
                            'Deeds_Serial_No' => $stFragmentationRegistration->particularsRegistrationNumber ?? '0/0/0',
                            'instrument_type' => 'ST Fragmentation',
                            'Grantor' => 'Kano State Government',
                            'Grantee' => $subApp->sub_applicant,
                            'GrantorAddress' => getGrantorAddress('Sectional Titling CofO', $subApp),
                            'GranteeAddress' => getGranteeAddress($subApp),
                            'duration' => '',
                            'leasePeriod' => '',
                            'propertyDescription' => $propertyDescription,
                            'lga' => $subApp->lga,
                            'district' => $subApp->district,
                            'size' => $subApp->size,
                            'plotNumber' => $subApp->plotNumber,
                            'deeds_date' => $stFragmentationRegistration->instrumentDate ?? $subApp->created_at,
                            'solicitorName' => '',
                            'solicitorAddress' => '',
                            'status' => 'registered',
                            'land_use' => '',
                            'reg_created_by' => $subApp->reg_created_by,
                            'created_at' => $subApp->created_at,
                            'reg_creator_name' => $subApp->reg_creator_name,
                            'instrument_category' => 'ST Fragmentation',
                            'STM_Ref' => $stFragmentationRegistration->STM_Ref ?? null,
                            'original_subapp_id' => $subApp->id,
                            'registered_instrument_id' => $stFragmentationRegistration->id ? 'deed_reg_' . $stFragmentationRegistration->id : null,
                            'application_type' => $displayApplicationType,
                            'captured_date' => $subApp->created_at,
                            'reg_date' => $stFragmentationRegistration->instrumentDate ?? $subApp->created_at,
                            'reg_time' => $stFragmentationRegistration->deeds_time ? date('g:i A', strtotime($stFragmentationRegistration->deeds_time)) : (($stFragmentationRegistration->instrumentDate ?? null) ? date('g:i A', strtotime($stFragmentationRegistration->instrumentDate)) : date('g:i A', strtotime($subApp->created_at))),
                            'serial_no' => 0,
                            'page_no' => 0,
                            'volume_no' => 0,
                            'rds_exists' => (bool) ($stFragmentationRegistration->rds_exists ?? false),
                            'cor_exists' => (bool) ($stFragmentationRegistration->cor_exists ?? false),
                            'rds_exists_db' => (bool) ($stFragmentationRegistration->rds_exists ?? false),
                            'cor_exists_db' => (bool) ($stFragmentationRegistration->cor_exists ?? false),
                        ];
                    } else {
                        // Fallback to virtual record if not yet persisted (though it should be)
                        $stAssignmentRecord = (object) [
                            'id' => $subApp->id . '_st_assignment',
                            'fileno' => $subApp->fileno,
                            'parent_fileNo' => $assignmentParentFileNo,
                            'Deeds_Serial_No' => '0/0/0',
                            'instrument_type' => 'ST Fragmentation',
                            'Grantor' => 'Kano State Government',
                            'Grantee' => $subApp->sub_applicant,
                            'GrantorAddress' => getGrantorAddress('Sectional Titling CofO', $subApp),
                            'GranteeAddress' => getGranteeAddress($subApp),
                            'duration' => '',
                            'leasePeriod' => '',
                            'propertyDescription' => $propertyDescription,
                            'lga' => $subApp->lga,
                            'district' => $subApp->district,
                            'size' => $subApp->size,
                            'plotNumber' => $subApp->plotNumber,
                            'deeds_date' => $subApp->created_at,
                            'solicitorName' => '',
                            'solicitorAddress' => '',
                            'status' => 'registered', // Automatically registered (virtual)
                            'land_use' => '',
                            'reg_created_by' => $subApp->reg_created_by,
                            'created_at' => $subApp->created_at,
                            'reg_creator_name' => $subApp->reg_creator_name,
                            'instrument_category' => 'ST Fragmentation',
                            'STM_Ref' => null,
                            'original_subapp_id' => $subApp->id,
                            'registered_instrument_id' => null,
                            'application_type' => $displayApplicationType,
                            'captured_date' => $subApp->created_at,
                            'reg_date' => $subApp->created_at,
                            'reg_time' => date('g:i A', strtotime($subApp->created_at)),
                            'serial_no' => 0,
                            'page_no' => 0,
                            'volume_no' => 0,
                            'rds_exists' => false,
                            'cor_exists' => false,
                        ];
                    }
                } else {
                    // CASE 2: Normal Transfer -> ST Assignment (Transfer of Title)

                    // Create ST Assignment (Transfer of Title) record
                    $stAssignmentRecord = (object) [
                        'id' => $subApp->id . '_st_assignment',
                        'fileno' => $subApp->fileno, // fileno from subapplications table
                        'parent_fileNo' => $assignmentParentFileNo,
                        'Deeds_Serial_No' => $stRegistration->particularsRegistrationNumber ?? null,
                        'volume_no' => $stRegistration->volume_no ?? null,
                        'instrument_type' => 'ST Assignment (Transfer of Title)',
                        'Grantor' => $assignmentGrantor,
                        'Grantee' => $subApp->sub_applicant,
                        'GrantorAddress' => getGrantorAddress('ST Assignment (Transfer of Title)', $subApp),
                        'GranteeAddress' => getGranteeAddress($subApp),
                        'duration' => '',
                        'leasePeriod' => '',
                        'propertyDescription' => $propertyDescription,
                        'lga' => $subApp->lga,
                        'district' => $subApp->district,
                        'size' => $subApp->size,
                        'plotNumber' => $subApp->plotNumber,
                        'deeds_date' => $stRegistration ? $stRegistration->instrumentDate : null,
                        'solicitorName' => '',
                        'solicitorAddress' => '',
                        'status' => $stRegistration ? 'registered' : 'pending',
                        'land_use' => '',
                        'reg_created_by' => $subApp->reg_created_by,
                        'created_at' => $subApp->created_at,
                        'reg_creator_name' => $subApp->reg_creator_name,
                        'instrument_category' => 'ST Assignment',
                        'STM_Ref' => $stRegistration ? ($stRegistration->STM_Ref ?? null) : null,
                        'original_subapp_id' => $subApp->id,
                        'registered_instrument_id' => ($stRegistration && $stRegistration->id) ? 'deed_reg_' . $stRegistration->id : null,
                        'application_type' => $displayApplicationType,
                        // Add captured date and reg date fields
                        'captured_date' => $subApp->created_at, // from subapplications.created_at
                        'reg_date' => $stRegistration ? ($stRegistration->instrumentDate ?? null) : null, // from registered_instruments.instrumentDate
                        'reg_time' => ($stRegistration && $stRegistration->deeds_time) ? date('g:i A', strtotime($stRegistration->deeds_time)) : (($stRegistration && $stRegistration->instrumentDate) ? date('g:i A', strtotime($stRegistration->instrumentDate)) : null),
                        'rds_exists' => (bool) ($stRegistration ? ($stRegistration->rds_exists ?? false) : false),
                        'cor_exists' => (bool) ($stRegistration ? ($stRegistration->cor_exists ?? false) : false),
                        'rds_exists_db' => (bool) ($stRegistration ? ($stRegistration->rds_exists ?? false) : false),
                        'cor_exists_db' => (bool) ($stRegistration ? ($stRegistration->cor_exists ?? false) : false),
                    ];
                }

                // Create Sectional Titling CofO record
                $sectionalRecord = (object) [
                    'id' => $subApp->id . '_sectional_cofo',
                    'fileno' => $subApp->fileno, // Keep file number identical to ST Assignment row
                    'parent_fileNo' => $assignmentParentFileNo ?? $subApp->np_fileno, // Mirror parent reference from ST Assignment
                    'Deeds_Serial_No' => $sectionalRegistration->particularsRegistrationNumber ?? null,
                    'volume_no' => $sectionalRegistration->volume_no ?? null,
                    'instrument_type' => 'Sectional Titling CofO',
                    'Grantor' => 'Kano State Government', // Always Kano State Government for Sectional Titling CofO
                    'Grantee' => $subApp->sub_applicant,
                    'GrantorAddress' => getGrantorAddress('Sectional Titling CofO', $subApp),
                    'GranteeAddress' => getGranteeAddress($subApp),
                    'duration' => '',
                    'leasePeriod' => '',
                    'propertyDescription' => $propertyDescription,
                    'lga' => $subApp->lga,
                    'district' => $subApp->district,
                    'size' => $subApp->size,
                    'plotNumber' => $subApp->plotNumber,
                    'deeds_date' => $sectionalRegistration ? $sectionalRegistration->instrumentDate : null,
                    'solicitorName' => '',
                    'solicitorAddress' => '',
                    'status' => $sectionalRegistration ? 'registered' : 'pending',
                    'land_use' => '',
                    'reg_created_by' => $subApp->reg_created_by,
                    'created_at' => $subApp->created_at,
                    'reg_creator_name' => $subApp->reg_creator_name,
                    'instrument_category' => 'Sectional Titling',
                    'STM_Ref' => $sectionalRegistration ? ($sectionalRegistration->STM_Ref ?? null) : null,
                    'original_subapp_id' => $subApp->id,
                    'registered_instrument_id' => ($sectionalRegistration && $sectionalRegistration->id) ? 'deed_reg_' . $sectionalRegistration->id : null,
                    'application_type' => $displayApplicationType,
                    // Add captured date and reg date fields
                    'captured_date' => $subApp->created_at, // from subapplications.created_at
                    'reg_date' => $sectionalRegistration ? ($sectionalRegistration->instrumentDate ?? null) : null, // from registered_instruments.instrumentDate
                    'reg_time' => ($sectionalRegistration && $sectionalRegistration->deeds_time) ? date('g:i A', strtotime($sectionalRegistration->deeds_time)) : (($sectionalRegistration && $sectionalRegistration->instrumentDate) ? date('g:i A', strtotime($sectionalRegistration->instrumentDate)) : null),
                    'rds_exists' => (bool) ($sectionalRegistration ? ($sectionalRegistration->rds_exists ?? false) : false),
                    'cor_exists' => (bool) ($sectionalRegistration ? ($sectionalRegistration->cor_exists ?? false) : false),
                    'rds_exists_db' => (bool) ($sectionalRegistration ? ($sectionalRegistration->rds_exists ?? false) : false),
                    'cor_exists_db' => (bool) ($sectionalRegistration ? ($sectionalRegistration->cor_exists ?? false) : false),
                ];

                $allInstruments->push($stAssignmentRecord);
                $allInstruments->push($sectionalRecord);
            }

            // Add ST Fragmentation records from approved mother applications
            foreach ($approvedMotherApplications as $motherApp) {
                // Check if ST Fragmentation is already registered in deed_registrations
                $stFragmentationRegistration = DB::connection('sqlsrv')->table('deed_registrations')
                    ->where('fileno', $motherApp->fileno)
                    ->where('instrument_type', 'ST Fragmentation')
                    ->where('status', 'registered')
                    ->select('*', 'registration_number as particularsRegistrationNumber', 'deeds_date as instrumentDate')
                    ->first();

                if ($stFragmentationRegistration && !empty($stFragmentationRegistration->data)) {
                    $stFragmentationRegistration->STM_Ref = json_decode($stFragmentationRegistration->data)->stm_ref ?? null;
                }

                // Build property description for ST Fragmentation
                $propertyDescription = '';
                $propertyParts = [];

                if (!empty($motherApp->property_house_no)) {
                    $propertyParts[] = 'House No: ' . $motherApp->property_house_no;
                }
                if (!empty($motherApp->plotNumber)) {
                    $propertyParts[] = 'Plot No: ' . $motherApp->plotNumber;
                }
                if (!empty($motherApp->property_street_name)) {
                    $propertyParts[] = $motherApp->property_street_name;
                }
                if (!empty($motherApp->district)) {
                    $propertyParts[] = $motherApp->district;
                }
                if (!empty($motherApp->lga)) {
                    $propertyParts[] = $motherApp->lga;
                }

                $propertyDescription = implode(', ', $propertyParts);
                if (empty($propertyDescription)) {
                    $propertyDescription = 'Property details not available';
                }

                // Build mother applicant name properly for ST Fragmentation Grantee
                $motherApplicantName = '';
                $motherApplicantParts = [];

                if (!empty($motherApp->applicant_title)) {
                    $motherApplicantParts[] = $motherApp->applicant_title;
                }
                if (!empty($motherApp->first_name)) {
                    $motherApplicantParts[] = $motherApp->first_name;
                }
                if (!empty($motherApp->middle_name)) {
                    $motherApplicantParts[] = $motherApp->middle_name;
                }
                if (!empty($motherApp->surname)) {
                    $motherApplicantParts[] = $motherApp->surname;
                }
                if (!empty($motherApp->corporate_name)) {
                    $motherApplicantParts[] = $motherApp->corporate_name;
                }
                if (!empty($motherApp->rc_number)) {
                    $motherApplicantParts[] = $motherApp->rc_number;
                }
                if (!empty($motherApp->multiple_owners_names)) {
                    $motherApplicantParts[] = $motherApp->multiple_owners_names;
                }

                $motherApplicantName = implode(' ', $motherApplicantParts);
                if (empty($motherApplicantName)) {
                    $motherApplicantName = $motherApp->mother_applicant ?? 'N/A';
                }

                // Create ST Fragmentation record
                $stFragmentationRecord = (object) [
                    'id' => $motherApp->id . '_st_fragmentation',
                    'fileno' => $motherApp->np_fileno ?? $motherApp->fileno, // np_fileno from mother_applications table as fileNo
                    'parent_fileNo' => $motherApp->fileno, // fileno from mother_applications table as parent_fileNo
                    'Deeds_Serial_No' => $stFragmentationRegistration->particularsRegistrationNumber ?? null,
                    'instrument_type' => 'ST Fragmentation',
                    'Grantor' => 'Kano State Government', // As specified in requirements
                    'Grantee' => $motherApplicantName, // Use properly built mother applicant name
                    'GrantorAddress' => getGrantorAddress('Sectional Titling CofO', $motherApp),
                    'GranteeAddress' => getGranteeAddress($motherApp),
                    'duration' => '',
                    'leasePeriod' => '',
                    'propertyDescription' => $propertyDescription,
                    'lga' => $motherApp->lga,
                    'district' => $motherApp->district,
                    'size' => $motherApp->size,
                    'plotNumber' => $motherApp->plotNumber,
                    'deeds_date' => $stFragmentationRegistration ? $stFragmentationRegistration->instrumentDate : null,
                    'solicitorName' => '',
                    'solicitorAddress' => '',
                    'status' => $stFragmentationRegistration ? 'registered' : 'pending',
                    'land_use' => '',
                    'reg_created_by' => $motherApp->reg_created_by,
                    'created_at' => $motherApp->created_at,
                    'reg_creator_name' => $motherApp->reg_creator_name,
                    'instrument_category' => 'ST Fragmentation',
                    'STM_Ref' => $stFragmentationRegistration ? ($stFragmentationRegistration->STM_Ref ?? null) : null,
                    'original_mother_app_id' => $motherApp->id,
                    'registered_instrument_id' => ($stFragmentationRegistration && $stFragmentationRegistration->id) ? 'deed_reg_' . $stFragmentationRegistration->id : null,
                    'application_type' => 'Primary',
                    // Add captured date and reg date fields
                    'captured_date' => $motherApp->created_at, // from mother_applications.created_at
                    'reg_date' => $stFragmentationRegistration ? ($stFragmentationRegistration->instrumentDate ?? null) : null, // from registered_instruments.instrumentDate
                    'reg_time' => ($stFragmentationRegistration && $stFragmentationRegistration->deeds_time) ? date('g:i A', strtotime($stFragmentationRegistration->deeds_time)) : (($stFragmentationRegistration && $stFragmentationRegistration->instrumentDate) ? date('g:i A', strtotime($stFragmentationRegistration->instrumentDate)) : null),
                    'rds_exists' => (bool) ($stFragmentationRegistration ? ($stFragmentationRegistration->rds_exists ?? false) : false),
                    'cor_exists' => (bool) ($stFragmentationRegistration ? ($stFragmentationRegistration->cor_exists ?? false) : false),
                    'rds_exists_db' => (bool) ($stFragmentationRegistration ? ($stFragmentationRegistration->rds_exists ?? false) : false),
                    'cor_exists_db' => (bool) ($stFragmentationRegistration ? ($stFragmentationRegistration->cor_exists ?? false) : false),
                ];

                $allInstruments->push($stFragmentationRecord);
            }

            // Add registered instruments from deed_registrations that are NOT ST-related
            // These would be instruments registered from instrument_capture or instrument_registration
            $registeredOtherInstrumentsQuery = DB::connection('sqlsrv')->table('deed_registrations')
                ->leftJoin('users', 'deed_registrations.created_by', '=', 'users.id')
                ->leftJoin('instrument_capture', function($join) {
                    $join->on('deed_registrations.fileno', '=', 'instrument_capture.mlsFNo')
                        ->orOn('deed_registrations.fileno', '=', 'instrument_capture.temp_fileno')
                        ->orOn('deed_registrations.fileno', '=', 'instrument_capture.kangisFileNo')
                        ->orOn('deed_registrations.fileno', '=', 'instrument_capture.NewKANGISFileno');
                })
                ->whereNotIn('deed_registrations.instrument_type', [
                    'ST Assignment (Transfer of Title)',
                    'Sectional Titling CofO',
                    'ST Fragmentation',
                    // Land Registration shares this table with Digitization and
                    // is separated only by classification, so it has to be
                    // excluded here or it would surface on the Digitization
                    // screen as well as its own register.
                    config('land_registration.instrument_type'),
                ]);

            if ($isStDeeds) {
                $registeredOtherInstrumentsQuery->where('deed_registrations.fileno', 'like', 'ST-%');
            }

            $registeredOtherInstruments = $registeredOtherInstrumentsQuery->select(
                'deed_registrations.*',
                'deed_registrations.rds_exists as rds_exists_db',
                'deed_registrations.cor_exists as cor_exists_db',
                'instrument_capture.op_type as captured_op_type',
                DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
            )
            ->get();

            foreach ($registeredOtherInstruments as $reg) {
                $instrumentRecord = (object) [
                    'id' => 'deed_reg_' . $reg->id,
                    'fileno' => $reg->fileno,
                    'parent_fileNo' => $reg->parent_fileno,
                    'Deeds_Serial_No' => $reg->registration_number,
                    'volume_no' => $reg->volume_no ?? null,
                    'instrument_type' => $reg->instrument_type,
                    'Grantor' => $reg->grantor ?? 'N/A',
                    'Grantee' => $reg->grantee ?? 'N/A',
                    'GrantorAddress' => '',
                    'GranteeAddress' => '',
                    'duration' => '',
                    'leasePeriod' => '',
                    'propertyDescription' => $reg->property_description ?? '',
                    'lga' => $reg->lga ?? '',
                    'district' => $reg->district ?? '',
                    'size' => $reg->size ?? '',
                    'plotNumber' => $reg->plot_number ?? '',
                    'deeds_date' => $reg->deeds_date,
                    'solicitorName' => '',
                    'solicitorAddress' => '',
                    'status' => 'registered',
                    'land_use' => '',
                    'reg_created_by' => $reg->created_by,
                    'created_at' => $reg->created_at,
                    'reg_creator_name' => $reg->reg_creator_name ?? '',
                    'instrument_category' => 'Deeds',
                    'STM_Ref' => null,
                    'original_instrument_id' => null,
                    'registered_instrument_id' => 'deed_reg_' . $reg->id,
                    'application_type' => 'Deed',
                    'captured_date' => $reg->created_at ?? $reg->instrument_date, // Prefer system capture time
                    'reg_date' => $reg->deeds_date,
                    'reg_time' => $reg->deeds_time ? date('g:i A', strtotime($reg->deeds_time)) : ($reg->deeds_date ? date('g:i A', strtotime($reg->deeds_date)) : null),
                    'rds_exists' => (bool) ($reg->rds_exists ?? false),
                    'cor_exists' => (bool) ($reg->cor_exists ?? false),
                    'rds_exists_db' => (bool) ($reg->rds_exists ?? false),
                    'cor_exists_db' => (bool) ($reg->cor_exists ?? false),
                    'prop_id' => $reg->prop_id ?? null,
                    'op_type' => $reg->captured_op_type ?? null,
                ];

                // Normalize op_type if it's an OP
                if ($instrumentRecord->instrument_type === 'Occupancy Permit (OP)' && $instrumentRecord->op_type) {
                    if (!str_starts_with($instrumentRecord->op_type, 'OP ')) {
                        $instrumentRecord->op_type = 'OP ' . $instrumentRecord->op_type;
                    }
                }

                // Try to get more info from data column
                if (!empty($reg->data)) {
                    $extraData = json_decode($reg->data);
                    if ($extraData) {
                        $instrumentRecord->STM_Ref = $extraData->stm_ref ?? null;
                        $instrumentRecord->GrantorAddress = $extraData->GrantorAddress ?? $extraData->Grantor_Address ?? '';
                        $instrumentRecord->GranteeAddress = $extraData->GranteeAddress ?? $extraData->Grantee_Address ?? '';
                        if (isset($extraData->op_type) && !empty($extraData->op_type)) {
                            $instrumentRecord->op_type = $extraData->op_type;
                            // Re-normalize if updated from extraData
                            if (!str_starts_with($instrumentRecord->op_type, 'OP ')) {
                                $instrumentRecord->op_type = 'OP ' . $instrumentRecord->op_type;
                            }
                        }
                    }
                }

                $allInstruments->push($instrumentRecord);
            }

            Log::info('Instrument Registration data loaded', [
                'approved_subapplications' => $approvedSubapplications->count(),
                'approved_mother_applications' => $approvedMotherApplications->count(),
                'total_instruments' => $allInstruments->count(),
                'st_assignment_count' => $allInstruments->where('instrument_type', 'ST Assignment (Transfer of Title)')->count(),
                'sectional_titling_count' => $allInstruments->where('instrument_type', 'Sectional Titling CofO')->count(),
                'st_fragmentation_count' => $allInstruments->where('instrument_type', 'ST Fragmentation')->count()
            ]);

            // Count statuses
            $pendingCount = $allInstruments->where('status', 'pending')->count();
            $registeredCount = $allInstruments->where('status', 'registered')->count();
            $rejectedCount = 0; // No rejected status in this context
            $totalCount = $allInstruments->count();

            // ST Deeds view: registered and pending instruments broken down by application type (PUA / SUA / Primary)
            $registeredByAppType = ['PUA' => 0, 'SUA' => 0, 'Primary' => 0];
            $pendingByAppType = ['PUA' => 0, 'SUA' => 0, 'Primary' => 0];
            $pendingByInstrumentType = ['Assignment' => 0, 'Fragmentation' => 0, 'CofO' => 0];
            if ($isStDeeds) {
                $countByAppType = function ($instruments) {
                    $counts = ['PUA' => 0, 'SUA' => 0, 'Primary' => 0];
                    foreach ($instruments as $i) {
                        $type = strtoupper(trim($i->application_type ?? ''));
                        if ($type === 'PUA') {
                            $counts['PUA']++;
                        } elseif ($type === 'SUA') {
                            $counts['SUA']++;
                        } elseif ($type === 'PRIMARY') {
                            $counts['Primary']++;
                        }
                    }
                    return $counts;
                };
                $registeredByAppType = $countByAppType($allInstruments->where('status', 'registered'));
                $pendingByAppType = $countByAppType($allInstruments->where('status', 'pending'));

                // Pending broken down by ST instrument type
                foreach ($allInstruments->where('status', 'pending') as $i) {
                    switch ($i->instrument_type ?? '') {
                        case 'ST Assignment (Transfer of Title)':
                            $pendingByInstrumentType['Assignment']++;
                            break;
                        case 'ST Fragmentation':
                            $pendingByInstrumentType['Fragmentation']++;
                            break;
                        case 'Sectional Titling CofO':
                            $pendingByInstrumentType['CofO']++;
                            break;
                    }
                }
            }

            // Process property descriptions and durations
            foreach ($allInstruments as $application) {
                if (empty($application->propertyDescription)) {
                    $application->property_description =
                        (!empty($application->district) ? $application->district . ', ' : '') .
                        (!empty($application->lga) ? $application->lga . ', ' : '') .
                        (!empty($application->state) ? $application->state : '');
                } else {
                    $application->property_description = $application->propertyDescription;
                }

                $application->duration = $application->duration ?? $application->leasePeriod ?? 'N/A';
            }

            Log::info('Final instrument counts', [
                'total_count' => $totalCount,
                'pending_count' => $pendingCount,
                'registered_count' => $registeredCount,
                'rejected_count' => $rejectedCount,
            ]);

            // Build grouped ST structure for the grouped view
            $stGroups = $this->buildSTGroups($approvedSubapplications, $approvedMotherApplications);

            // Sort by captured_date descending THEN reg_date descending
            // For ST Deeds, prioritize registered instruments first
            if ($isStDeeds) {
                $allInstrumentsSorted = $allInstruments
                    ->sortBy(function ($item) {
                        return $item->status === 'registered' ? 0 : 1;
                    })
                    ->sortByDesc(function ($item) {
                        return $item->captured_date ?? '';
                    })
                    ->sortByDesc(function ($item) {
                        return $item->reg_date ?? '';
                    });
            } else {
                $allInstrumentsSorted = $allInstruments->sortByDesc(function ($item) {
                    return $item->captured_date ?? '';
                })->sortByDesc(function ($item) {
                    return $item->reg_date ?? '';
                });
            }

            // Limit initial view load to prevent DOM overload, but keep full data for JS
            $approvedApplications = $allInstrumentsSorted->take(50);

            // Pass full dataset for JS functionality
            $fullDataForJs = $allInstrumentsSorted->values();

            // PropID for every row, not just the first page: updateTable()
            // re-renders the table client-side out of this same set, so
            // resolving only the 50 rendered here would leave page 2 blank.
            // Only instrument_capture and deed_registrations keep the column,
            // so ST rows are resolved from PropID_Master in batches.
            $this->attachPropIds($fullDataForJs);

            // Bulk check for RDS and CoR existence to avoid N+1 issues
            $allStmRefs = $approvedApplications->pluck('STM_Ref')->filter()->unique()->toArray();

            $existingRdsRefs = [];
            if (!empty($allStmRefs)) {
                $existingRdsRefs = DB::connection('sqlsrv')->table('rds_tracking')
                    ->whereIn('stm_ref', $allStmRefs)
                    ->pluck('stm_ref')
                    ->toArray();
            }

            $existingCorRefs = [];
            if (!empty($allStmRefs)) {
                // Check in both SectionalCofOReg and Sectional_title_transfer
                $cofoRefs = DB::connection('sqlsrv')->table('SectionalCofOReg')
                    ->whereIn('STM_Ref', $allStmRefs)
                    ->pluck('STM_Ref')
                    ->toArray();

                $transferRefs = DB::connection('sqlsrv')->table('Sectional_title_transfer')
                    ->whereIn('STM_Ref', $allStmRefs)
                    ->pluck('STM_Ref')
                    ->toArray();

                $existingCorRefs = array_unique(array_merge($cofoRefs, $transferRefs));
            }

            foreach ($approvedApplications as $app) {
                // Determine if record already shows as existing in DB
                $dbRds = isset($app->rds_exists_db) ? (bool) $app->rds_exists_db : (bool) ($app->rds_exists ?? false);
                $dbCor = isset($app->cor_exists_db) ? (bool) $app->cor_exists_db : (bool) ($app->cor_exists ?? false);

                // Combine with fallback checks (unify flags)
                $app->rds_exists = $dbRds || (!empty($app->STM_Ref) && in_array($app->STM_Ref, $existingRdsRefs));
                $app->cor_exists = $dbCor || (!empty($app->STM_Ref) && in_array($app->STM_Ref, $existingCorRefs));
            }

            $instrumentTypesList = \App\Models\NewInstrumentType::orderBy('name')->pluck('name')->toArray();
            $dataOpTypes = $allInstruments->where('instrument_type', 'Occupancy Permit (OP)')
                ->pluck('op_type')
                ->filter()
                ->unique()
                ->map(function($t) { 
                    return str_starts_with($t, 'OP ') ? $t : 'OP ' . $t; 
                })
                ->toArray();
            
            $instrumentTypes = array_unique(array_merge($instrumentTypesList, $dataOpTypes));
            sort($instrumentTypes);

            if ($isStDeeds) {
                $instrumentTypes = array_values(array_filter($instrumentTypes, function ($t) {
                    return in_array($t, [
                        'ST Assignment (Transfer of Title)',
                        'ST Fragmentation',
                        'Sectional Titling CofO'
                    ]);
                }));
            }

            return view('instrument_registration.index', compact(
                'approvedApplications',
                'PageTitle',
                'PageDescription',
                'pendingCount',
                'registeredCount',
                'rejectedCount',
                'totalCount',
                'registeredByAppType',
                'pendingByAppType',
                'pendingByInstrumentType',
                'instrumentTypes',
                'fullDataForJs',
                'isStDeeds',
                'stGroups'
            ));

        } catch (\Exception $e) {
            Log::error('Error in InstrumentRegistration method', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $approvedApplications = collect();
            $fullDataForJs = collect();
            $stGroups = collect();
            $pendingCount = $registeredCount = $rejectedCount = $totalCount = 0;
            $registeredByAppType = ['PUA' => 0, 'SUA' => 0, 'Primary' => 0];
            $pendingByAppType = ['PUA' => 0, 'SUA' => 0, 'Primary' => 0];
            $pendingByInstrumentType = ['Assignment' => 0, 'Fragmentation' => 0, 'CofO' => 0];
            $instrumentTypes = collect();

            return view('instrument_registration.index', compact(
                'approvedApplications',
                'PageTitle',
                'PageDescription',
                'pendingCount',
                'registeredCount',
                'rejectedCount',
                'totalCount',
                'registeredByAppType',
                'pendingByAppType',
                'pendingByInstrumentType',
                'instrumentTypes',
                'fullDataForJs',
                'isStDeeds',
                'stGroups'
            ))->with('error', 'Error loading instrument data: ' . $e->getMessage());
        }
    }

    public function view($id)
    {
        $PageTitle = 'View Instrument Registration';
        $PageDescription = '';

        try {
            $application = null;

            // Handle composite IDs for ST Assignment and Sectional Titling
            if (strpos($id, '_st_assignment') !== false || strpos($id, '_sectional_cofo') !== false) {
                $originalId = str_replace(['_st_assignment', '_sectional_cofo'], '', $id);
                $instrumentType = strpos($id, '_st_assignment') !== false ? 'ST Assignment (Transfer of Title)' : 'Sectional Titling CofO';

                // Get the subapplication details
                $subApplication = DB::connection('sqlsrv')->table('subapplications as s')
                    ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                    ->leftJoin('users', 's.created_by', '=', 'users.id')
                    ->where('s.id', $originalId)
                    ->select(
                        's.*',
                        DB::raw("CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.surname,''), COALESCE(s.corporate_name,''), COALESCE(s.multiple_owners_names,'')) as sub_applicant"),
                        'm.property_lga as lga',
                        'm.property_district as district',
                        'm.plot_size as size',
                        'm.property_plot_no as plotNumber',
                        'm.np_fileno', // Include np_fileno for parent_fileNo
                        DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                    )
                    ->first();

                if (!$subApplication) {
                    Log::error('Subapplication not found', ['id' => $originalId]);
                    return redirect()->route('instrument_registration.index')->with('error', 'Instrument not found');
                }

                // Check if this instrument is registered and get registration details
                $registeredInstrument = DB::connection('sqlsrv')->table('registered_instruments')
                    ->leftJoin('users', 'registered_instruments.created_by', '=', 'users.id')
                    ->where('registered_instruments.StFileNo', $subApplication->fileno)
                    ->where('registered_instruments.instrument_type', $instrumentType)
                    ->where('registered_instruments.status', 'registered')
                    ->select(
                        'registered_instruments.*',
                        DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                    )
                    ->first();

                // Fetch mother application prop_id for timeline
                $motherPropId = null;
                if (!empty($subApplication->main_application_id)) {
                    $motherApp = DB::connection('sqlsrv')->table('mother_applications')
                        ->where('id', $subApplication->main_application_id)
                        ->value('prop_id');
                    $motherPropId = $motherApp ?? null;
                }

                // Create a combined application object
                $application = (object) [
                    'id' => $id,
                    'prop_id' => $motherPropId,
                    'fileno' => $subApplication->fileno,
                    'parent_fileNo' => $subApplication->np_fileno, // Include parent_fileNo
                    'instrument_type' => $instrumentType,
                    'Grantor' => $subApplication->sub_applicant,
                    'Grantee' => $subApplication->sub_applicant,
                    'Applicant_Name' => $subApplication->sub_applicant,
                    'lga' => $subApplication->lga,
                    'district' => $subApplication->district,
                    'size' => $subApplication->size,
                    'plotNumber' => $subApplication->plotNumber,
                    'reg_creator_name' => $subApplication->reg_creator_name,
                    'created_at' => $subApplication->created_at,
                    'updated_at' => $subApplication->updated_at ?? $subApplication->created_at,
                    'source_type' => 'subapplication',
                    // Registration details if available
                    'particularsRegistrationNumber' => $registeredInstrument->particularsRegistrationNumber ?? null,
                    'Deeds_Serial_No' => $registeredInstrument->particularsRegistrationNumber ?? null,
                    'STM_Ref' => $registeredInstrument->STM_Ref ?? null,
                    'instrumentDate' => $registeredInstrument->instrumentDate ?? null,
                    'deeds_date' => $registeredInstrument->deeds_date ?? $registeredInstrument->instrumentDate ?? null,
                    'deeds_time' => $registeredInstrument->deeds_time ?? null,
                    'status' => $registeredInstrument ? 'registered' : 'pending',
                    'reg_status' => $registeredInstrument ? 'registered' : 'pending',
                    'propertyDescription' => $registeredInstrument->propertyDescription ?? '',
                    'GrantorAddress' => $registeredInstrument->GrantorAddress ?? '',
                    'GranteeAddress' => $registeredInstrument->GranteeAddress ?? '',
                    'duration' => $registeredInstrument->duration ?? '',
                    'solicitorName' => $registeredInstrument->solicitorName ?? '',
                    'solicitorAddress' => $registeredInstrument->solicitorAddress ?? '',
                    // Additional properties that might be referenced in the view
                    'Tenure_Period' => $registeredInstrument->Tenure_Period ?? null,
                    'serial_no' => $registeredInstrument->serial_no ?? null,
                    'page_no' => $registeredInstrument->page_no ?? null,
                    'reg_page_no' => $registeredInstrument->page_no ?? null,
                    'volume_no' => $registeredInstrument->volume_no ?? null,
                    'Occupation' => $subApplication->occupation ?? null,
                    'NoOfUnits' => null,
                    'NoOfBlocks' => null,
                    'NoOfSections' => null,
                    'property_street_name' => null,
                    'property_district' => $subApplication->district,
                    'property_lga' => $subApplication->lga,
                    'land_use' => null,
                    'commercial_type' => null,
                    'industrial_type' => null,
                    'residential_type' => null
                ];

            } else {
                // Regular registered instrument ID
                $application = DB::connection('sqlsrv')
                    ->table('registered_instruments')
                    ->leftJoin('users', 'registered_instruments.created_by', '=', 'users.id')
                    ->select(
                        'registered_instruments.*',
                        DB::raw("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as reg_creator_name")
                    )
                    ->where('registered_instruments.id', $id)
                    ->first();

                if ($application) {
                    $application->source_type = 'registered_instruments';
                    $application->prop_id = $application->prop_id ?? null;
                    $application->fileno = $application->MLSFileNo ?? $application->KAGISFileNO ?? $application->NewKANGISFileNo ?? $application->StFileNo;
                    // Ensure all required properties exist
                    $application->Deeds_Serial_No = $application->particularsRegistrationNumber ?? null;
                    $application->reg_status = $application->status ?? 'pending';
                    $application->Applicant_Name = $application->Grantor ?? $application->Grantee ?? 'N/A';
                    $application->reg_page_no = $application->page_no ?? null;
                    $application->property_district = $application->district ?? null;
                    $application->property_lga = $application->lga ?? null;
                    // Set default values for properties that might not exist
                    $application->Tenure_Period = $application->Tenure_Period ?? null;
                    $application->Occupation = $application->Occupation ?? null;
                    $application->NoOfUnits = $application->NoOfUnits ?? null;
                    $application->NoOfBlocks = $application->NoOfBlocks ?? null;
                    $application->NoOfSections = $application->NoOfSections ?? null;
                    $application->property_street_name = $application->property_street_name ?? null;
                    $application->land_use = $application->land_use ?? null;
                    $application->commercial_type = $application->commercial_type ?? null;
                    $application->industrial_type = $application->industrial_type ?? null;
                    $application->residential_type = $application->residential_type ?? null;
                }
            }

            if (!$application) {
                Log::error('Instrument not found', ['id' => $id]);
                return redirect()->route('instrument_registration.index')->with('error', 'Instrument not found');
            }

            return view('instrument_registration.view', compact('application', 'PageTitle', 'PageDescription'));
        } catch (\Exception $e) {
            Log::error('Error in view method', [
                'id' => $id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('instrument_registration.index')
                ->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function registerSingle(Request $request)
    {
        try {
            // Validate ST Assignment and Sectional Titling CofO requirements
            $instrumentType = $request->instrument_type;
            if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                $request->validate([
                    'instrument_type' => 'required|string',
                    'file_no' => 'required|string',
                ], [
                    'instrument_type.required' => 'Instrument type is required for ST Assignment and Sectional Titling CofO',
                    'file_no.required' => 'File number (StFileNo) is required for ST Assignment and Sectional Titling CofO',
                ]);

                // Check duplicates in NEW deed_registrations table
                $fileNo = $request->file_no;
                $existingRegistrations = DB::connection('sqlsrv')->table('deed_registrations')
                    ->where('fileno', $fileNo)
                    ->whereIn('instrument_type', ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])
                    ->pluck('instrument_type')
                    ->toArray();

                if (in_array($instrumentType, $existingRegistrations)) {
                    return response()->json([
                        'success' => false,
                        'error' => "A {$instrumentType} registration already exists for file number {$fileNo}"
                    ], 422);
                }

                Log::info('ST/Sectional Titling registration attempt', [
                    'file_no' => $fileNo,
                    'instrument_type' => $instrumentType,
                    'existing_registrations' => $existingRegistrations
                ]);
            }

            $applicationId = $request->mother_application_id;
            $sourceRecord = null;
            $sourceTable = null;

            // Handle composite IDs
            if (strpos($applicationId, '_st_assignment') !== false || strpos($applicationId, '_sectional_cofo') !== false) {
                $originalId = str_replace(['_st_assignment', '_sectional_cofo'], '', $applicationId);
                $sourceRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $originalId)->first();
                if ($sourceRecord) {
                    $sourceTable = 'subapplications';
                    $sourceRecord->original_id = $originalId;
                }
            } elseif (strpos($applicationId, 'instr_reg_') === 0) {
                $originalId = str_replace('instr_reg_', '', $applicationId);
                $sourceRecord = DB::connection('sqlsrv')->table('instrument_registration')->where('id', $originalId)->first();
                if ($sourceRecord) {
                    $sourceTable = 'instrument_registration';
                }
            } elseif (strpos($applicationId, 'instr_capture_') === 0) {
                $originalId = str_replace('instr_capture_', '', $applicationId);
                $sourceRecord = DB::connection('sqlsrv')->table('instrument_capture')->where('id', $originalId)->first();
                if ($sourceRecord) {
                    $sourceTable = 'instrument_capture';
                    // For proper update logic
                    $sourceRecord->original_id = $originalId;
                }
            } else {
                if (is_numeric($applicationId)) {
                    $sourceRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $applicationId)->first();
                    if ($sourceRecord) {
                        $sourceTable = 'subapplications';
                    }
                }

                // Both id columns are ints. A composite id that reached here
                // unrecognised (e.g. "mother_assign_1028") would otherwise be
                // compared against them and surface as a raw SQL conversion error.
                if (!isset($sourceRecord) && is_numeric($applicationId)) {
                    $sourceRecord = DB::connection('sqlsrv')->table('instrument_registration')->where('id', $applicationId)->first();
                    if ($sourceRecord) {
                        $sourceTable = 'instrument_registration';
                    } else {
                        $sourceRecord = DB::connection('sqlsrv')->table('mother_applications')->where('id', $applicationId)->first();
                        if ($sourceRecord) {
                            $sourceTable = 'mother_applications';
                        }
                    }
                }
            }

            if (!$sourceRecord) {
                return response()->json(['success' => false, 'error' => 'Source record not found in any table'], 404);
            }

            // check if already registered
            if ($sourceTable === 'instrument_registration' && isset($sourceRecord->status) && $sourceRecord->status === 'registered') {
                return response()->json(['success' => false, 'error' => 'This instrument is already registered.'], 422);
            }
            if ($sourceTable === 'instrument_capture' && isset($sourceRecord->is_deed_registered) && $sourceRecord->is_deed_registered == 1) {
                return response()->json(['success' => false, 'error' => 'This instrument is already registered.'], 422);
            }
            if ($sourceTable === 'mother_applications' && isset($sourceRecord->deeds_status) && $sourceRecord->deeds_status === 'registered' && !in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                return response()->json(['success' => false, 'error' => 'This instrument is already registered.'], 422);
            }

            // Dummy serial data (Service generates real numbers)
            $serialData = ['serial_no' => 0, 'page_no' => 0, 'volume_no' => 0, 'deeds_serial_no' => 'PENDING'];
            $stmReference = 'STM/' . date('Y') . '/' . mt_rand(1000, 9999);

            $preparedData = $this->prepareRegistrationData($sourceRecord, $sourceTable, $request, $serialData, $stmReference);

            $updateId = isset($sourceRecord->original_id) ? $sourceRecord->original_id : $applicationId;
            if ($sourceTable === 'instrument_registration' && strpos($updateId, 'instr_reg_') === 0) {
                $updateId = str_replace('instr_reg_', '', $updateId);
            }

            // Map to Service format
            $serviceData = [
                'instrument_capture_id' => ($sourceTable === 'instrument_capture' ? $updateId : null),
                'fileno' => $preparedData['MLSFileNo'] ?? $preparedData['fileNo'] ?? '',
                'parent_fileno' => $preparedData['parent_fileNo'] ?? null,
                'instrument_type' => $preparedData['instrument_type'],
                'grantor' => $preparedData['Grantor'],
                'grantee' => $preparedData['Grantee'],
                'lga' => $preparedData['lga'] ?? null,
                'district' => $preparedData['district'] ?? null,
                'plot_number' => $preparedData['plotNumber'] ?? null,
                'size' => $preparedData['size'] ?? null,
                'property_description' => $preparedData['propertyDescription'] ?? null,
                'op_type' => $preparedData['op_type'] ?? null,
                'extra_data' => $preparedData
            ];

            // Use Service to Register
            $newId = $this->instrumentService->registerInstrument($serviceData);

            // Fetch back the actual number for response
            $newRecord = DB::connection('sqlsrv')->table('deed_registrations')->where('id', $newId)->first();
            if ($newRecord) {
                $serialData = [
                    'serial_no' => $newRecord->serial_no,
                    'page_no' => $newRecord->page_no,
                    'volume_no' => $newRecord->volume_no,
                    'deeds_serial_no' => $newRecord->registration_number
                ];
            }

            $this->updateSourceRecordStatus($updateId, $sourceTable);

            if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO']) && $sourceTable === 'subapplications') {
                $this->updateInstrumentCompletionStatus($updateId, $instrumentType, 'Registered');
            }

            if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                $this->checkBothTypesRegistered($request->file_no ?? $sourceRecord->fileno);
            }

            /*
             | Tell the party their instrument is registered.
             |
             | Only once $newRecord has been read back: the registration number,
             | serial, page and volume are minted inside the service (from the
             | instrument's own number vault), so composing the message from the
             | request would quote numbers that do not exist.
             */
            $this->sendDeedRegisteredSms($newRecord, $request);

            return response()->json([
                'success' => true,
                'message' => 'Instrument registered successfully',
                'serial_data' => $serialData,
                'stm_ref' => $stmReference,
                'record_id' => $newId,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in registerSingle', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'error' => 'Failed to register: ' . $e->getMessage()], 500);
        }
    }

    public function registerSingleLegacy(Request $request)
    {
        try {
            // Validate ST Assignment and Sectional Titling CofO requirements
            $instrumentType = $request->instrument_type;
            if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                // For these instrument types, we need to ensure both StFileNo and instrument type are properly validated
                $request->validate([
                    'instrument_type' => 'required|string',
                    'file_no' => 'required|string', // This will be used as StFileNo
                ], [
                    'instrument_type.required' => 'Instrument type is required for ST Assignment and Sectional Titling CofO',
                    'file_no.required' => 'File number (StFileNo) is required for ST Assignment and Sectional Titling CofO',
                ]);

                // Additional validation to ensure both types are registered for each application
                $fileNo = $request->file_no;
                $existingRegistrations = DB::connection('sqlsrv')->table('registered_instruments')
                    ->where('StFileNo', $fileNo)
                    ->whereIn('instrument_type', ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])
                    ->pluck('instrument_type')
                    ->toArray();

                // Check if we're trying to register the same type twice for the same file
                if (in_array($instrumentType, $existingRegistrations)) {
                    return response()->json([
                        'success' => false,
                        'error' => "A {$instrumentType} registration already exists for file number {$fileNo}"
                    ], 422);
                }

                // Log the registration attempt for tracking
                Log::info('ST/Sectional Titling registration attempt', [
                    'file_no' => $fileNo,
                    'instrument_type' => $instrumentType,
                    'existing_registrations' => $existingRegistrations
                ]);
            }

            $applicationId = $request->mother_application_id;
            $sourceRecord = null;
            $sourceTable = null;

            // Handle composite IDs for ST Assignment and Sectional Titling
            if (strpos($applicationId, '_st_assignment') !== false || strpos($applicationId, '_sectional_cofo') !== false) {
                $originalId = str_replace(['_st_assignment', '_sectional_cofo'], '', $applicationId);
                $sourceRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $originalId)->first();
                if ($sourceRecord) {
                    $sourceTable = 'subapplications';
                    // Add the original ID for proper status update
                    $sourceRecord->original_id = $originalId;
                }
            } elseif (strpos($applicationId, 'instr_reg_') === 0) {
                // Handle instrument_registration IDs that start with 'instr_reg_'
                $originalId = str_replace('instr_reg_', '', $applicationId);
                $sourceRecord = DB::connection('sqlsrv')->table('instrument_registration')->where('id', $originalId)->first();
                if ($sourceRecord) {
                    $sourceTable = 'instrument_registration';
                }
            } else {
                // Only check subapplications if the ID is numeric
                if (is_numeric($applicationId)) {
                    $sourceRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $applicationId)->first();
                    if ($sourceRecord) {
                        $sourceTable = 'subapplications';
                    }
                }

                // Both id columns are ints. A composite id that reached here
                // unrecognised (e.g. "mother_assign_1028") would otherwise be
                // compared against them and surface as a raw SQL conversion error.
                if (!isset($sourceRecord) && is_numeric($applicationId)) {
                    $sourceRecord = DB::connection('sqlsrv')->table('instrument_registration')->where('id', $applicationId)->first();
                    if ($sourceRecord) {
                        $sourceTable = 'instrument_registration';
                    } else {
                        $sourceRecord = DB::connection('sqlsrv')->table('mother_applications')->where('id', $applicationId)->first();
                        if ($sourceRecord) {
                            $sourceTable = 'mother_applications';
                        }
                    }
                }
            }

            if (!$sourceRecord) {
                return response()->json(['success' => false, 'error' => 'Source record not found in any table'], 404);
            }

            // Dummy serial data (Service generates real numbers)
            $serialData = ['serial_no' => 0, 'page_no' => 0, 'volume_no' => 0, 'deeds_serial_no' => 'PENDING'];
            $stmReference = 'STM/' . date('Y') . '/' . mt_rand(1000, 9999);

            // Prepare data using legacy helper
            $preparedData = $this->prepareRegistrationData($sourceRecord, $sourceTable, $request, $serialData, $stmReference);

            // Map to Service format
            $serviceData = [
                'fileno' => $preparedData['MLSFileNo'] ?? $preparedData['fileNo'] ?? '',
                'parent_fileno' => $preparedData['parent_fileNo'] ?? null,
                'instrument_type' => $preparedData['instrument_type'],
                'grantor' => $preparedData['Grantor'],
                'grantee' => $preparedData['Grantee'],
                'lga' => $preparedData['lga'] ?? null,
                'district' => $preparedData['district'] ?? null,
                'plot_number' => $preparedData['plotNumber'] ?? null,
                'size' => $preparedData['size'] ?? null,
                'property_description' => $preparedData['propertyDescription'] ?? null,
                'deeds_date' => $request->deeds_date,
                'deeds_time' => $request->deeds_time,
                'op_type' => $preparedData['op_type'] ?? null,
                'extra_data' => $preparedData
            ];

            // Use Service to Register
            $newId = $this->instrumentService->registerInstrument($serviceData);

            // Fetch back the actual number for response
            $newRecord = DB::connection('sqlsrv')->table('deed_registrations')->where('id', $newId)->first();
            if ($newRecord) {
                $serialData = [
                    'serial_no' => $newRecord->serial_no,
                    'page_no' => $newRecord->page_no,
                    'volume_no' => $newRecord->volume_no,
                    'deeds_serial_no' => $newRecord->registration_number
                ];
            }

            // Update status using original ID if it's a composite ID
            $updateId = isset($sourceRecord->original_id) ? $sourceRecord->original_id : $applicationId;

            // For instrument_registration IDs, we need to extract the numeric part
            if ($sourceTable === 'instrument_registration' && strpos($updateId, 'instr_reg_') === 0) {
                $updateId = str_replace('instr_reg_', '', $updateId);
            }

            $this->updateSourceRecordStatus($updateId, $sourceTable);

            // Update instrument completion status for ST Assignment and Sectional Titling
            if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO']) && $sourceTable === 'subapplications') {
                $this->updateInstrumentCompletionStatus($updateId, $instrumentType, 'Registered');
            }

            // Check if both ST Assignment and Sectional Titling are now registered for this file
            if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                $this->checkBothTypesRegistered($request->file_no ?? $sourceRecord->fileno);
            }

            return response()->json([
                'success' => true,
                'message' => 'Instrument registered successfully',
                'serial_data' => $serialData,
                'stm_ref' => $stmReference,
                'record_id' => $newId,
                'source_table' => $sourceTable
            ]);
        } catch (\Exception $e) {
            Log::error('Error in registerSingle', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'error' => 'Failed to register: ' . $e->getMessage()], 500);
        }
    }

    private function prepareRegistrationData($sourceRecord, $sourceTable, $request, $serialData, $stmReference)
    {
        // Convert array inputs to comma-separated strings
        if (is_array($request->instrument_type)) {
            $request->instrument_type = implode(',', $request->instrument_type);
        }
        if (is_array($request->Grantor)) {
            $request->Grantor = implode(',', $request->Grantor);
        }
        if (is_array($request->Grantee)) {
            $request->Grantee = implode(',', $request->Grantee);
        }

        // Determine StFileNo based on instrument type and source
        $stFileNo = null;
        if (in_array($request->instrument_type, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
            $stFileNo = $request->file_no ?? $sourceRecord->fileno ?? null;
        }

        // Override grantor for ST Assignment and Sectional Titling CofO
        $grantor = $request->Grantor;
        $grantorAddress = $request->GrantorAddress ?? '';
        $granteeAddress = $request->GranteeAddress ?? '';

        if ($request->instrument_type === 'ST Assignment (Transfer of Title)') {
            // For ST Assignment (SUA), grantor should be allocation_entity
            if ($sourceTable === 'subapplications') {
                $unitTypeRaw = trim((string) ($sourceRecord->unit_type ?? ''));
                $unitType = $unitTypeRaw !== '' ? strtoupper($unitTypeRaw) : null;
                if ($unitType === 'SUA') {
                    $grantor = $sourceRecord->allocation_entity ?? $request->Grantor;
                    // For SUA: GrantorAddress = allocation_source, GranteeAddress = address
                    $grantorAddress = formatAddress($sourceRecord->allocation_source ?? '');
                    $granteeAddress = formatAddress($sourceRecord->address ?? '');
                }
            }
        } elseif ($request->instrument_type === 'Sectional Titling CofO') {
            // For Sectional Titling CofO, grantor is always Kano State Government
            $grantor = 'Kano State Government';
            $grantorAddress = 'Government House, Kano';
        }

        $baseData = [
            'particularsRegistrationNumber' => $serialData['deeds_serial_no'],
            'STM_Ref' => $stmReference,
            'instrument_type' => $request->instrument_type,
            'Grantor' => $grantor, // Use the overridden grantor value
            'Grantee' => $request->Grantee,
            'GrantorAddress' => $grantorAddress,
            'GranteeAddress' => $granteeAddress,
            'instrumentDate' => $request->deeds_date,
            'deeds_date' => $request->deeds_date,
            'deeds_time' => $request->deeds_time,
            'serial_no' => $serialData['serial_no'],
            'page_no' => $serialData['page_no'],
            'volume_no' => $serialData['volume_no'],
            'status' => 'registered',
            'StFileNo' => $stFileNo, // Add StFileNo field
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now()
        ];

        switch ($sourceTable) {
            case 'instrument_registration':
                return array_merge($baseData, [
                    'MLSFileNo' => $sourceRecord->MLSFileNo ?? $request->file_no,
                    'KAGISFileNO' => $sourceRecord->KAGISFileNO ?? null,
                    'NewKANGISFileNo' => $sourceRecord->NewKANGISFileNo ?? null,
                    'rootRegistrationNumber' => $sourceRecord->rootRegistrationNumber ?? null,
                    'GrantorAddress' => $request->GrantorAddress ?? $sourceRecord->GrantorAddress ?? '',
                    'GranteeAddress' => $request->GranteeAddress ?? $sourceRecord->GranteeAddress ?? '',
                    'mortgagor' => $sourceRecord->mortgagor ?? null,
                    'mortgagorAddress' => $sourceRecord->mortgagorAddress ?? null,
                    'mortgagee' => $sourceRecord->mortgagee ?? null,
                    'mortgageeAddress' => $sourceRecord->mortgageeAddress ?? null,
                    'loanAmount' => $sourceRecord->loanAmount ?? null,
                    'interestRate' => $sourceRecord->interestRate ?? null,
                    'duration' => $request->duration ?? $sourceRecord->duration ?? null,
                    'assignor' => $sourceRecord->assignor ?? null,
                    'assignorAddress' => $sourceRecord->assignorAddress ?? null,
                    'assignee' => $sourceRecord->assignee ?? null,
                    'assigneeAddress' => $sourceRecord->assigneeAddress ?? null,
                    'lessor' => $sourceRecord->lessor ?? null,
                    'lessorAddress' => $sourceRecord->lessorAddress ?? null,
                    'lessee' => $sourceRecord->lessee ?? null,
                    'lesseeAddress' => $sourceRecord->lesseeAddress ?? null,
                    'leasePeriod' => $sourceRecord->leasePeriod ?? null,
                    'leaseTerms' => $sourceRecord->leaseTerms ?? null,
                    'propertyDescription' => $request->propertyDescription ?? $sourceRecord->propertyDescription ?? '',
                    'propertyAddress' => $sourceRecord->propertyAddress ?? null,
                    'lga' => $request->lga ?? $sourceRecord->lga ?? '',
                    'district' => $request->district ?? $sourceRecord->district ?? '',
                    'size' => $request->plotSize ?? $sourceRecord->size ?? '',
                    'plotNumber' => $request->plotNumber ?? $sourceRecord->plotNumber ?? '',
                    'landUseType' => $sourceRecord->landUseType ?? null,
                    'solicitorName' => $sourceRecord->solicitorName ?? null,
                    'solicitorAddress' => $sourceRecord->solicitorAddress ?? null,
                ]);

            case 'mother_applications':
                return array_merge($baseData, [
                    'MLSFileNo' => $sourceRecord->fileno ?? $request->file_no,
                    'lga' => $sourceRecord->property_lga ?? '',
                    'district' => $sourceRecord->property_district ?? '',
                    'size' => $sourceRecord->plot_size ?? '',
                    'plotNumber' => $sourceRecord->property_plot_no ?? '',
                ]);

            case 'subapplications':
                $motherApp = DB::connection('sqlsrv')->table('mother_applications')->where('id', $sourceRecord->main_application_id)->first();

                // Build property description for ST Assignment and Sectional Titling CofO
                $propertyDescription = $request->propertyDescription ?? '';
                if (empty($propertyDescription) && in_array($request->instrument_type, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                    $propertyParts = [];

                    if (!empty($motherApp->property_house_no)) {
                        $propertyParts[] = 'House No: ' . $motherApp->property_house_no;
                    }
                    if (!empty($motherApp->property_plot_no)) {
                        $propertyParts[] = 'Plot No: ' . $motherApp->property_plot_no;
                    }
                    if (!empty($motherApp->property_street_name)) {
                        $propertyParts[] = $motherApp->property_street_name;
                    }
                    if (!empty($motherApp->property_district)) {
                        $propertyParts[] = $motherApp->property_district;
                    }
                    if (!empty($motherApp->property_lga)) {
                        $propertyParts[] = $motherApp->property_lga;
                    }

                    $propertyDescription = implode(', ', $propertyParts);
                    if (empty($propertyDescription)) {
                        $propertyDescription = 'Property details not available';
                    }
                }

                // CRITICAL FIX: For ST Assignment and Sectional Titling CofO, set parent_fileNo to np_fileno from mother application
                $parentFileNo = null;
                if (in_array($request->instrument_type, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                    $parentFileNo = $motherApp->np_fileno ?? null;
                }

                return array_merge($baseData, [
                    'MLSFileNo' => $sourceRecord->fileno ?? $request->file_no,
                    'parent_fileNo' => $parentFileNo, // FIXED: Add parent_fileNo field for ST Assignment and Sectional Titling CofO
                    'lga' => $motherApp->property_lga ?? '',
                    'district' => $motherApp->property_district ?? '',
                    'size' => $motherApp->plot_size ?? '',
                    'plotNumber' => $motherApp->property_plot_no ?? '',
                    'propertyDescription' => $propertyDescription,
                ]);

            case 'instrument_capture':
                return array_merge($baseData, [
                    'MLSFileNo' => $sourceRecord->mlsFNo ?? $request->file_no,
                    'KAGISFileNO' => $sourceRecord->kangisFileNo ?? null,
                    'NewKANGISFileNo' => $sourceRecord->NewKANGISFileno ?? null,
                    'rootRegistrationNumber' => $sourceRecord->rootRegNo ?? null,
                    'GrantorAddress' => $request->GrantorAddress ?? $sourceRecord->party_1_address ?? '',
                    'GranteeAddress' => $request->GranteeAddress ?? $sourceRecord->party_2_address ?? '',
                    'mortgagor' => $sourceRecord->party_1_name ?? null,
                    'mortgagorAddress' => $sourceRecord->party_1_address ?? null,
                    'mortgagee' => $sourceRecord->party_2_name ?? null,
                    'mortgageeAddress' => $sourceRecord->party_2_address ?? null,
                    'loanAmount' => $sourceRecord->consideration_amount ?? null,
                    'duration' => $request->duration ?? $sourceRecord->duration ?? null,
                    'assignor' => $sourceRecord->party_1_name ?? null,
                    'assignorAddress' => $sourceRecord->party_1_address ?? null,
                    'assignee' => $sourceRecord->party_2_name ?? null,
                    'assigneeAddress' => $sourceRecord->party_2_address ?? null,
                    'lessor' => $sourceRecord->party_1_name ?? null,
                    'lessorAddress' => $sourceRecord->party_1_address ?? null,
                    'lessee' => $sourceRecord->party_2_name ?? null,
                    'lesseeAddress' => $sourceRecord->party_2_address ?? null,
                    'leasePeriod' => $sourceRecord->duration ?? null,
                    'propertyDescription' => $request->propertyDescription ?? $sourceRecord->property_description ?? '',
                    'lga' => $request->lga ?? $sourceRecord->lga ?? '',
                    'plotNumber' => $sourceRecord->plot_number ?? null,
                    'size' => $sourceRecord->plot_size ?? null,
                    'district' => $sourceRecord->district ?? null,
                    'op_type' => $sourceRecord->op_type ?? null,
                ]);

            default:
                return $baseData;
        }
    }

    /**
     * Check if both ST Assignment and Sectional Titling CofO are registered for a file
     * and update the subapplication status accordingly
     */
    private function checkBothTypesRegistered($fileNo)
    {
        try {
            $registrations = DB::connection('sqlsrv')->table('registered_instruments')
                ->where('StFileNo', $fileNo)
                ->whereIn('instrument_type', ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])
                ->where('status', 'registered')
                ->pluck('instrument_type')
                ->toArray();

            $hasSTAssignment = in_array('ST Assignment (Transfer of Title)', $registrations);
            $hasSectionalTitling = in_array('Sectional Titling CofO', $registrations);

            if ($hasSTAssignment && $hasSectionalTitling) {
                // Both types are registered, update the subapplication status
                $subApplication = DB::connection('sqlsrv')->table('subapplications')
                    ->where('fileno', $fileNo)
                    ->first();

                if ($subApplication) {
                    DB::connection('sqlsrv')->table('subapplications')
                        ->where('id', $subApplication->id)
                        ->update([
                            'deeds_status' => 'registered',
                            'updated_at' => now()
                        ]);

                    Log::info('Both ST Assignment and Sectional Titling registered', [
                        'file_no' => $fileNo,
                        'subapplication_id' => $subApplication->id
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Error checking both types registered', [
                'file_no' => $fileNo,
                'exception' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update instrument completion status for ST Assignment and Sectional Titling CofO
     */
    private function updateInstrumentCompletionStatus($subApplicationId, $instrumentType, $status = 'Registered')
    {
        try {
            // Get current completion status
            $currentRecord = DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $subApplicationId)
                ->select('deeds_completion_status')
                ->first();

            // Initialize or parse existing JSON
            $completionStatus = [
                'instruments' => [
                    [
                        'name' => 'ST Assignment (Transfer of Title)',
                        'status' => 'Pending'
                    ],
                    [
                        'name' => 'Sectional Titling CofO',
                        'status' => 'Pending'
                    ]
                ]
            ];

            if ($currentRecord && !empty($currentRecord->deeds_completion_status)) {
                $existingStatus = json_decode($currentRecord->deeds_completion_status, true);
                if ($existingStatus && isset($existingStatus['instruments'])) {
                    $completionStatus = $existingStatus;
                }
            }

            // Update the specific instrument status
            foreach ($completionStatus['instruments'] as &$instrument) {
                if ($instrument['name'] === $instrumentType) {
                    $instrument['status'] = $status;
                    break;
                }
            }

            // Update the database
            DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $subApplicationId)
                ->update([
                    'deeds_completion_status' => json_encode($completionStatus),
                    'updated_at' => now(),
                    'updated_by' => Auth::id()
                ]);

            Log::info('Updated instrument completion status', [
                'subapplication_id' => $subApplicationId,
                'instrument_type' => $instrumentType,
                'status' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating instrument completion status', [
                'subapplication_id' => $subApplicationId,
                'instrument_type' => $instrumentType,
                'exception' => $e->getMessage()
            ]);
        }
    }

    /**
     * Initialize default completion status for subapplications that don't have it set
     */
    private function initializeDefaultCompletionStatus()
    {
        try {
            // Get subapplications without deeds_completion_status
            $subapplicationsWithoutStatus = DB::connection('sqlsrv')->table('subapplications')
                ->where(function ($query) {
                    $query->whereNull('deeds_completion_status')
                        ->orWhere('deeds_completion_status', '');
                })
                ->where('planning_recommendation_status', 'Approved')
                ->where('application_status', 'Approved')
                ->get();

            foreach ($subapplicationsWithoutStatus as $subApp) {
                $defaultStatus = [
                    'instruments' => [
                        [
                            'name' => 'ST Assignment (Transfer of Title)',
                            'status' => 'pending'
                        ],
                        [
                            'name' => 'Sectional Titling CofO',
                            'status' => 'pending'
                        ]
                    ]
                ];

                DB::connection('sqlsrv')->table('subapplications')
                    ->where('id', $subApp->id)
                    ->update([
                        'deeds_completion_status' => json_encode($defaultStatus),
                        'updated_at' => now()
                    ]);
            }

            if ($subapplicationsWithoutStatus->count() > 0) {
                Log::info('Initialized default completion status', [
                    'count' => $subapplicationsWithoutStatus->count()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error initializing default completion status', [
                'exception' => $e->getMessage()
            ]);
        }
    }

    /**
     * Automatically register ST Fragmentation for approved mother applications
     * This function is called when the Instrument Registration page loads
     */
    /**
     * Automatically register ST Fragmentation for approved mother applications
     * This function is called when the Instrument Registration page loads
     */
    private function autoRegisterSTFragmentation()
    {
        // Delegated to Service
        $this->instrumentService->autoRegisterSTFragmentation();
    }

    /**
     * Automatically register ST Fragmentation for approved sub-applications where primary is owner
     */
    private function autoRegisterSTFragmentationForSubApps()
    {
        // Delegated to Service
        $this->instrumentService->autoRegisterSTSubApps();
    }

    private function autoRegisterSTFragmentationForSubAppsLegacy()
    {
        Log::info('autoRegisterSTFragmentationForSubApps called - Version with Mother App Join');
        try {
            $approvedSubApps = DB::connection('sqlsrv')->table('subapplications as s')
                ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                ->leftJoin('registered_instruments as ri', function ($join) {
                    $join->on('s.fileno', '=', 'ri.StFileNo')
                        ->where('ri.instrument_type', '=', 'ST Fragmentation')
                        ->where('ri.status', '=', 'registered');
                })
                ->where('s.is_primary_the_owner', 1)
                ->where(function ($query) {
                    $query->where('s.planning_recommendation_status', 'Approved')
                        ->orWhere('s.planning_recommendation_status', 'Pending');
                })
                ->whereNull('ri.id')
                ->select(
                    's.id',
                    's.fileno',
                    's.np_fileno',
                    'm.property_lga as lga',
                    'm.property_district as district',
                    'm.plot_size as size',
                    'm.property_plot_no as plotNumber',
                    'm.property_house_no',
                    'm.property_street_name',
                    'm.fileno as mother_fileno',
                    's.applicant_title',
                    's.first_name',
                    's.middle_name',
                    's.surname',
                    's.corporate_name',
                    's.rc_number',
                    's.multiple_owners_names',
                    's.created_by'
                )
                ->get();

            $registeredCount = 0;

            foreach ($approvedSubApps as $subApp) {
                Log::info("Processing ST Fragmentation for SubApp ID: {$subApp->id}, FileNo: {$subApp->fileno}");
                $stmReference = $this->generateSTMReference();

                $granteeName = $this->buildApplicantDisplayName(
                    $subApp->applicant_title,
                    $subApp->first_name,
                    $subApp->middle_name,
                    $subApp->surname,
                    $subApp->corporate_name,
                    $subApp->rc_number,
                    $subApp->multiple_owners_names
                );

                // Build property description
                $propertyParts = [];
                if (!empty($subApp->property_house_no))
                    $propertyParts[] = 'House No: ' . $subApp->property_house_no;
                if (!empty($subApp->plotNumber))
                    $propertyParts[] = 'Plot No: ' . $subApp->plotNumber;
                if (!empty($subApp->property_street_name))
                    $propertyParts[] = $subApp->property_street_name;
                if (!empty($subApp->district))
                    $propertyParts[] = $subApp->district;
                if (!empty($subApp->lga))
                    $propertyParts[] = $subApp->lga;
                $propertyDescription = implode(', ', $propertyParts) ?: 'Property details not available';

                $registrationData = [
                    'particularsRegistrationNumber' => '0/0/0',
                    'STM_Ref' => $stmReference,
                    'instrument_type' => 'ST Fragmentation',
                    'Grantor' => 'Kano State Government',
                    'Grantee' => $granteeName,
                    'instrumentDate' => now(),
                    'deeds_date' => now(),
                    'deeds_time' => now()->format('H:i:s'),
                    'serial_no' => 0,
                    'page_no' => 0,
                    'volume_no' => 0,
                    'status' => 'registered',
                    'parent_fileNo' => $subApp->mother_fileno,
                    'fileNo' => $subApp->fileno,
                    'StFileNo' => $subApp->fileno,
                    'MLSFileNo' => $subApp->fileno,
                    'lga' => $subApp->lga ?? '',
                    'district' => $subApp->district ?? '',
                    'size' => $subApp->size ?? '',
                    'plotNumber' => $subApp->plotNumber ?? '',
                    'propertyDescription' => $propertyDescription,
                    'created_by' => $subApp->created_by ?? Auth::id() ?? 1,
                    'updated_by' => Auth::id() ?? 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                DB::connection('sqlsrv')->table('registered_instruments')->insert($registrationData);
                $registeredCount++;
            }

            if ($registeredCount > 0) {
                Log::info('Auto-registered ST Fragmentation records for SubApps', ['count' => $registeredCount]);
            }

        } catch (\Exception $e) {
            Log::error('Error auto-registering ST Fragmentation for SubApps', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function getNextSerialNumber(Request $request)
    {
        // Now delegating to Service for generic vault handling
        // The frontend passes 'instrument_type' via query param or we assume 'batch' logic if missing?
        // Actually, the new frontend plan says we fetch per type.
        // For backward compatibility or if type is not passed, we might default or error.

        $instrumentType = $request->input('instrument_type', 'Certificate of Occupancy'); // Default if missing

        // Use the service to get a PREVIEW
        $preview = $this->instrumentService->previewNextNumber($instrumentType);

        $serial = $preview['serial'] ?? 1;
        $page = $preview['page'] ?? 1;
        $volume = $preview['volume'] ?? 1;
        $formatted = $preview['formatted'] ?? "{$serial}/{$page}/{$volume}";

        return response()->json([
            'serial_no' => (int) $serial,
            'page_no' => (int) $page,
            'volume_no' => (int) $volume,
            'deeds_serial_no' => $formatted
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function registerBatch(Request $request)
    {
        try {
            $request->validate([
                'batch_entries' => 'required|array',
                'deeds_time' => 'required|string',
                'deeds_date' => 'required|date'
            ]);

            $batchSessionId = $this->generateBatchRegistrationSessionId();

            // Pre-validate ST Assignment and Sectional Titling entries
            $stFileValidation = [];
            foreach ($request->batch_entries as $entry) {
                $instrumentType = $entry['instrument_type'] ?? '';
                if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                    $fileNo = $entry['file_no'] ?? '';
                    if (empty($fileNo)) {
                        return response()->json([
                            'success' => false,
                            'error' => "File number (StFileNo) is required for {$instrumentType}"
                        ], 422);
                    }

                    // Track what we're trying to register for each file
                    if (!isset($stFileValidation[$fileNo])) {
                        $stFileValidation[$fileNo] = [];
                    }
                    $stFileValidation[$fileNo][] = $instrumentType;
                }
            }

            // Check for existing registrations and duplicates within the batch
            foreach ($stFileValidation as $fileNo => $types) {
                // Check for duplicates within the batch
                if (count($types) !== count(array_unique($types))) {
                    return response()->json([
                        'success' => false,
                        'error' => "Duplicate instrument types found in batch for file number {$fileNo}"
                    ], 422);
                }

                // Check existing registrations in database
                $existingRegistrations = DB::connection('sqlsrv')->table('registered_instruments')
                    ->where('StFileNo', $fileNo)
                    ->whereIn('instrument_type', ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])
                    ->pluck('instrument_type')
                    ->toArray();

                foreach ($types as $type) {
                    if (in_array($type, $existingRegistrations)) {
                        return response()->json([
                            'success' => false,
                            'error' => "A {$type} registration already exists for file number {$fileNo}"
                        ], 422);
                    }
                }
            }

            // Dummy serial data (Service generates real numbers per entry)
            $serialData = ['serial_no' => 0, 'page_no' => 0, 'volume_no' => 0, 'deeds_serial_no' => 'PENDING'];
            $results = [];
            $processedRecords = [];
            $registeredFiles = []; // Track files for final validation

            DB::beginTransaction();
            DB::connection('sqlsrv')->beginTransaction();

            foreach ($request->batch_entries as $index => $entry) {
                // Manual increment logic REMOVED - Service handles it atomically

                $applicationId = $entry['application_id'];
                $sourceRecord = null;
                $sourceTable = null;

                // Handle composite IDs for ST Assignment and Sectional Titling
                if (strpos($applicationId, '_st_assignment') !== false || strpos($applicationId, '_sectional_cofo') !== false) {
                    $originalId = str_replace(['_st_assignment', '_sectional_cofo'], '', $applicationId);
                    $sourceRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $originalId)->first();
                    if ($sourceRecord) {
                        $sourceTable = 'subapplications';
                        $sourceRecord->original_id = $originalId;
                    }
                } elseif (strpos($applicationId, '_st_fragmentation') !== false) {
                    // Handle ST Fragmentation - Maps to Mother Applications
                    $originalId = str_replace('_st_fragmentation', '', $applicationId);
                    $sourceRecord = DB::connection('sqlsrv')->table('mother_applications')->where('id', $originalId)->first();
                    if ($sourceRecord) {
                        $sourceTable = 'mother_applications';
                        $sourceRecord->original_id = $originalId;
                    }
                } elseif (strpos($applicationId, 'instr_reg_') === 0) {
                    // Handle instrument_registration IDs that start with 'instr_reg_'
                    $originalId = str_replace('instr_reg_', '', $applicationId);
                    $sourceRecord = DB::connection('sqlsrv')->table('instrument_registration')->where('id', $originalId)->first();
                    if ($sourceRecord) {
                        $sourceTable = 'instrument_registration';
                        $sourceRecord->original_id = $originalId; // Ensure we keep track of the numeric ID
                    }
                } elseif (strpos($applicationId, 'instr_capture_') === 0) {
                    // Handle instrument_capture IDs that start with 'instr_capture_'
                    $originalId = str_replace('instr_capture_', '', $applicationId);
                    $sourceRecord = DB::connection('sqlsrv')->table('instrument_capture')->where('id', $originalId)->first();
                    if ($sourceRecord) {
                        $sourceTable = 'instrument_capture';
                        $sourceRecord->original_id = $originalId;
                    }
                } else {
                    // Only check specific tables if ID is numeric to avoid SQL conversion errors with composite string IDs
                    if (is_numeric($applicationId)) {
                        $sourceRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $applicationId)->first();
                        if ($sourceRecord) {
                            $sourceTable = 'subapplications';
                        }
                    }

                    if (!isset($sourceRecord) && is_numeric($applicationId)) {
                        $sourceRecord = DB::connection('sqlsrv')->table('instrument_registration')->where('id', $applicationId)->first();
                        if ($sourceRecord) {
                            $sourceTable = 'instrument_registration';
                        } else {
                            $sourceRecord = DB::connection('sqlsrv')->table('mother_applications')->where('id', $applicationId)->first();
                            if ($sourceRecord) {
                                $sourceTable = 'mother_applications';
                            }
                        }
                    }
                }

                if (!$sourceRecord) {
                    Log::warning('Source record not found for batch entry', ['application_id' => $applicationId]);
                    continue;
                }

                // Update ID handling for instrument_registration IDs
                $updateId = isset($sourceRecord->original_id) ? $sourceRecord->original_id : $applicationId;

                // For instrument_registration and instrument_capture IDs, we need to extract the numeric part
                if (($sourceTable === 'instrument_registration' || $sourceTable === 'instrument_capture') && (strpos($updateId, 'instr_reg_') === 0 || strpos($updateId, 'instr_capture_') === 0)) {
                    $updateId = str_replace(['instr_reg_', 'instr_capture_'], '', $updateId);
                }

                $processedRecords[] = ['id' => $updateId, 'table' => $sourceTable];
                $stmReference = $this->generateSTMReference();

                $entryRequest = new \Illuminate\Http\Request();
                $entryRequest->merge([
                    'instrument_type' => $entry['instrument_type'] ?? '',
                    'Grantor' => $entry['grantor'] ?? '',
                    'Grantee' => $entry['grantee'] ?? '',
                    'duration' => $entry['duration'] ?? '',
                    'propertyDescription' => $entry['propertyDescription'] ?? '',
                    'lga' => $entry['lga'] ?? '',
                    'district' => $entry['district'] ?? '',
                    'plotSize' => $entry['size'] ?? '',
                    'plotNumber' => $entry['plotNumber'] ?? '',
                    'deeds_date' => $request->deeds_date,
                    'deeds_time' => $request->deeds_time,
                    'file_no' => $entry['file_no'] ?? ''
                ]);

                $preparedData = $this->prepareRegistrationData($sourceRecord, $sourceTable, $entryRequest, $serialData, $stmReference);

                // Map to Service format
                $serviceData = [
                    'instrument_capture_id' => ($sourceTable === 'instrument_capture' ? $updateId : null),
                    'fileno' => $preparedData['MLSFileNo'] ?? $preparedData['fileNo'] ?? '',
                    'parent_fileno' => $preparedData['parent_fileNo'] ?? null,
                    'instrument_type' => $preparedData['instrument_type'],
                    'grantor' => $preparedData['Grantor'],
                    'grantee' => $preparedData['Grantee'],
                    'lga' => $preparedData['lga'] ?? null,
                    'district' => $preparedData['district'] ?? null,
                    'plot_number' => $preparedData['plotNumber'] ?? null,
                    'size' => $preparedData['size'] ?? null,
                    'property_description' => $preparedData['propertyDescription'] ?? null,
                    'op_type' => $preparedData['op_type'] ?? null,
                    'is_auto_reg' => false,
                    'batch_session_id' => $batchSessionId,
                    'extra_data' => $preparedData
                ];

                // Use Service to Register
                $newId = $this->instrumentService->registerInstrument($serviceData);

                // Update instrument completion status for ST Assignment and Sectional Titling
                $instrumentType = $entry['instrument_type'] ?? '';
                if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO']) && $sourceTable === 'subapplications') {
                    $this->updateInstrumentCompletionStatus($updateId, $instrumentType, 'Registered');
                }

                // Track registered files for final validation
                if (in_array($instrumentType, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])) {
                    $fileNo = $entry['file_no'] ?? $sourceRecord->fileno;
                    $registeredFiles[] = $fileNo;
                }

                // Fetch valid number
                $inserted = DB::connection('sqlsrv')->table('deed_registrations')->where('id', $newId)->first();
                $finalSerial = $inserted ? $inserted->registration_number : 'PENDING';

                $results[] = [
                    'application_id' => $applicationId,
                    'new_id' => $newId,
                    'batch_session_id' => $batchSessionId,
                    'deeds_serial_no' => $finalSerial,
                    'stm_ref' => $stmReference,
                    'source_table' => $sourceTable
                ];
            }

            foreach ($processedRecords as $record) {
                $this->updateSourceRecordStatus($record['id'], $record['table']);
            }

            // Check if both types are registered for each file
            foreach (array_unique($registeredFiles) as $fileNo) {
                $this->checkBothTypesRegistered($fileNo);
            }

            DB::commit();
            DB::connection('sqlsrv')->commit();

            /*
             | One message per instrument in the batch, AFTER both commits: this
             | path opens two transactions and a message announcing a registration
             | that then rolled back would be worse than none.
             */
            $this->sendBatchDeedRegisteredSms($results);

            return response()->json([
                'success' => true,
                'message' => count($results) . ' instruments registered successfully',
                'batch_session_id' => $batchSessionId,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            DB::connection('sqlsrv')->rollBack();
            Log::error('Error in registerBatch', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'error' => 'Failed to register batch: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Check registration status for ST Assignment and Sectional Titling CofO for a given file number
     */
    public function checkRegistrationStatus(Request $request)
    {
        try {
            $fileNo = $request->query('file_no');

            if (empty($fileNo)) {
                return response()->json([
                    'success' => false,
                    'error' => 'File number is required'
                ], 400);
            }

            $registrations = DB::connection('sqlsrv')->table('registered_instruments')
                ->where('StFileNo', $fileNo)
                ->whereIn('instrument_type', ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])
                ->select('instrument_type', 'status', 'particularsRegistrationNumber', 'STM_Ref', 'created_at')
                ->get();

            $stAssignment = $registrations->firstWhere('instrument_type', 'ST Assignment (Transfer of Title)');
            $sectionalTitling = $registrations->firstWhere('instrument_type', 'Sectional Titling CofO');

            $response = [
                'success' => true,
                'file_no' => $fileNo,
                'st_assignment' => [
                    'registered' => !is_null($stAssignment),
                    'status' => $stAssignment->status ?? null,
                    'registration_number' => $stAssignment->particularsRegistrationNumber ?? null,
                    'stm_ref' => $stAssignment->STM_Ref ?? null,
                    'registered_date' => $stAssignment->created_at ?? null
                ],
                'sectional_titling' => [
                    'registered' => !is_null($sectionalTitling),
                    'status' => $sectionalTitling->status ?? null,
                    'registration_number' => $sectionalTitling->particularsRegistrationNumber ?? null,
                    'stm_ref' => $sectionalTitling->STM_Ref ?? null,
                    'registered_date' => $sectionalTitling->created_at ?? null
                ],
                'both_registered' => !is_null($stAssignment) && !is_null($sectionalTitling),
                'total_registrations' => $registrations->count()
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error checking registration status', [
                'file_no' => $request->query('file_no'),
                'exception' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to check registration status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getBatchData(Request $request)
    {
        try {
            $filter = $request->query('filter', 'batch');
            $data = collect();
            $isStDeeds = str_contains($request->header('referer', ''), 'st_deeds') 
                || $request->query('is_st') == '1' 
                || $request->query('st_only') == '1';

            switch ($filter) {
                case 'other':
                    // FIXED: Only return pending Other instruments
                    $otherQuery = DB::connection('sqlsrv')->table('instrument_registration')
                        ->where('status', 'pending')
                        ->whereNull('particularsRegistrationNumber') // Exclude instruments with registration numbers
                        ->whereNull('STM_Ref'); // Exclude instruments with STM references

                    if ($isStDeeds) {
                        $otherQuery->where(function ($q) {
                            $q->where('MLSFileNo', 'like', 'ST-%')
                              ->orWhere('KAGISFileNO', 'like', 'ST-%')
                              ->orWhere('NewKANGISFileNo', 'like', 'ST-%')
                              ->orWhere('StFileNo', 'like', 'ST-%');
                        });
                    }

                    $data = $otherQuery->select(
                            'id',
                            DB::raw("COALESCE(MLSFileNo, KAGISFileNO, NewKANGISFileNo) as fileno"),
                            'instrument_type',
                            'Grantor as grantor',
                            'Grantee as grantee',
                            'lga',
                            'district',
                            'size',
                            'plotNumber',
                            'created_at',
                            DB::raw("'pending' as status"),
                            DB::raw("'Other Instruments' as source_type")
                        )
                        ->get();

                    $capturedQuery = DB::connection('sqlsrv')->table('instrument_capture')
                        ->where(function ($q) {
                            $q->where('is_deed_registered', 0)
                                ->orWhereNull('is_deed_registered');
                        });

                    if ($isStDeeds) {
                        $capturedQuery->where(function ($q) {
                            $q->where('mlsFNo', 'like', 'ST-%')
                              ->orWhere('kangisFileNo', 'like', 'ST-%')
                              ->orWhere('NewKANGISFileno', 'like', 'ST-%')
                              ->orWhere('temp_fileno', 'like', 'ST-%');
                        });
                    }

                    $capturedInstruments = $capturedQuery->select(
                            'id',
                            DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno) as fileno"),
                            'instrument_type',
                            'party_1_name as grantor',
                            'party_2_name as grantee',
                            'lga',
                            'district',
                            'plot_size as size',
                            'plot_number as plotNumber',
                            'created_at',
                            'op_type',
                            DB::raw("'pending' as status"),
                            DB::raw("'Captured' as source_type")
                        )
                        ->get();

                    $capturedData = collect();
                    foreach ($capturedInstruments as $cap) {
                        $capturedData->push((object) [
                            'id' => 'instr_capture_' . $cap->id,
                            'fileno' => $cap->fileno,
                            'instrument_type' => $this->mapCaptureTypeToRegistry($cap->instrument_type),
                            'grantor' => $cap->grantor,
                            'grantee' => $cap->grantee,
                            'lga' => $cap->lga,
                            'district' => $cap->district,
                            'size' => $cap->size,
                            'plotNumber' => $cap->plotNumber,
                            'created_at' => $cap->created_at,
                            'status' => 'pending',
                            'source_type' => 'Captured',
                            'op_type' => $cap->op_type ?? null,
                        ]);
                    }

                    $data = $data->merge($capturedData);
                    break;

                case 'stAssignment':
                    // FIXED: Only return pending ST Assignment instruments
                    $approvedSubapplications = DB::connection('sqlsrv')->table('subapplications as s')
                        ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                        ->leftJoin('registered_instruments as ri', function ($join) {
                            $join->on('s.fileno', '=', 'ri.StFileNo')
                                ->where('ri.instrument_type', '=', 'ST Assignment (Transfer of Title)')
                                ->where('ri.status', '=', 'registered');
                        })
                        ->where('s.planning_recommendation_status', 'Approved')
                        ->where('s.application_status', 'Approved')
                        ->whereNull('ri.id') // Only get subapplications without existing ST Assignment registration
                        ->select(
                            's.id',
                            's.fileno',
                            's.deeds_completion_status',
                            DB::raw("CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.surname,''), COALESCE(s.corporate_name,''), COALESCE(s.multiple_owners_names,'')) as sub_applicant"),
                            DB::raw("CONCAT(COALESCE(m.applicant_title,''), ' ', COALESCE(m.first_name,''), ' ', COALESCE(m.surname,''), COALESCE(m.corporate_name,''), COALESCE(m.multiple_owners_names,'')) as mother_applicant"),
                            'm.property_lga as lga',
                            'm.property_district as district',
                            'm.plot_size as size',
                            'm.property_plot_no as plotNumber',
                            's.created_at'
                        )
                        ->get();

                    // Create ST Assignment records for each pending subapplication
                    $data = collect();
                    foreach ($approvedSubapplications as $subApp) {
                        $data->push((object) [
                            'id' => $subApp->id . '_st_assignment',
                            'fileno' => $subApp->fileno,
                            'instrument_type' => 'ST Assignment (Transfer of Title)',
                            'grantor' => $subApp->mother_applicant,
                            'grantee' => $subApp->sub_applicant,
                            'lga' => $subApp->lga,
                            'district' => $subApp->district,
                            'size' => $subApp->size,
                            'plotNumber' => $subApp->plotNumber,
                            'created_at' => $subApp->created_at,
                            'status' => 'pending',
                            'source_type' => 'ST Assignment',
                            'original_subapp_id' => $subApp->id
                        ]);
                    }
                    break;

                case 'regular':
                case 'sltr':
                    // Keep these available for other instrument types in modals
                    $data = collect([
                        (object) [
                            'id' => null,
                            'fileno' => 'No Record',
                            'grantor' => 'No Record',
                            'grantee' => 'No Record',
                            'lga' => 'No Record',
                            'district' => 'No Record',
                            'size' => 'No Record',
                            'plotNumber' => 'No Record',
                            'created_at' => null,
                            'status' => 'unavailable'
                        ]
                    ]);
                    break;

                case 'sectional':
                    // FIXED: Only return pending Sectional Titling instruments
                    $approvedSubapplications = DB::connection('sqlsrv')->table('subapplications as s')
                        ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                        ->leftJoin('registered_instruments as ri', function ($join) {
                            $join->on('s.fileno', '=', 'ri.StFileNo')
                                ->where('ri.instrument_type', '=', 'Sectional Titling CofO')
                                ->where('ri.status', '=', 'registered');
                        })
                        ->where('s.planning_recommendation_status', 'Approved')
                        ->where('s.application_status', 'Approved')
                        ->whereNull('ri.id') // Only get subapplications without existing Sectional Titling registration
                        ->select(
                            's.id',
                            's.fileno',
                            's.deeds_completion_status',
                            DB::raw("CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.surname,''), COALESCE(s.corporate_name,''), COALESCE(s.multiple_owners_names,'')) as sub_applicant"),
                            DB::raw("CONCAT(COALESCE(m.applicant_title,''), ' ', COALESCE(m.first_name,''), ' ', COALESCE(m.surname,''), COALESCE(m.corporate_name,''), COALESCE(m.multiple_owners_names,'')) as mother_applicant"),
                            'm.property_lga as lga',
                            'm.property_district as district',
                            'm.plot_size as size',
                            'm.property_plot_no as plotNumber',
                            's.created_at'
                        )
                        ->get();

                    // Create Sectional Titling records for each pending subapplication
                    $data = collect();
                    foreach ($approvedSubapplications as $subApp) {
                        $data->push((object) [
                            'id' => $subApp->id . '_sectional_cofo',
                            'fileno' => $subApp->fileno,
                            'instrument_type' => 'Sectional Titling CofO',
                            'grantor' => 'Kano State Government',
                            'grantee' => $subApp->sub_applicant,
                            'lga' => $subApp->lga,
                            'district' => $subApp->district,
                            'size' => $subApp->size,
                            'plotNumber' => $subApp->plotNumber,
                            'created_at' => $subApp->created_at,
                            'status' => 'pending',
                            'source_type' => 'Sectional Titling',
                            'original_subapp_id' => $subApp->id
                        ]);
                    }
                    break;

                case 'batch':
                default:
                    // FIXED: For batch registration, only include pending instruments

                    // Get pending other instruments
                    $instrumentQuery = DB::connection('sqlsrv')->table('instrument_registration')
                        ->where('status', 'pending')
                        ->whereNull('particularsRegistrationNumber') // Exclude instruments with registration numbers
                        ->whereNull('STM_Ref'); // Exclude instruments with STM references

                    if ($isStDeeds) {
                        $instrumentQuery->where(function ($q) {
                            $q->where('MLSFileNo', 'like', 'ST-%')
                              ->orWhere('KAGISFileNO', 'like', 'ST-%')
                              ->orWhere('NewKANGISFileNo', 'like', 'ST-%')
                              ->orWhere('StFileNo', 'like', 'ST-%');
                        });
                    }

                    $instrumentData = $instrumentQuery->select(
                            'id',
                            DB::raw("COALESCE(MLSFileNo, KAGISFileNO, NewKANGISFileNo) as fileno"),
                            'instrument_type',
                            'Grantor as grantor',
                            'Grantee as grantee',
                            'lga',
                            'district',
                            'size',
                            'plotNumber',
                            'created_at',
                            DB::raw("'pending' as status"),
                            DB::raw("'Other Instruments' as source_type")
                        )
                        ->get();

                    // Get pending ST Assignment instruments
                    $pendingSTAssignments = DB::connection('sqlsrv')->table('subapplications as s')
                        ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                        ->leftJoin('registered_instruments as ri', function ($join) {
                            $join->on('s.fileno', '=', 'ri.StFileNo')
                                ->where('ri.instrument_type', '=', 'ST Assignment (Transfer of Title)')
                                ->where('ri.status', '=', 'registered');
                        })
                        ->where('s.planning_recommendation_status', 'Approved')
                        ->where('s.application_status', 'Approved')
                        ->whereNull('ri.id') // Only get subapplications without existing ST Assignment registration
                        ->select(
                            's.id',
                            's.fileno',
                            's.deeds_completion_status',
                            DB::raw("CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.surname,''), COALESCE(s.corporate_name,''), COALESCE(s.multiple_owners_names,'')) as sub_applicant"),
                            DB::raw("CONCAT(COALESCE(m.applicant_title,''), ' ', COALESCE(m.first_name,''), ' ', COALESCE(m.surname,''), COALESCE(m.corporate_name,''), COALESCE(m.multiple_owners_names,'')) as mother_applicant"),
                            'm.property_lga as lga',
                            'm.property_district as district',
                            'm.plot_size as size',
                            'm.property_plot_no as plotNumber',
                            's.created_at'
                        )
                        ->get();

                    // Get pending Sectional Titling instruments
                    $pendingSectionalTitling = DB::connection('sqlsrv')->table('subapplications as s')
                        ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                        ->leftJoin('registered_instruments as ri', function ($join) {
                            $join->on('s.fileno', '=', 'ri.StFileNo')
                                ->where('ri.instrument_type', '=', 'Sectional Titling CofO')
                                ->where('ri.status', '=', 'registered');
                        })
                        ->where('s.planning_recommendation_status', 'Approved')
                        ->where('s.application_status', 'Approved')
                        ->whereNull('ri.id') // Only get subapplications without existing Sectional Titling registration
                        ->select(
                            's.id',
                            's.fileno',
                            's.deeds_completion_status',
                            DB::raw("CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.surname,''), COALESCE(s.corporate_name,''), COALESCE(s.multiple_owners_names,'')) as sub_applicant"),
                            DB::raw("CONCAT(COALESCE(m.applicant_title,''), ' ', COALESCE(m.first_name,''), ' ', COALESCE(m.surname,''), COALESCE(m.corporate_name,''), COALESCE(m.multiple_owners_names,'')) as mother_applicant"),
                            'm.property_lga as lga',
                            'm.property_district as district',
                            'm.plot_size as size',
                            'm.property_plot_no as plotNumber',
                            's.created_at'
                        )
                        ->get();

                    // Create collections for ST Assignment and Sectional Titling data
                    $stAssignmentData = collect();
                    $sectionalTitlingData = collect();

                    foreach ($pendingSTAssignments as $subApp) {
                        $stAssignmentData->push((object) [
                            'id' => $subApp->id . '_st_assignment',
                            'fileno' => $subApp->fileno,
                            'instrument_type' => 'ST Assignment (Transfer of Title)',
                            'grantor' => $subApp->mother_applicant,
                            'grantee' => $subApp->sub_applicant,
                            'lga' => $subApp->lga,
                            'district' => $subApp->district,
                            'size' => $subApp->size,
                            'plotNumber' => $subApp->plotNumber,
                            'created_at' => $subApp->created_at,
                            'status' => 'pending',
                            'source_type' => 'ST Assignment',
                            'original_subapp_id' => $subApp->id
                        ]);
                    }

                    foreach ($pendingSectionalTitling as $subApp) {
                        $sectionalTitlingData->push((object) [
                            'id' => $subApp->id . '_sectional_cofo',
                            'fileno' => $subApp->fileno,
                            'instrument_type' => 'Sectional Titling CofO',
                            'grantor' => 'Kano State Government',
                            'grantee' => $subApp->sub_applicant,
                            'lga' => $subApp->lga,
                            'district' => $subApp->district,
                            'size' => $subApp->size,
                            'plotNumber' => $subApp->plotNumber,
                            'created_at' => $subApp->created_at,
                            'status' => 'pending',
                            'source_type' => 'Sectional Titling',
                            'original_subapp_id' => $subApp->id
                        ]);
                    }

                    $capturedBatchQuery = DB::connection('sqlsrv')->table('instrument_capture')
                        ->where(function ($q) {
                            $q->where('is_deed_registered', 0)
                                ->orWhereNull('is_deed_registered');
                        });

                    if ($isStDeeds) {
                        $capturedBatchQuery->where(function ($q) {
                            $q->where('mlsFNo', 'like', 'ST-%')
                              ->orWhere('kangisFileNo', 'like', 'ST-%')
                              ->orWhere('NewKANGISFileno', 'like', 'ST-%')
                              ->orWhere('temp_fileno', 'like', 'ST-%');
                        });
                    }

                    $capturedInstruments = $capturedBatchQuery->select(
                            'id',
                            DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno) as fileno"),
                            'instrument_type',
                            'party_1_name as grantor',
                            'party_2_name as grantee',
                            'lga',
                            'district',
                            'plot_size as size',
                            'plot_number as plotNumber',
                            'created_at',
                            'op_type',
                            DB::raw("'pending' as status"),
                            DB::raw("'Captured' as source_type")
                        )
                        ->get();

                    $capturedData = collect();
                    foreach ($capturedInstruments as $cap) {
                        $capturedData->push((object) [
                            'id' => 'instr_capture_' . $cap->id,
                            'fileno' => $cap->fileno,
                            'instrument_type' => $this->mapCaptureTypeToRegistry($cap->instrument_type),
                            'grantor' => $cap->grantor,
                            'grantee' => $cap->grantee,
                            'lga' => $cap->lga,
                            'district' => $cap->district,
                            'size' => $cap->size,
                            'plotNumber' => $cap->plotNumber,
                            'created_at' => $cap->created_at,
                            'status' => 'pending',
                            'source_type' => 'Captured',
                            'op_type' => $cap->op_type ?? null,
                        ]);
                    }

                    $data = $instrumentData->merge($stAssignmentData)->merge($sectionalTitlingData)->merge($capturedData);
                    break;
            }

            // FINAL FILTER: Ensure all returned data has 'pending' status
            $data = $data->filter(function ($item) {
                return $item->status === 'pending';
            });

            return response()->json($data->values()->toArray());

        } catch (\Exception $e) {
            Log::error('Error in getBatchData', ['filter' => $request->query('filter'), 'exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to fetch batch data: ' . $e->getMessage()], 500);
        }
    }

    public function declineRegistration(Request $request)
    {
        try {
            $request->validate([
                'application_id' => 'required',
                'decline_reason' => 'required|string|max:500'
            ]);

            $applicationId = $request->application_id;
            $sourceRecord = null;
            $sourceTable = null;

            // Handle composite IDs for ST Assignment and Sectional Titling
            if (strpos($applicationId, '_st_assignment') !== false || strpos($applicationId, '_sectional_cofo') !== false) {
                $originalId = str_replace(['_st_assignment', '_sectional_cofo'], '', $applicationId);
                $sourceRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $originalId)->first();
                if ($sourceRecord) {
                    $sourceTable = 'subapplications';
                    $sourceRecord->original_id = $originalId;
                }
            } elseif (strpos($applicationId, 'instr_reg_') === 0) {
                // Handle instrument_registration IDs that start with 'instr_reg_'
                $originalId = str_replace('instr_reg_', '', $applicationId);
                $sourceRecord = DB::connection('sqlsrv')->table('instrument_registration')->where('id', $originalId)->first();
                if ($sourceRecord) {
                    $sourceTable = 'instrument_registration';
                }
            } else {
                // Only check subapplications if the ID is numeric
                if (is_numeric($applicationId)) {
                    $sourceRecord = DB::connection('sqlsrv')->table('subapplications')->where('id', $applicationId)->first();
                    if ($sourceRecord) {
                        $sourceTable = 'subapplications';
                    }
                }

                // Both id columns are ints. A composite id that reached here
                // unrecognised (e.g. "mother_assign_1028") would otherwise be
                // compared against them and surface as a raw SQL conversion error.
                if (!isset($sourceRecord) && is_numeric($applicationId)) {
                    $sourceRecord = DB::connection('sqlsrv')->table('instrument_registration')->where('id', $applicationId)->first();
                    if ($sourceRecord) {
                        $sourceTable = 'instrument_registration';
                    } else {
                        $sourceRecord = DB::connection('sqlsrv')->table('mother_applications')->where('id', $applicationId)->first();
                        if ($sourceRecord) {
                            $sourceTable = 'mother_applications';
                        }
                    }
                }
            }

            if (!$sourceRecord) {
                return response()->json(['success' => false, 'error' => 'Source record not found'], 404);
            }

            // Update status using original ID if it's a composite ID
            $updateId = isset($sourceRecord->original_id) ? $sourceRecord->original_id : $applicationId;

            // For instrument_registration IDs, we need to extract the numeric part
            if ($sourceTable === 'instrument_registration' && strpos($updateId, 'instr_reg_') === 0) {
                $updateId = str_replace('instr_reg_', '', $updateId);
            }

            $updateData = [
                'deeds_status' => 'declined',
                'decline_reason' => $request->decline_reason,
                'updated_by' => Auth::id(),
                'updated_at' => now()
            ];

            switch ($sourceTable) {
                case 'instrument_registration':
                    $updateData['status'] = 'declined';
                    DB::connection('sqlsrv')->table('instrument_registration')->where('id', $updateId)->update($updateData);
                    break;

                case 'mother_applications':
                    DB::connection('sqlsrv')->table('mother_applications')->where('id', $updateId)->update($updateData);
                    break;

                case 'subapplications':
                    DB::connection('sqlsrv')->table('subapplications')->where('id', $updateId)->update($updateData);
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration declined successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in declineRegistration', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'error' => 'Failed to decline registration: ' . $e->getMessage()], 500);
        }
    }

    public function getOverallCompletionStatus(Request $request)
    {
        try {
            // Get counts for different statuses
            $totalInstruments = DB::connection('sqlsrv')->table('subapplications')
                ->where('planning_recommendation_status', 'Approved')
                ->where('application_status', 'Approved')
                ->count() * 2; // Each subapplication has 2 instruments (ST Assignment + Sectional Titling)

            $registeredInstruments = DB::connection('sqlsrv')->table('registered_instruments')
                ->whereIn('instrument_type', ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])
                ->where('status', 'registered')
                ->count();

            $pendingInstruments = $totalInstruments - $registeredInstruments;

            $completionPercentage = $totalInstruments > 0 ? round(($registeredInstruments / $totalInstruments) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'total_instruments' => $totalInstruments,
                'registered_instruments' => $registeredInstruments,
                'pending_instruments' => $pendingInstruments,
                'completion_percentage' => $completionPercentage
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting overall completion status', [
                'exception' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get completion status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getFileCompletionStatus(Request $request)
    {
        try {
            $fileNo = $request->query('file_no');

            if (empty($fileNo)) {
                return response()->json([
                    'success' => false,
                    'error' => 'File number is required'
                ], 400);
            }

            // Get the subapplication for this file number
            $subApplication = DB::connection('sqlsrv')->table('subapplications')
                ->where('fileno', $fileNo)
                ->first();

            if (!$subApplication) {
                return response()->json([
                    'success' => false,
                    'error' => 'File not found'
                ], 404);
            }

            // Check registration status for both instruments
            $registrations = DB::connection('sqlsrv')->table('registered_instruments')
                ->where('StFileNo', $fileNo)
                ->whereIn('instrument_type', ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'])
                ->select('instrument_type', 'status')
                ->get();

            $stAssignmentRegistered = $registrations->contains('instrument_type', 'ST Assignment (Transfer of Title)');
            $sectionalTitlingRegistered = $registrations->contains('instrument_type', 'Sectional Titling CofO');

            $completionStatus = [
                'instruments' => [
                    [
                        'name' => 'ST Assignment (Transfer of Title)',
                        'status' => $stAssignmentRegistered ? 'Registered' : 'Pending'
                    ],
                    [
                        'name' => 'Sectional Titling CofO',
                        'status' => $sectionalTitlingRegistered ? 'Registered' : 'Pending'
                    ]
                ]
            ];

            $overallStatus = ($stAssignmentRegistered && $sectionalTitlingRegistered) ? 'Completed' : 'In Progress';
            $completionPercentage = (($stAssignmentRegistered ? 1 : 0) + ($sectionalTitlingRegistered ? 1 : 0)) / 2 * 100;

            return response()->json([
                'success' => true,
                'file_no' => $fileNo,
                'overall_status' => $overallStatus,
                'completion_percentage' => $completionPercentage,
                'completion_details' => $completionStatus
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting file completion status', [
                'file_no' => $request->query('file_no'),
                'exception' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get file completion status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tell the party their instrument has been registered.
     *
     * The registration particulars are read off the row that was just written,
     * never off the request: serial, page and volume are minted inside
     * InstrumentRegistrationService from the instrument's own number vault, so
     * the request has no idea what they will be.
     *
     * WHO IS TEXTED: the grantee, on instrument_capture.party_2_phone. Those
     * columns exist and are almost entirely empty today (3 populated rows, none
     * of them a valid number), so in practice this message will resolve through
     * the file until Deeds starts capturing numbers -- which is exactly what the
     * skipped rows in sms_dispatch_logs will show.
     */
    private function sendDeedRegisteredSms($record, $request): void
    {
        if (!$record) {
            return;
        }

        try {
            $key = \App\Models\SmsSetting::KEY_DEED_REGISTERED;
            $dispatcher = app(\App\Services\Sms\KlaesSmsDispatcher::class);

            if (!$dispatcher->enabled($key)) {
                return;
            }

            $fileNumber = trim((string) ($record->fileno ?? ''));

            // The officer may type a number on the registration screen; the
            // grantee's captured number is the next best thing, then the file.
            $phone = trim((string) ($request->input('applicant_phone') ?? ''));

            if ($phone === '') {
                $phone = (string) $this->deedPartyPhone($record->instrument_capture_id ?? null);
            }

            if (trim($phone) === '' && $fileNumber !== '') {
                $phone = (string) app(\App\Services\Sms\ApplicantPhoneResolver::class)
                    ->forFileNumber($fileNumber)['phone'];
            }

            $tokens = [
                'Instrument Type' => $record->instrument_type ?? 'instrument',
                'Registration Particulars' => $this->deedParticulars($record),
                'DateTime' => $this->deedMoment($record),
                'FileNo' => $fileNumber,
            ];
            $event = [
                // Keyed on the registration row: registering a SECOND instrument
                // on the same file is a separate event the party should hear about.
                'dedupe_key' => $key . ':' . ($record->id ?? $fileNumber),
                'file_number' => $fileNumber,
                'subject_type' => 'deed_registrations',
                'subject_id' => $record->id ?? null,
                'event_at' => $record->deeds_date ?? null,
                'phone_confirmed' => (bool) $request->input('phone_confirmed', false),
            ];

            $dispatcher->queue($key, $phone, $tokens, $event);

            // A solicitor who supplied a validated phone number at capture is also
            // entitled to the same registration notice. This uses the identical
            // Instrument Registered SMS template, with a recipient-specific dedupe key.
            $solicitorPhone = $this->deedSolicitorPhone($record->instrument_capture_id ?? null);
            if ($solicitorPhone !== null && $solicitorPhone !== '' && $solicitorPhone !== $phone) {
                $dispatcher->queue($key, $solicitorPhone, $tokens, array_merge($event, [
                    'dedupe_key' => $key . ':solicitor:' . ($record->id ?? $fileNumber),
                ]));
            }
        } catch (\Throwable $e) {
            Log::warning('InstrumentRegistrationController: deed registration SMS not queued', [
                'record_id' => $record->id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /** One message per instrument registered in a batch. */
    private function sendBatchDeedRegisteredSms(array $results): void
    {
        if (empty($results)) {
            return;
        }

        try {
            if (!app(\App\Services\Sms\KlaesSmsDispatcher::class)
                ->enabled(\App\Models\SmsSetting::KEY_DEED_REGISTERED)) {
                return;
            }

            $ids = array_filter(array_column($results, 'new_id'));

            if (empty($ids)) {
                return;
            }

            $rows = DB::connection('sqlsrv')->table('deed_registrations')
                ->whereIn('id', $ids)
                ->get();

            // The batch screen captures no per-entry phone number, so this goes
            // through the same resolution chain with an empty request value.
            $blank = new \Illuminate\Http\Request();

            foreach ($rows as $row) {
                $this->sendDeedRegisteredSms($row, $blank);
            }
        } catch (\Throwable $e) {
            Log::warning('InstrumentRegistrationController: batch deed registration SMS not queued', [
                'count' => count($results),
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * "41/41/2026" -- serial, page and volume as the Deeds Registry writes them.
     *
     * Falls back to the whole registration number when any part is missing,
     * because a message that says "with Registration Particulars //" tells the
     * party nothing they can quote at the counter.
     */
    private function deedParticulars($record): string
    {
        $serial = trim((string) ($record->serial_no ?? ''));
        $page = trim((string) ($record->page_no ?? ''));
        $volume = trim((string) ($record->volume_no ?? ''));

        if ($serial !== '' && $page !== '' && $volume !== '') {
            return $serial . '/' . $page . '/' . $volume;
        }

        return trim((string) ($record->registration_number ?? ''));
    }

    /** The registration moment, on the office clock rather than UTC. */
    private function deedMoment($record): string
    {
        $timezone = config('klaes_sms.timezone', 'Africa/Lagos');
        $format = config('klaes_sms.datetime_format', 'd/m/Y h:i A');

        try {
            $date = $record->deeds_date ?? $record->created_at ?? null;
            $time = trim((string) ($record->deeds_time ?? ''));

            $moment = $date
                ? \Carbon\Carbon::parse(trim($date . ' ' . $time))
                : \Carbon\Carbon::now();

            return $moment->setTimezone($timezone)->format($format);
        } catch (\Throwable $e) {
            return \Carbon\Carbon::now($timezone)->format($format);
        }
    }

    /** The grantee's captured phone number, if the capture row holds one. */
    private function deedPartyPhone($instrumentCaptureId): ?string
    {
        if (!$instrumentCaptureId) {
            return null;
        }

        try {
            $row = DB::connection('sqlsrv')->table('instrument_capture')
                ->select('party_1_phone', 'party_2_phone')
                ->where('id', $instrumentCaptureId)
                ->first();

            // Party 2 is the grantee -- the person acquiring the interest, and
            // the one waiting to hear the instrument is registered.
            return trim((string) ($row->party_2_phone ?? '')) !== ''
                ? (string) $row->party_2_phone
                : (trim((string) ($row->party_1_phone ?? '')) !== '' ? (string) $row->party_1_phone : null);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Solicitor phone captured on the originating instrument, if any. */
    private function deedSolicitorPhone($instrumentCaptureId): ?string
    {
        if (!$instrumentCaptureId) {
            return null;
        }

        try {
            $phone = DB::connection('sqlsrv')->table('instrument_capture')
                ->where('id', $instrumentCaptureId)
                ->value('solicitor_phone');

            $phone = trim((string) $phone);
            return $phone === '' ? null : $phone;
        } catch (\Throwable $e) {
            Log::warning('InstrumentRegistrationController: solicitor phone could not be read', [
                'instrument_capture_id' => $instrumentCaptureId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function updateSourceRecordStatus($id, $sourceTable)
    {
        $updateData = [
            'updated_by' => Auth::id(),
            'updated_at' => now()
        ];

        switch ($sourceTable) {
            case 'instrument_registration':
                $updateData['status'] = 'registered';
                DB::connection('sqlsrv')->table('instrument_registration')->where('id', $id)->update($updateData);
                break;

            case 'mother_applications':
                $updateData['deeds_status'] = 'registered';
                DB::connection('sqlsrv')->table('mother_applications')->where('id', $id)->update($updateData);
                break;

            case 'subapplications':
                $updateData['deeds_status'] = 'registered';
                DB::connection('sqlsrv')->table('subapplications')->where('id', $id)->update($updateData);
                break;

            case 'instrument_capture':
                $updateData['is_deed_registered'] = 1;
                // Also sync registration particulars back to capture table for redundancy/history
                // Note: We need the registration number etc. from the newly created record
                $newReg = DB::connection('sqlsrv')->table('deed_registrations')
                    ->where('fileno', DB::connection('sqlsrv')->table('instrument_capture')->where('id', $id)->value('mlsFNo')
                        ?? DB::connection('sqlsrv')->table('instrument_capture')->where('id', $id)->value('kangisFileNo')
                        ?? DB::connection('sqlsrv')->table('instrument_capture')->where('id', $id)->value('NewKANGISFileno')
                        ?? DB::connection('sqlsrv')->table('instrument_capture')->where('id', $id)->value('temp_fileno')) // This is a bit loose, better to pass details
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($newReg) {
                    $updateData['registration_number'] = $newReg->registration_number;
                    $updateData['volume_no'] = $newReg->volume_no;
                    $updateData['page_no'] = $newReg->page_no;
                    $updateData['serial_no'] = $newReg->serial_no;
                    $updateData['reg_date'] = $newReg->deeds_date;
                }

                DB::connection('sqlsrv')->table('instrument_capture')->where('id', $id)->update($updateData);
                break;
        }
    }

    public function exportRegistry(Request $request)
    {
        try {
            $instrumentType = $request->query('instrument_type');
            $volumeNo = $request->query('volume_no');

            $query = DB::connection('sqlsrv')->table('deed_registrations as dr')
                ->leftJoin('instrument_capture as ic', 'dr.instrument_capture_id', '=', 'ic.id')
                ->select(
                    'dr.*',
                    'ic.party_3_name',
                    'ic.party_4_name',
                    'ic.op_serial_number',
                    'ic.property_description as captured_description'
                );

            if ($instrumentType) {
                if (strpos($instrumentType, ',') !== false) {
                    $types = explode(',', $instrumentType);
                    $query->whereIn('dr.instrument_type', $types);
                } elseif ($instrumentType === 'Occupancy Permit (OP)') {
                    $query->where('dr.instrument_type', 'Occupancy Permit (OP)');
                } elseif (str_starts_with($instrumentType, 'OP ')) {
                    $opType = str_replace('OP ', '', $instrumentType);
                    $query->where('dr.instrument_type', 'Occupancy Permit (OP)')
                          ->where(function($q) use ($instrumentType, $opType) {
                              $q->where('ic.op_type', $instrumentType)
                                ->orWhere('ic.op_type', $opType);
                          });
                } else {
                    $query->where('dr.instrument_type', $instrumentType);
                }
            }

            if ($volumeNo) {
                $query->where('dr.volume_no', $volumeNo);
            }

            $results = $query->orderBy('dr.volume_no', 'asc')
                ->orderBy('dr.page_no', 'asc')
                ->orderBy('dr.serial_no', 'asc')
                ->get();

            $data = $results->map(function ($item, $index) {
                return [
                    'SN' => $index + 1,
                    'fileno' => $item->fileno,
                    'serialNo' => $item->serial_no,
                    'pageNo' => $item->page_no,
                    'volumeNo' => $item->volume_no,
                    'reg_particulars' => $item->registration_number,
                    'party_1' => $item->grantor,
                    'party_2' => $item->grantee,
                    'party_3' => $item->party_3_name ?? 'N/A',
                    'party_4' => $item->party_4_name ?? 'N/A',
                    'deed_time' => $item->deeds_time,
                    'deed_date' => $item->deeds_date,
                    'op_serial' => $item->op_serial_number ?? 'N/A',
                    'location' => $item->captured_description ?? trim(($item->lga ? $item->lga . ', ' : '') . ($item->district ? $item->district . ', ' : '') . ($item->plot_number ? 'Plot ' . $item->plot_number . ', ' : '') . ($item->property_description ?? ''))
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            \Log::error('Error exporting registry', ['exception' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
