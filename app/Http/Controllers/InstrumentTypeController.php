<?php

namespace App\Http\Controllers;

use App\Models\NewInstrumentType; // Registration Module (New)
use App\Models\InstrumentType as LegacyInstrumentType; // PRA Module (Legacy)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\InstrumentRegistrationService;

class InstrumentTypeController extends Controller
{
    public function __construct(protected InstrumentRegistrationService $registrationService)
    {
    }

    /**
     * Whether this deployment's vault table can record where a register began.
     *
     * start_volume / start_serial arrived after the table did, so every read and
     * write of them is guarded: a site that has not run
     * database/sql/2026_09_08_add_register_origin_to_instrument_number_vaults.sql
     * yet manages its vaults exactly as before, minus the two fields.
     */
    private function vaultHasOriginColumns(): bool
    {
        static $has = null;

        if ($has === null) {
            $has = Schema::connection('sqlsrv')->hasColumn('instrument_number_vaults', 'start_volume')
                && Schema::connection('sqlsrv')->hasColumn('instrument_number_vaults', 'start_serial');
        }

        return $has;
    }

    /**
     * The vault columns a create/update may set, keyed by the request field.
     *
     * The "last used" trio moves the vault - the next registration is issued
     * from it. The "start" pair does not move anything: it is the floor the
     * missing-number survey measures from, and is read only by
     * MissingRegistrationParticularsService.
     */
    private function vaultColumnMap(): array
    {
        $map = [
            'last_volume' => 'current_volume',
            'last_page' => 'current_page',
            'last_serial' => 'current_serial',
        ];

        if ($this->vaultHasOriginColumns()) {
            $map['start_volume'] = 'start_volume';
            $map['start_serial'] = 'start_serial';
        }

        return $map;
    }

    /**
     * Display a listing of the resource (For Instrument Registration Manager).
     * Uses NewInstrumentType.
     */
    public function index()
    {
        // Fetch all types (Registration Module)
        $types = NewInstrumentType::orderBy('name')->get();

        // Fetch all vaults to map data
        $vaults = DB::connection('sqlsrv')->table('instrument_number_vaults')->get()->keyBy('instrument_type');

        // Map vault data to types using resolved names
        $types->transform(function ($type) use ($vaults) {
            $vaultName = $this->registrationService->resolveVaultName($type->name);
            $vault = $vaults->get($vaultName);

            $type->last_volume = $vault->current_volume ?? null;
            $type->last_page = $vault->current_page ?? null;
            $type->last_serial = $vault->current_serial ?? null;
            // Where the register began, when the office has recorded it. Null
            // means the missing-number survey infers the floor from the lowest
            // surviving registration - which cannot see a hole beneath itself.
            $type->start_volume = $vault->start_volume ?? null;
            $type->start_serial = $vault->start_serial ?? null;
            $type->vault_source = $vaultName; // Expose the source vault name

            return $type;
        });

        return response()->json($types);
    }

    /**
     * Get all instrument types for dropdown/select options (For PRA / Legacy).
     * USES LEGACY TABLE.
     */
    public function getAll()
    {
        $instrumentTypes = LegacyInstrumentType::active()
            ->orderBy('InstrumentName')
            ->get(['InstrumentTypeID', 'InstrumentName', 'Description'])
            ->map(function ($type) {
                return [
                    'id' => $type->InstrumentTypeID,
                    'name' => $type->InstrumentName,
                    'description' => $type->Description
                ];
            })
            ->values();

        return response()->json($instrumentTypes);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:sqlsrv.new_instrument_types,name',
            'description' => 'nullable|string',
            'last_volume' => 'nullable|integer|min:0',
            'last_page' => 'nullable|integer|min:0',
            'last_serial' => 'nullable|integer|min:0',
            'start_volume' => 'nullable|integer|min:1',
            'start_serial' => 'nullable|integer|min:1',
        ]);

        return DB::transaction(function () use ($request) {
            $type = NewInstrumentType::create([
                'name' => $request->name,
                'description' => $request->description
            ]);

            // Initialize or update the vault for this instrument type
            $volume = $request->input('last_volume', 1);
            $page = $request->input('last_page', 1);
            $serial = $request->input('last_serial', 0);

            // Use resolved vault name to support shared vaults
            $vaultName = $this->registrationService->resolveVaultName($request->name);

            $vaultData = [
                'current_volume' => $volume,
                'current_page' => $page,
                'current_serial' => $serial,
                'updated_at' => now(),
            ];

            // Blank stays NULL rather than becoming 0: NULL is "not recorded",
            // and the survey falls back to the inferred floor for it.
            if ($this->vaultHasOriginColumns()) {
                $vaultData['start_volume'] = $this->nullableInt($request->input('start_volume'));
                $vaultData['start_serial'] = $this->nullableInt($request->input('start_serial'));
            }

            DB::connection('sqlsrv')->table('instrument_number_vaults')->updateOrInsert(
                ['instrument_type' => $vaultName],
                $vaultData
            );

            return response()->json($type, 201);
        });
    }

    public function update(Request $request, $id)
    {
        $type = NewInstrumentType::findOrFail($id);
        $oldName = $type->name;

        // PROTECTED TYPES: Prevent renaming of types involved in shared vault logic
        $protectedTypes = ['Power of Attorney', 'Deed of Surrender and Release', 'Deed of Assignment', 'Deed of Gift'];
        if (in_array($oldName, $protectedTypes) && $request->name !== $oldName) {
            return response()->json(['message' => 'This instrument type is a System Protected type and cannot be renamed.'], 403);
        }

        $request->validate([
            'name' => 'required|string|unique:sqlsrv.new_instrument_types,name,' . $id,
            'description' => 'nullable|string',
            'last_volume' => 'nullable|integer|min:0',
            'last_page' => 'nullable|integer|min:0',
            'last_serial' => 'nullable|integer|min:0',
            'start_volume' => 'nullable|integer|min:1',
            'start_serial' => 'nullable|integer|min:1',
        ]);

        return DB::transaction(function () use ($request, $type, $oldName) {
            $type->update([
                'name' => $request->name,
                'description' => $request->description
            ]);

            // If name changed, we should probably rename the vault entry too (if not shared)
            // But with shared vaults, this complicates things. 
            // For now, let's assume renaming non-protected types renames their 1-to-1 vault.
            if ($oldName !== $request->name) {
                // Only rename vault if it matches the old name (1-to-1)
                DB::connection('sqlsrv')->table('instrument_number_vaults')
                    ->where('instrument_type', $oldName)
                    ->update(['instrument_type' => $request->name]);
            }

            // Update vault numbers if provided
            $fields = $this->vaultColumnMap();

            if ($request->hasAny(array_keys($fields))) {
                $updateData = ['updated_at' => now()];

                foreach ($fields as $field => $column) {
                    if (!$request->has($field)) {
                        continue;
                    }

                    // The "last used" trio has always been written as given; the
                    // start pair is normalised so an emptied box clears the
                    // recorded floor instead of pinning it to 0.
                    $updateData[$column] = str_starts_with($field, 'start_')
                        ? $this->nullableInt($request->input($field))
                        : $request->input($field);
                }

                $vaultName = $this->registrationService->resolveVaultName($request->name);

                DB::connection('sqlsrv')->table('instrument_number_vaults')
                    ->updateOrInsert(
                        ['instrument_type' => $vaultName],
                        $updateData
                    );
            }

            return response()->json($type);
        });
    }

    /**
     * A vault number as the table stores it: a positive integer, or NULL.
     *
     * '' and '0' both mean "not recorded" here - the screen sends an empty box
     * as an empty string, and 0 is not a serial any book ever carried.
     */
    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $number = (int) $value;

        return $number > 0 ? $number : null;
    }

    public function destroy($id)
    {
        $type = NewInstrumentType::findOrFail($id);

        // PROTECTED TYPES: Prevent deletion
        $protectedTypes = ['Power of Attorney', 'Deed of Surrender and Release', 'Deed of Assignment', 'Deed of Gift'];
        if (in_array($type->name, $protectedTypes)) {
            return response()->json(['message' => 'This instrument type is System Protected and cannot be deleted.'], 403);
        }

        $type->delete();

        return response()->json(null, 204);
    }
}