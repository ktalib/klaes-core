<?php

namespace App\Http\Controllers\LandsOneStopShop;

use App\Http\Controllers\Controller;
use App\Models\LossOfDocumentApplication;
use App\Models\StreetName;
use App\Services\ApplicantContactResolver;
use App\Services\Survey\OpLookup;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Loss of Document, Land FC list. OssLossOfDocumentController is the OSS list.
 *
 * Commissioning is separate, as with the other parcel updates: this records the application
 * and, for an OP, makes sure the OP has a number. An OP that is not in the system is refused
 * here; the officer has to capture it first.
 */
class LossOfDocumentController extends Controller
{
    protected const MODULE = LossOfDocumentApplication::MODULE_LAND;
    protected const VIEW = 'lands_one_stop_shop.loss_of_document';

    public function __construct(
        protected OpLookup $ops,
        protected ApplicantContactResolver $contacts
    ) {
    }

    public function index(Request $request): View
    {
        $limit = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $records = $this->records()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('op_serial_number', 'LIKE', "%{$search}%")
                        ->orWhere('location', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        return view(static::VIEW, compact('records', 'limit', 'states', 'lgas', 'districts', 'streetNames'));
    }

    /**
     * Every OP carrying this serial, linked to a file or not: serials repeat, so the officer
     * picks one. An empty list means the OP has to be captured first.
     */
    public function opSearch(Request $request): JsonResponse
    {
        $serial = trim((string) $request->query('serial'));
        if ($serial === '') {
            return response()->json(['success' => false, 'message' => 'Enter the OP serial number.'], 422);
        }

        $ops = array_map(fn (array $op) => $this->withPraDetails($op), $this->ops->exactSerial($serial));

        $contacts = $this->contacts->forFiles(array_column($ops, 'file_number'));

        $rows = array_map(function (array $op) use ($contacts) {
            $contact = $contacts[ApplicantContactResolver::key($op['file_number'])] ?? [];
            $op['phone'] = $op['phone'] ?: ($contact['phone'] ?? null);
            $op['address'] = $op['address'] ?: ($contact['address'] ?? null);

            return $op;
        }, $ops);

        return response()->json(['success' => true, 'count' => count($rows), 'data' => $rows]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->records()->findOrFail($id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->toArray());
        }

        try {
            $record = DB::connection('sqlsrv')->transaction(function () use ($request, $validator) {
                $data = $this->prepared($request, $validator->validated());
                $data['module'] = static::MODULE;
                $data['captured_by'] = Auth::id();
                $data['status'] = LossOfDocumentApplication::STATUS_PENDING;

                return LossOfDocumentApplication::create($data);
            });
        } catch (ValidationException $e) {
            return $this->invalid($e->errors());
        }

        return response()->json([
            'success' => true,
            'message' => 'Loss of document application captured successfully.',
            'data' => $record,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = $this->records()->findOrFail($id);

        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->toArray());
        }

        try {
            DB::connection('sqlsrv')->transaction(function () use ($request, $validator, $record) {
                $data = $this->prepared($request, $validator->validated());
                $data['updated_by'] = Auth::id();
                $record->update($data);
            });
        } catch (ValidationException $e) {
            return $this->invalid($e->errors());
        }

        return response()->json([
            'success' => true,
            'message' => 'Loss of document application updated successfully.',
            'data' => $record->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->records()->findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Loss of document application deleted successfully.',
        ]);
    }

    /** This list's rows only: a Land FC page never reaches an OSS row, or the reverse. */
    protected function records(): Builder
    {
        return LossOfDocumentApplication::query()->where('module', static::MODULE);
    }

    private function prepared(Request $request, array $data): array
    {
        unset($data['op_key'], $data['passport_photo']);

        foreach (['applicant_name', 'nationality', 'occupation', 'plot_no', 'plan_no', 'district', 'lga', 'land_use'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = Str::squish($data[$field]);
            }
        }
        if (isset($data['email'])) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }

        $data = array_merge($data, $data['document_kind'] === LossOfDocumentApplication::KIND_OP
            ? $this->opFields((string) $request->input('op_key'))
            : ['op_serial_number' => null, 'op_source' => null, 'op_id' => null, 'op_type' => null]);

        if ($request->hasFile('passport_photo')) {
            // The public disk (F:, served at /storage). The stored value keeps its
            // 'public/' prefix because the page turns 'public/' into '/storage/'.
            $data['passport_photo'] = 'public/' . $request->file('passport_photo')
                ->store('loss_of_document_passports', 'public');
        }

        return $data;
    }

    /**
     * The chosen OP's details, read back from the OP rather than trusted from the form. An
     * OP with no file number gets its temporary number here, on save, so that abandoning
     * the form never uses one up.
     */
    private function opFields(string $key): array
    {
        $op = $this->ops->find($key);
        if (! $op) {
            throw ValidationException::withMessages([
                'op_key' => 'That OP is not in the system. Capture the OP first.',
            ]);
        }
        $op = $this->withPraDetails($op);

        return [
            'op_serial_number' => $op['serial'],
            'op_source' => $op['source'],
            'op_id' => $op['id'],
            'op_type' => $op['type'],
            'file_no' => $op['file_number'] ?: $this->assignTemporaryNumber($op),
        ];
    }

    /**
     * What OpLookup does not read from pra: the LGA, and fileno, where many older OPs hold
     * their number. Without it such an OP looks numberless and would get a second number.
     */
    private function withPraDetails(array $op): array
    {
        if ($op['source'] !== 'pra') {
            return $op;
        }

        $row = DB::connection('sqlsrv')->table('pra')->where('id', $op['id'])->first(['fileno', 'lgsaOrCity']);
        $op['file_number'] = $op['file_number'] ?: (trim((string) ($row->fileno ?? '')) ?: null);
        $op['lga'] = $op['lga'] ?: (trim((string) ($row->lgsaOrCity ?? '')) ?: null);

        return $op;
    }

    /** Same allocation as the Capture OP card: a row marked used, so an id is never reissued. */
    private function assignTemporaryNumber(array $op): string
    {
        $db = DB::connection('sqlsrv');

        $sequence = $db->table('temp_fileno_sequence')->insertGetId([
            'created_by' => Auth::id(),
            'is_used' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $number = 'TEMP-' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);

        $blank = fn ($q) => $q->whereNull('temp_fileno')->orWhere('temp_fileno', '');

        if ($op['source'] === 'capture') {
            $db->table('instrument_capture')->where('id', $op['id'])->where($blank)->update(['temp_fileno' => $number]);
            // The pra mirror of a captured OP carries the same number.
            $db->table('pra')->where('instrument_capture_id', $op['id'])->where($blank)->update(['temp_fileno' => $number]);
        } else {
            $db->table('pra')->where('id', $op['id'])->where($blank)->update(['temp_fileno' => $number]);
        }

        Log::info('Loss of Document: temporary number given to an OP', [
            'op' => $op['key'],
            'temp_fileno' => $number,
            'user' => Auth::id(),
        ]);

        return $number;
    }

    private function invalid(array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => collect($errors)->flatten()->first() ?: 'Validation failed.',
            'errors' => $errors,
        ], 422);
    }

    private function rules(): array
    {
        return [
            'document_kind'           => 'required|in:regular,op',
            'op_key'                  => 'required_if:document_kind,op|nullable|string|max:40',
            'file_no'                 => 'required_if:document_kind,regular|nullable|string|max:255',
            'applicant_name'          => 'required|string|max:255',
            'location'                => 'nullable|string|max:2000',
            'residential_address'     => 'nullable|string|max:2000',
            'correspondence_address'  => 'nullable|string|max:2000',
            'nationality'             => 'nullable|string|max:255',
            'occupation'              => 'nullable|string|max:255',
            'phone'                   => 'nullable|string|max:50',
            'email'                   => 'nullable|email|max:255',
            'land_use'                => 'nullable|string|max:255',
            'plot_no'                 => 'nullable|string|max:100',
            'plan_no'                 => 'nullable|string|max:100',
            'district'                => 'nullable|string|max:255',
            'lga'                     => 'nullable|string|max:255',
            'remarks'                 => 'nullable|string',
            'passport_photo'          => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ];
    }
}
