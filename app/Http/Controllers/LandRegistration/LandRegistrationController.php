<?php

namespace App\Http\Controllers\LandRegistration;

use App\Http\Controllers\Controller;
use App\Services\InstrumentRegistrationService;
use App\Services\TimelineWeightingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Instrument Registration for the Land Registry.
 *
 * The register itself: every Deed of Purchase this registry holds, in
 * Serial/Page/Volume order, with the RDS and CoR actions beside each row.
 *
 * A note on what "registration" means on this screen. Capture already registers:
 * InstrumentCaptureService reserves the number from the vault and writes the
 * matching deed_registrations row in the same sqlsrv transaction, so a record
 * captured normally arrives here already numbered. This screen therefore reads
 * as a register rather than a queue - and registerSingle() exists for the one
 * case that does reach it unnumbered, a capture saved as a temporary
 * registration (is_temporary_reg), which deliberately holds its number back.
 *
 * Everything is scoped to config('land_registration.instrument_type'). None of
 * the ST fragmentation / sectional / OP machinery on the Deeds registration
 * screen applies here, so none of it is carried across.
 */
class LandRegistrationController extends Controller
{
    public function __construct(
        protected InstrumentRegistrationService $registrationService
    ) {
    }

    private function instrumentType(): string
    {
        return (string) config('land_registration.instrument_type');
    }

    /**
     * The register. Two sources, both scoped to this registry's type:
     * registered rows out of deed_registrations, and any capture still waiting
     * for a number.
     */
    public function index()
    {
        $type = $this->instrumentType();
        $PageTitle = 'Land Registration - Instrument Registration';
        $PageDescription = '';

        // receipt_no arrives with the Land Registration install and the capture
        // table predates it, so the register must not hard-fail where it has not
        // been applied yet.
        $hasReceiptNo = Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'receipt_no');

        try {
            $registered = DB::connection('sqlsrv')->table('deed_registrations as dr')
                ->leftJoin('users as u', 'dr.created_by', '=', 'u.id')
                ->leftJoin('instrument_capture as ic', 'dr.instrument_capture_id', '=', 'ic.id')
                ->where('dr.instrument_type', $type)
                ->select(
                    'dr.*',
                    'dr.rds_exists as rds_exists_db',
                    'dr.cor_exists as cor_exists_db',
                    'ic.party_1_address',
                    'ic.party_2_address',
                    // When the deed was CAPTURED, as distinct from when it was
                    // registered: the register shows both, and on a record held
                    // back as a temporary registration they are different days.
                    'ic.created_at as captured_at',
                    // What was paid. Both live on the capture, not the registration.
                    'ic.consideration_amount',
                    DB::raw($hasReceiptNo ? 'ic.receipt_no' : "CAST(NULL AS varchar(100)) as receipt_no"),
                    DB::raw("CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as reg_creator_name")
                )
                ->get()
                ->map(function ($row) {
                    return (object) [
                        'id' => 'deed_reg_' . $row->id,
                        'registered_instrument_id' => 'deed_reg_' . $row->id,
                        'capture_id' => $row->instrument_capture_id,
                        'fileno' => $row->fileno,
                        'registration_number' => $row->registration_number,
                        'serial_no' => $row->serial_no,
                        'page_no' => $row->page_no,
                        'volume_no' => $row->volume_no,
                        'vendor' => $row->grantor ?? 'N/A',
                        'purchaser' => $row->grantee ?? 'N/A',
                        'vendor_address' => $row->party_1_address ?? '',
                        'purchaser_address' => $row->party_2_address ?? '',
                        'property_description' => $row->property_description ?? '',
                        'lga' => $row->lga ?? '',
                        'district' => $row->district ?? '',
                        'plot_number' => $row->plot_number ?? '',
                        'size' => $row->size ?? '',
                        'deeds_date' => $row->deeds_date,
                        'deeds_time' => $row->deeds_time,
                        // The date on the deed itself, kept apart from the day
                        // the registry numbered it. deed_registrations stores it
                        // as instrument_date.
                        'transaction_date' => $row->instrument_date,
                        'prop_id' => $row->prop_id ?? null,
                        // Captured falls back to the registration's own timestamp
                        // for rows written before the capture link existed.
                        'captured_at' => $row->captured_at ?? $row->created_at,
                        'amount' => $row->consideration_amount,
                        'receipt_no' => $row->receipt_no,
                        'status' => 'registered',
                        'created_at' => $row->created_at,
                        'reg_creator_name' => $row->reg_creator_name ?? '',
                        'rds_exists' => (bool) ($row->rds_exists_db ?? false),
                        'cor_exists' => (bool) ($row->cor_exists_db ?? false),
                    ];
                });

            // Captures still holding their number back. Left-joined against
            // deed_registrations so a row that IS registered cannot appear twice.
            $pending = DB::connection('sqlsrv')->table('instrument_capture as ic')
                ->leftJoin('deed_registrations as dr', 'dr.instrument_capture_id', '=', 'ic.id')
                ->leftJoin('users as u', 'ic.created_by', '=', 'u.id')
                ->where('ic.instrument_type', $type)
                ->whereNull('dr.id')
                ->where(function ($q) {
                    $q->where('ic.is_deleted', 0)->orWhereNull('ic.is_deleted');
                })
                ->select(
                    'ic.*',
                    DB::raw("CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as reg_creator_name")
                )
                ->get()
                ->map(function ($row) {
                    return (object) [
                        'id' => 'instr_capture_' . $row->id,
                        'registered_instrument_id' => null,
                        'capture_id' => $row->id,
                        'fileno' => $row->mlsFNo ?? $row->kangisFileNo ?? $row->NewKANGISFileno ?? $row->temp_fileno ?? 'N/A',
                        'registration_number' => null,
                        'serial_no' => null,
                        'page_no' => null,
                        'volume_no' => null,
                        'vendor' => $row->party_1_name ?? 'N/A',
                        'purchaser' => $row->party_2_name ?? 'N/A',
                        'vendor_address' => $row->party_1_address ?? '',
                        'purchaser_address' => $row->party_2_address ?? '',
                        'property_description' => $row->property_description ?? '',
                        'lga' => $row->lga ?? '',
                        'district' => $row->district ?? '',
                        'plot_number' => $row->plot_number ?? '',
                        'size' => $row->size ?? '',
                        'deeds_date' => $row->reg_date,
                        'deeds_time' => null,
                        'transaction_date' => $row->instrument_date ?? null,
                        'prop_id' => $row->prop_id ?? null,
                        'captured_at' => $row->created_at,
                        // This leg selects ic.*, so an environment without the
                        // receipt column simply has no such property.
                        'amount' => $row->consideration_amount ?? null,
                        'receipt_no' => $row->receipt_no ?? null,
                        'status' => 'pending',
                        'created_at' => $row->created_at,
                        'reg_creator_name' => $row->reg_creator_name ?? '',
                        'rds_exists' => false,
                        'cor_exists' => false,
                    ];
                });

            // Registry order: volume, then serial within it. Pending rows have no
            // number yet, so they sort to the top where they can be acted on.
            $instruments = $pending->concat($registered)
                ->sortBy([
                    fn ($a, $b) => ((int) $a->volume_no) <=> ((int) $b->volume_no),
                    fn ($a, $b) => ((int) $a->serial_no) <=> ((int) $b->serial_no),
                ])
                ->values();

            $totalCount = $instruments->count();

            // Registered today, by the registry date printed on the documents
            // (deeds_date) rather than the row's created_at, so it counts what
            // the register itself says was registered today.
            $today = now()->toDateString();
            $registeredTodayCount = $registered
                ->filter(fn ($row) => $row->deeds_date
                    && \Carbon\Carbon::parse($row->deeds_date)->toDateString() === $today)
                ->count();

            // What the next capture will be numbered, so the register shows where
            // the series has reached without consuming anything.
            $nextNumber = $this->registrationService->peekRegistrationNumber($this->instrumentType());

            // Timeline counts for the Timeline column, batched: one pass for the
            // whole register rather than four queries per row.
            $timelineCounts = app(TimelineWeightingService::class)->getWeightedCountsForMany(
                collect($instruments)->mapWithKeys(fn ($r) => [$r->id => [
                    'fileNumber' => $r->fileno,
                    'propId' => (string) ($r->prop_id ?? ''),
                ]])->all()
            );

            return view('land_registration.registration.index', compact(
                'PageTitle',
                'PageDescription',
                'instruments',
                'registeredTodayCount',

                'totalCount',
                'nextNumber',
                'timelineCounts'
            ));
        } catch (\Throwable $e) {
            Log::error('Error loading the Land Registration register', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            $instruments = collect();
            $totalCount = $registeredTodayCount = 0;
            $nextNumber = ['configured' => false];
            $timelineCounts = [];

            return view('land_registration.registration.index', compact(
                'PageTitle',
                'PageDescription',
                'instruments',
                'registeredTodayCount',

                'totalCount',
                'nextNumber',
                'timelineCounts'
            ))->with('error', 'Error loading the register: ' . $e->getMessage());
        }
    }

    /**
     * Number a capture that was held back, and write its deed_registrations row.
     *
     * The id arrives prefixed, the way the Deeds screen passes ids around:
     * "instr_capture_123". Anything already registered is refused rather than
     * numbered twice - a second number on one deed is not recoverable once the
     * certificate is printed.
     */
    public function registerSingle(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|string']);

        $rawId = (string) $request->input('id');
        $captureId = (int) preg_replace('/\D/', '', $rawId);

        if (!$captureId) {
            return response()->json(['success' => false, 'message' => 'A capture id is required.'], 422);
        }

        $capture = DB::connection('sqlsrv')->table('instrument_capture')
            ->where('id', $captureId)
            ->where('instrument_type', $this->instrumentType())
            ->first();

        if (!$capture) {
            return response()->json([
                'success' => false,
                'message' => 'That record is not a Land Registry capture.',
            ], 404);
        }

        $already = DB::connection('sqlsrv')->table('deed_registrations')
            ->where('instrument_capture_id', $captureId)
            ->first();

        if ($already) {
            return response()->json([
                'success' => false,
                'message' => 'This deed is already registered as ' . $already->registration_number . '.',
            ], 422);
        }

        $fileNo = $capture->mlsFNo ?? $capture->kangisFileNo ?? $capture->NewKANGISFileno ?? $capture->temp_fileno;

        try {
            // One sqlsrv transaction around the whole thing. The vault takes its
            // row lock on this connection, so the lock only actually holds while
            // the surrounding transaction is on the same connection - which is
            // why this is opened here and not left to the service.
            $result = DB::connection('sqlsrv')->transaction(function () use ($capture, $captureId, $fileNo) {
                $numberData = $this->registrationService->getRegistrationNumber($this->instrumentType());

                $registration = $this->registrationService->registerInstrument([
                    'instrument_capture_id' => $captureId,
                    'fileno' => $fileNo,
                    'instrument_type' => $this->instrumentType(),
                    'instrument_date' => $capture->reg_date ?? now()->format('Y-m-d'),
                    'grantor' => $capture->party_1_name,
                    'grantee' => $capture->party_2_name,
                    'lga' => $capture->lga ?? null,
                    'district' => $capture->district ?? null,
                    'plot_number' => $capture->plot_number ?? null,
                    'size' => $capture->size ?? null,
                    'property_description' => $capture->property_description ?? null,
                    'prop_id' => $capture->prop_id ?? null,
                ], $numberData);

                DB::connection('sqlsrv')->table('instrument_capture')
                    ->where('id', $captureId)
                    ->update([
                        'registration_number' => $numberData['formatted'],
                        'volume_no' => $numberData['volume'],
                        'page_no' => $numberData['page'],
                        'serial_no' => $numberData['serial'],
                        'is_deed_registered' => 1,
                        'is_temporary_reg' => 0,
                        'updated_at' => now(),
                    ]);

                return [
                    'deed_registration_id' => $registration['id'],
                    'registration_number' => $numberData['formatted'],
                ];
            });

            Log::info('Land Registration: deed registered', [
                'capture_id' => $captureId,
                'fileno' => $fileNo,
                'registration_number' => $result['registration_number'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registered as ' . $result['registration_number'] . '.',
                'registered_instrument_id' => 'deed_reg_' . $result['deed_registration_id'],
                'registration_number' => $result['registration_number'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Land Registration: registration failed', [
                'capture_id' => $captureId,
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not register this deed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * One registered deed, in full.
     */
    public function view($id)
    {
        $realId = (int) preg_replace('/\D/', '', (string) $id);

        $registration = DB::connection('sqlsrv')->table('deed_registrations as dr')
            ->leftJoin('instrument_capture as ic', 'dr.instrument_capture_id', '=', 'ic.id')
            ->where('dr.id', $realId)
            ->where('dr.instrument_type', $this->instrumentType())
            ->select(
                'dr.*',
                'ic.party_1_address',
                'ic.party_2_address',
                'ic.solicitor_name',
                'ic.solicitor_address',
                // Amount and receipt live on the capture, not the registration.
                'ic.consideration_amount',
                'ic.receipt_no'
            )
            ->first();

        if (!$registration) {
            return redirect()->route('land-registration.registration.index')
                ->with('error', 'Registration not found in the Land Registry.');
        }

        $PageTitle = 'Deed of Purchase ' . $registration->registration_number;
        $PageDescription = '';

        return view('land_registration.registration.view', compact('PageTitle', 'PageDescription', 'registration'));
    }

    /**
     * The next number, without consuming it.
     */
    public function getNextSerialNumber(): JsonResponse
    {
        try {
            return response()->json(array_merge(
                ['success' => true],
                $this->registrationService->peekRegistrationNumber($this->instrumentType())
            ));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * Remove a registration.
     *
     * The number is NOT returned to the vault. A registry series is a record of
     * what was issued, not a pool of reusable numbers - reissuing a serial that
     * may already be printed on a certificate is the one thing that cannot be
     * undone. The gap is correct and intentional.
     */
    public function destroy($id): JsonResponse
    {
        $realId = (int) preg_replace('/\D/', '', (string) $id);

        $registration = DB::connection('sqlsrv')->table('deed_registrations')
            ->where('id', $realId)
            ->where('instrument_type', $this->instrumentType())
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'Registration not found in the Land Registry.',
            ], 404);
        }

        try {
            DB::connection('sqlsrv')->transaction(function () use ($registration) {
                DB::connection('sqlsrv')->table('deed_registrations')
                    ->where('id', $registration->id)
                    ->delete();

                if ($registration->instrument_capture_id) {
                    DB::connection('sqlsrv')->table('instrument_capture')
                        ->where('id', $registration->instrument_capture_id)
                        ->update([
                            'is_deed_registered' => 0,
                            'updated_at' => now(),
                        ]);
                }
            });

            Log::warning('Land Registration: registration removed', [
                'deed_registration_id' => $registration->id,
                'registration_number' => $registration->registration_number,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registration ' . $registration->registration_number . ' removed. '
                    . 'The number is not reissued - the gap in the series is intentional.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Land Registration: delete failed', [
                'deed_registration_id' => $realId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not remove this registration: ' . $e->getMessage(),
            ], 500);
        }
    }
}
