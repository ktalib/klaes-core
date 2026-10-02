<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Exception;

class InstrumentRegistrationService
{
    /**
     * Generate the next available registration number ensuring atomic uniqueness.
     * Uses Base-300 rollover logic (Serial -> Page -> Volume).
     *
     * @param string $instrumentType
     * @param bool $isAutoReg If true, returns 0/0/0 (e.g., for some ST fragmentations)
     * @return array Contains 'volume', 'page', 'serial', 'formatted'
     * @throws Exception
     */
    public function getRegistrationNumber(string $instrumentType, bool $isAutoReg = false, array $instrumentData = []): array
    {
        if ($isAutoReg) {
            return [
                'volume' => 0,
                'page' => 0,
                'serial' => 0,
                'formatted' => '0/0/0' // Special case as per requirement
            ];
        }

        // Resolve Vault Name (Shared Vault Logic)
        $vaultName = $this->resolveVaultName($instrumentType, $instrumentData['op_type'] ?? null);

        return DB::transaction(function () use ($vaultName, $instrumentType) {
            // Atomic Locking: Lock the vault row for this instrument type (or shared vault)
            $vault = DB::connection('sqlsrv')->table('instrument_number_vaults')
                ->where('instrument_type', $vaultName)
                ->lockForUpdate()
                ->first();

            if (!$vault) {
                throw new \Exception("The instrument type [{$vaultName}] is not found in the 'Manage Instrument Types for Vault Registration'. Please configure the vault first.");
            }

            $next = $this->nextFromVault($vault);
            ['volume' => $volume, 'page' => $page, 'serial' => $serial] = $next;

            $vaultUpdate = [
                'current_volume' => $volume,
                'current_page' => $page,
                'current_serial' => $serial,
                'updated_at' => now()
            ];

            // How full the volume now is. Only the Land Registry rules track
            // this, and only where the column has been deployed - the vault
            // table predates it, so a site that has not run the Land
            // Registration install still numbers, just without volume capacity.
            if (isset($next['volume_count']) && $this->vaultHasVolumeCountColumn()) {
                $vaultUpdate['current_volume_count'] = $next['volume_count'];
            }

            // Update the vault
            DB::connection('sqlsrv')->table('instrument_number_vaults')
                ->where('instrument_type', $vaultName)
                ->update($vaultUpdate);

            $formatted = "{$serial}/{$page}/{$volume}";

            Log::info("Vault [{$vaultName}] (requested as {$instrumentType}) advanced to {$formatted} by User " . Auth::id());

            return [
                'volume' => $volume,
                'page' => $page,
                'serial' => $serial,
                'formatted' => $formatted
            ];
        });
    }

    /**
     * Persist a new instrument registration to the deed_registrations table.
     *
     * @param array $data
     * @param array|null $preGeneratedNumber Optional pre-generated number data
     * @return int The ID of the new record
     */
    public function registerInstrument(array $data, ?array $preGeneratedNumber = null)
    {
        // Extract numbering if passed, or generate it if 'generate_number' is true
        // Note: It's safer to generate number INSIDE the same transaction that inserts the record
        // to minimize gaps if insert fails.

        return DB::transaction(function () use ($data, $preGeneratedNumber) {
            $instrumentType = $data['instrument_type'];
            $isAutoReg = $data['is_auto_reg'] ?? false;

            // Generate Number OR Use Pre-generated
            if ($preGeneratedNumber) {
                $numberData = $preGeneratedNumber;
                 Log::info("Using pre-generated number for {$data['fileno']}: {$numberData['formatted']}");
            } else {
                 $numberData = $this->getRegistrationNumber($instrumentType, $isAutoReg, $data);
            }

            $insertData = [
                'instrument_capture_id' => $data['instrument_capture_id'] ?? null,
                'fileno' => $data['fileno'],
                'parent_fileno' => $data['parent_fileno'] ?? null,
                'instrument_type' => $instrumentType,
                'registration_number' => $numberData['formatted'],
                'volume_no' => $numberData['volume'],
                'page_no' => $numberData['page'],
                'serial_no' => $numberData['serial'],
                'instrument_date' => $data['instrument_date'] ?? date('Y-m-d'),
                'deeds_date' => $data['deeds_date'] ?? ($data['instrument_date'] ?? date('Y-m-d')),
                'deeds_time' => $data['deeds_time'] ?? date('g:i A'), // Standard 12-hour AM/PM format
                'grantor' => $data['grantor'] ?? null,
                'grantee' => $data['grantee'] ?? null,
                'lga' => $data['lga'] ?? null,
                'district' => $data['district'] ?? null,
                'plot_number' => $data['plot_number'] ?? null,
                'size' => $data['size'] ?? null,
                'property_description' => $data['property_description'] ?? null,
                'status' => 'registered',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $propId = $data['prop_id'] ?? null;
            if (($propId === null || $propId === '') && !empty($data['instrument_capture_id'])) {
                $propId = DB::connection('sqlsrv')
                    ->table('instrument_capture')
                    ->where('id', $data['instrument_capture_id'])
                    ->value('prop_id');
            }

            if (Schema::connection('sqlsrv')->hasColumn('deed_registrations', 'prop_id')) {
                $insertData['prop_id'] = $propId !== '' ? $propId : null;
            }

            if (Schema::connection('sqlsrv')->hasColumn('deed_registrations', 'batch_session_id')) {
                $insertData['batch_session_id'] = $data['batch_session_id'] ?? null;
            }

            /*
             * Registration category. Digitization and Land Registration share
             * this table and are told apart by this column, not by the screen
             * that wrote the row - see section 4.2 of
             * docs/KLAES_Deed_of_Purchase_Land_Registration_Update.md, which
             * rules out a separate physical Land Registration table.
             *
             * Derived from the instrument type rather than passed in, so a row
             * cannot be filed under the wrong register by a caller that forgot
             * to set it. Everything that is not a Land Registration instrument
             * stays 'digitization', which is what every existing row is.
             */
            if (Schema::connection('sqlsrv')->hasColumn('deed_registrations', 'registration_category')) {
                $insertData['registration_category'] =
                    $instrumentType === config('land_registration.instrument_type')
                        ? 'land_registration'
                        : 'digitization';
            }

            // If there's extra data, json_encode it
            if (isset($data['extra_data'])) {
                $insertData['data'] = json_encode($data['extra_data']);
            }

            $id = DB::connection('sqlsrv')->table('deed_registrations')->insertGetId($insertData);

            Log::info("Instrument Registered: {$data['fileno']} as {$numberData['formatted']} (ID: $id)");

            // Propagate name changes to core tables for specific instrument types (Deed of Assignment/Gift)
            $syncResult = $this->syncPartyNames($data['fileno'], $data['instrument_type'], $data['grantee']);

            return [
                'id' => $id,
                'sync_result' => $syncResult
            ];
        });
    }

    /**
     * Initialize/Seed a vault if it doesn't exist (Helper)
     */
    public function initializeVault(string $instrumentType, int $vol = 1, int $page = 1, int $serial = 0, ?string $opType = null)
    {
        $vaultName = $this->resolveVaultName($instrumentType, $opType);

        $exists = DB::connection('sqlsrv')->table('instrument_number_vaults')->where('instrument_type', $vaultName)->exists();
        if (!$exists) {
            DB::connection('sqlsrv')->table('instrument_number_vaults')->insert([
                'instrument_type' => $vaultName,
                'current_volume' => $vol,
                'current_page' => $page,
                'current_serial' => $serial,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Preview the next number without incrementing (for UI display only)
     * @return array
     */
    public function previewNextNumber(string $instrumentType, ?string $opType = null): array
    {
        Log::info("DEBUG: resolving vault for type [{$instrumentType}] with op_type [{$opType}]");
        $vaultName = $this->resolveVaultName($instrumentType, $opType);
        Log::info("DEBUG: resolved vault name: [{$vaultName}]");

        $vault = DB::connection('sqlsrv')->table('instrument_number_vaults')
            ->where('instrument_type', $vaultName)
            ->first();

        if (!$vault) {
            throw new \Exception("The instrument type [{$vaultName}] is not found in the 'Manage Instrument Types for Vault Registration'. Please configure the vault first.");
        }

        // Delegated rather than recalculated. This method used to carry its own
        // copy of the base-300 arithmetic, which is the drift nextFromVault()
        // exists to prevent - and it would have shown a Land Registration
        // preview under the Deeds rules, so the number an officer was promised
        // and the number the capture burned would disagree.
        return array_merge(
            $this->nextFromVault($vault),
            [
                'deeds_date' => date('d F Y'), // e.g., 12 February 2026
                'deeds_time' => date('g:i A'), // e.g., 8:22 PM
            ]
        );
    }
    /**
     * Automatically register ST Fragmentation for approved mother applications.
     * Migrated from Controller to Service.
     */
    public function autoRegisterSTFragmentation()
    {
        try {
            // 1. Fetch existing registrations from SQL Server (primary connection)
            $existingFilenos = DB::connection('sqlsrv')->table('deed_registrations')
                ->where('instrument_type', 'ST Fragmentation')
                ->where('status', 'registered')
                ->pluck('fileno')
                ->toArray();

            // 2. Query Mother Applications from SQL Server, excluding existing ones
            $query = DB::connection('sqlsrv')->table('mother_applications as m')
                ->where('m.planning_recommendation_status', 'Approved')
                ->where('m.application_status', 'Approved');

            if (!empty($existingFilenos)) {
                $query->whereNotIn('m.fileno', $existingFilenos);
            }

            $approvedMotherApplications = $query->select(
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
                'm.property_street_name'
            )
                ->get();

            $registeredCount = 0;

            foreach ($approvedMotherApplications as $motherApp) {
                $stmReference = $this->generateSTMReference();

                // Build mother applicant name
                $motherApplicantParts = [];
                if (!empty($motherApp->applicant_title))
                    $motherApplicantParts[] = $motherApp->applicant_title;
                if (!empty($motherApp->first_name))
                    $motherApplicantParts[] = $motherApp->first_name;
                if (!empty($motherApp->middle_name))
                    $motherApplicantParts[] = $motherApp->middle_name;
                if (!empty($motherApp->surname))
                    $motherApplicantParts[] = $motherApp->surname;
                if (!empty($motherApp->corporate_name))
                    $motherApplicantParts[] = $motherApp->corporate_name;
                if (!empty($motherApp->rc_number))
                    $motherApplicantParts[] = $motherApp->rc_number;
                if (!empty($motherApp->multiple_owners_names))
                    $motherApplicantParts[] = $motherApp->multiple_owners_names;
                $motherApplicantName = implode(' ', $motherApplicantParts) ?: ($motherApp->mother_applicant ?? 'N/A');

                // Build property description
                $propertyParts = [];
                if (!empty($motherApp->property_house_no))
                    $propertyParts[] = 'House No: ' . $motherApp->property_house_no;
                if (!empty($motherApp->plotNumber))
                    $propertyParts[] = 'Plot No: ' . $motherApp->plotNumber;
                if (!empty($motherApp->property_street_name))
                    $propertyParts[] = $motherApp->property_street_name;
                if (!empty($motherApp->district))
                    $propertyParts[] = $motherApp->district;
                if (!empty($motherApp->lga))
                    $propertyParts[] = $motherApp->lga;
                $propertyDescription = implode(', ', $propertyParts) ?: 'Property details not available';

                // Use the registerInstrument method to ensure consistent logging and table usage
                // We pass 'is_auto_reg' => true to get the 0/0/0 numbering
                $this->registerInstrument([
                    'fileno' => $motherApp->fileno, // Using fileno as primary identifier
                    'parent_fileno' => $motherApp->fileno, // Self-referential or null? Logic used motherApp->fileno as parent
                    'instrument_type' => 'ST Fragmentation',
                    'is_auto_reg' => true,
                    'grantor' => 'Kano State Government',
                    'grantee' => $motherApplicantName,
                    'lga' => $motherApp->lga,
                    'district' => $motherApp->district,
                    'plot_number' => $motherApp->plotNumber,
                    'size' => $motherApp->size,
                    'property_description' => $propertyDescription,
                    'extra_data' => [
                        'stm_ref' => $stmReference,
                        'np_fileno' => $motherApp->np_fileno
                    ]
                ]);

                $registeredCount++;
            }

            if ($registeredCount > 0) {
                Log::info('Auto-registered ST Fragmentation records', ['count' => $registeredCount]);
            }

            // Trigger SubApps checks as well
            $this->autoRegisterSTSubApps();

        } catch (\Exception $e) {
            Log::error('Error auto-registering ST Fragmentation', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Automatically register ST Fragmentation for approved sub-applications.
     */
    public function autoRegisterSTSubApps()
    {
        try {
            // 1. Fetch existing registrations from SQL Server (primary connection)
            // We can reuse the same list if we want, but let's be safe and fetch fresh or pass it
            $existingFilenos = DB::connection('sqlsrv')->table('deed_registrations')
                ->where('instrument_type', 'ST Fragmentation')
                ->where('status', 'registered')
                ->pluck('fileno') // SubApps also use fileno column
                ->toArray();

            // 2. Query SubApplications from SQL Server
            // 2. Query SubApplications from SQL Server
            $query = DB::connection('sqlsrv')->table('subapplications as s')
                ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                ->where('s.application_status', 'Approved')
                ->where('s.is_primary_the_owner', 1) // Only auto-register if Primary Owner Retains
                ->where(function ($q) {
                    $q->where('s.planning_recommendation_status', 'Approved')
                        ->orWhere(function ($subQ) {
                            $subQ->where('m.planning_recommendation_status', 'Approved')
                                ->where('s.is_sua_unit', 0);
                        });
                });

            if (!empty($existingFilenos)) {
                $query->whereNotIn('s.fileno', $existingFilenos);
            }

            $approvedSubApps = $query->select(
                's.id',
                's.fileno',
                's.created_by',
                'm.property_lga as lga',
                'm.property_district as district',
                'm.property_plot_no as plotNumber',
                'm.plot_size as size',
                'm.property_house_no',
                'm.property_street_name',
                'm.fileno as mother_fileno',
                // Fetch Mother Applicant Details for Grantee Name
                'm.applicant_title',
                'm.first_name',
                'm.middle_name',
                'm.surname',
                'm.corporate_name',
                'm.rc_number',
                'm.multiple_owners_names'
            )
                ->get();

            $registeredCount = 0;

            foreach ($approvedSubApps as $subApp) {
                $stmReference = $this->generateSTMReference();

                // Build applicant name using MOTHER details (since is_primary_the_owner = 1)
                $parts = [];
                if (!empty($subApp->applicant_title))
                    $parts[] = $subApp->applicant_title;
                if (!empty($subApp->first_name))
                    $parts[] = $subApp->first_name;
                if (!empty($subApp->middle_name))
                    $parts[] = $subApp->middle_name;
                if (!empty($subApp->surname))
                    $parts[] = $subApp->surname;
                if (!empty($subApp->corporate_name))
                    $parts[] = $subApp->corporate_name;
                if (!empty($subApp->rc_number))
                    $parts[] = $subApp->rc_number;

                $granteeName = implode(' ', $parts) ?: ($subApp->multiple_owners_names ?? 'N/A');

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

                $this->registerInstrument([
                    'fileno' => $subApp->fileno,
                    'parent_fileno' => $subApp->mother_fileno,
                    'instrument_type' => 'ST Fragmentation',
                    'is_auto_reg' => true,
                    'grantor' => 'Kano State Government',
                    'grantee' => $granteeName,
                    'lga' => $subApp->lga,
                    'district' => $subApp->district,
                    'plot_number' => $subApp->plotNumber,
                    'size' => $subApp->size,
                    'property_description' => $propertyDescription,
                    'created_by' => $subApp->created_by,
                    'extra_data' => [
                        'stm_ref' => $stmReference,
                        'subapp_id' => $subApp->id
                    ]
                ]);

                $registeredCount++;
            }

            if ($registeredCount > 0) {
                Log::info('Auto-registered ST Fragmentation records for SubApps (Retention)', ['count' => $registeredCount]);
            }
        } catch (\Exception $e) {
            Log::error('Error auto-registering ST SubApps', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Generate a unique STM reference.
     */
    private function generateSTMReference()
    {
        $prefix = 'STM';
        $year = date('Y');
        $random = mt_rand(1000, 9999);
        return "{$prefix}/{$year}/{$random}";
    }

    /**
     * The next number a vault row would issue — pure arithmetic, no writes.
     *
     * Shared by getRegistrationNumber() (which then persists it) and
     * peekRegistrationNumber() (which only reports it), so what an officer is
     * shown before capturing and what the capture actually burns cannot diverge.
     */
    private function nextFromVault(?object $vault): array
    {
        // The Land Registry numbers on different rules from Deeds, and the two
        // must not be conflated - see nextForLandRegistration().
        if (($vault->instrument_type ?? null) === config('land_registration.instrument_type')) {
            return $this->nextForLandRegistration($vault);
        }

        // A fresh or corrupted vault starts at 1/1/1.
        $currentVolume = (int) ($vault->current_volume ?? 1);
        $currentSerial = (int) ($vault->current_serial ?? 0);

        if ($currentVolume < 1) {
            $currentVolume = 1;
        }

        $volume = $currentVolume;
        $serial = $currentSerial + 1;
        $page = $serial;

        // Rollover Logic: Base-300
        if ($serial > 300) {
            $serial = 1;
            $page = 1;
            $volume++;
        }

        return [
            'volume' => $volume,
            'page' => $page,
            'serial' => $serial,
            'formatted' => "{$serial}/{$page}/{$volume}",
        ];
    }

    /**
     * Whether instrument_number_vaults carries the volume-capacity counter.
     *
     * Cached for the life of the request: this is asked once per allocation and
     * the answer cannot change mid-request.
     */
    private ?bool $vaultHasVolumeCount = null;

    private function vaultHasVolumeCountColumn(): bool
    {
        if ($this->vaultHasVolumeCount === null) {
            $this->vaultHasVolumeCount = Schema::connection('sqlsrv')
                ->hasColumn('instrument_number_vaults', 'current_volume_count');
        }

        return $this->vaultHasVolumeCount;
    }

    /**
     * The Land Registry's numbering, per sections 10-14 of
     * docs/KLAES_Deed_of_Purchase_Land_Registration_Update.md.
     *
     * Three rules, and the third is the one that makes this different from the
     * Deeds arithmetic above rather than a variation of it:
     *
     *   1. Serial and page are four-digit counters: 9999 rolls back to 1.
     *   2. The volume advances once it holds `volume_capacity` (300) entries.
     *   3. Those two are INDEPENDENT. A serial rolling 9999 -> 1 must leave the
     *      volume alone. The Deeds rule ties them together - one event resets
     *      the serial and bumps the volume - which is precisely what a Land
     *      register must not do, and why this cannot reuse that code path.
     *
     * The capacity count is carried on the vault row rather than derived by
     * counting registrations, because a deleted registration must not hand its
     * slot back: the number is never re-issued, so the volume it sat in is
     * still that much closer to full.
     */
    private function nextForLandRegistration(?object $vault): array
    {
        $rules = config('land_registration.numbering');
        $serialMax = (int) ($rules['serial_max'] ?? 9999);
        $pageMax = (int) ($rules['page_max'] ?? 9999);
        $capacity = (int) ($rules['volume_capacity'] ?? 300);

        $volume = max(1, (int) ($vault->current_volume ?? 1));

        $serial = (int) ($vault->current_serial ?? 0) + 1;
        if ($serial > $serialMax) {
            $serial = 1;
        }

        // Page is tracked separately from serial. They advance in step in
        // practice - the paper register writes the same value in both columns -
        // but the spec gives each its own limit, so neither is derived.
        $page = (int) ($vault->current_page ?? 0) + 1;
        if ($page > $pageMax) {
            $page = 1;
        }

        // How full the current volume is. A vault written before this column
        // existed reports null, which reads as an empty volume; that is the
        // safe direction to be wrong in, since it delays a volume change rather
        // than forcing an early one.
        $inVolume = (int) ($vault->current_volume_count ?? 0);
        if ($inVolume >= $capacity) {
            $volume++;
            $inVolume = 0;
        }
        $inVolume++;

        return [
            'volume' => $volume,
            'page' => $page,
            'serial' => $serial,
            'volume_count' => $inVolume,
            'formatted' => "{$serial}/{$page}/{$volume}",
        ];
    }

    /**
     * Report the next available registration particulars WITHOUT consuming them.
     *
     * Used by the capture screen to tell the officer which number the instrument
     * they are about to register will take. Returns `configured => false` when the
     * vault has not been set up under "Manage Instrument Types", which is not an
     * error here — the capture form simply shows nothing.
     *
     * @return array{vault:string, configured:bool, volume?:int, page?:int, serial?:int, formatted?:string}
     */
    public function peekRegistrationNumber(string $instrumentType, ?string $opType = null): array
    {
        $vaultName = $this->resolveVaultName($instrumentType, $opType);

        $vault = DB::connection('sqlsrv')->table('instrument_number_vaults')
            ->where('instrument_type', $vaultName)
            ->first();

        if (!$vault) {
            return ['vault' => $vaultName, 'configured' => false];
        }

        return array_merge(
            ['vault' => $vaultName, 'configured' => true],
            $this->nextFromVault($vault)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Unified Register support
    |--------------------------------------------------------------------------
    |
    | Added for System Admin -> Configurable Entries -> Deeds Instrument Volume,
    | which reads the registers and their counters. ALAES carries these on its
    | own copy of this service; KLAES's copy had diverged (it has syncPartyNames,
    | ALAES does not), so they are added here rather than by overwriting it.
    |
    | The Unified Register itself is OFF on KLAES by default
    | (config/instrument_workflow.php -> registration.unified_vault.enabled).
    | Kano's twelve vaults number each instrument type on their own rules, and
    | switching them onto one shared register changes live registration
    | numbering. sharedVaultName() therefore returns null until someone turns it
    | on deliberately, and every caller treats null as "no shared register".
    */

    /** @var string|false|null false = not yet looked up */
    private $sharedVault = false;

    /** @var array<string, bool> column name => exists on instrument_number_vaults */
    private array $vaultColumns = [];

    /** The register every type shares while the Unified Register is live, or null. */
    public function sharedVaultName(): ?string
    {
        if (!filter_var(config('instrument_workflow.registration.unified_vault.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        if ($this->sharedVault === false) {
            $this->sharedVault = null;

            if ($this->vaultHasColumn('is_shared')) {
                $name = DB::connection('sqlsrv')->table('instrument_number_vaults')
                    ->where('is_shared', 1)
                    ->orderBy('id')
                    ->value('instrument_type');

                $this->sharedVault = $name !== null && trim((string) $name) !== '' ? (string) $name : null;
            }
        }

        return $this->sharedVault;
    }

    public function unifiedRegisterIsLive(): bool
    {
        return $this->sharedVaultName() !== null;
    }

    /**
     * The vault a type would use on its own, ignoring any shared register.
     * ALAES calls this baseVaultName(); here it is resolveVaultName(), and this
     * is the alias so the shared screens read the same on both systems.
     */
    public function baseVaultName(string $instrumentType, ?string $opType = null): string
    {
        return $this->resolveVaultName($instrumentType, $opType);
    }

    /** Pages per volume for one register, falling back to the configured default. */
    public function pageLimitFor(?object $vault): int
    {
        $limit = (int) ($vault->page_limit ?? 0);

        return $limit >= 1 ? $limit : max(1, (int) config('instrument_workflow.registration.default_page_limit', 300));
    }

    /** Whether instrument_number_vaults carries a column, cached per request. */
    public function vaultHasColumn(string $column): bool
    {
        return $this->vaultColumns[$column] ??= Schema::connection('sqlsrv')
            ->hasColumn('instrument_number_vaults', $column);
    }

    /** The particulars the next registration out of this register would take. */
    public function nextNumberForVault(object $vault): array
    {
        return $this->nextFromVault($vault);
    }

    public function resolveVaultName(string $instrumentType, ?string $opType = null): string
    {
        // Normalize for comparison
        $normalized = trim($instrumentType);

        // Occupancy Permit (OP) special handling: use sub-type vault if provided
        if (
            stripos($normalized, 'Occupancy Permit') !== false ||
            $normalized === 'OP'
        ) {
            return $opType ?? 'Occupancy Permit (OP)';
        }

        // Shared Vault Mapping
        if (
            stripos($normalized, 'Deed of Surrender') !== false ||
            stripos($normalized, 'Power of Attorney') !== false
        ) {
            // Both share the 'Power of Attorney' vault
            return 'Power of Attorney';
        }

        // SLTR and Sectional Titling certificates paginate from their OWN vaults,
        // configured under Manage Instrument Types ('SLTR CofO Pagination' and
        // 'Sectional Titling CofO'). These must be tested BEFORE the generic
        // "Certificate of Occupancy" branch below, which would otherwise swallow
        // "SLTR Certificate of Occupancy" / "ST Certificate of Occupancy" and burn
        // numbers out of the regular CofO vault.
        if ($this->looksLikeCofo($normalized)) {
            if (stripos($normalized, 'SLTR') !== false) {
                return 'SLTR CofO Pagination';
            }

            // "Sectional Titling …" spelled out, or the "ST " prefix the capture
            // form submits. Word-anchored so it cannot fire on an unrelated type
            // that merely contains the letters (e.g. "First Registration").
            if (
                stripos($normalized, 'Sectional Titling') !== false ||
                preg_match('/\bST\b/i', $normalized) === 1
            ) {
                return 'Sectional Titling CofO';
            }
        }

        if (
            stripos($normalized, 'Regular CofO') !== false ||
            stripos($normalized, 'Certificate of Occupancy') !== false
        ) {
            return 'Certificate of Occupancy';
        }

        if (
            stripos($normalized, 'Deed of Gift') !== false ||
            stripos($normalized, 'Deed of Assignment') !== false
        ) {
            return 'Deed of Assignment';
        }


        if (
            stripos($normalized, 'Deed of Mortgage') !== false ||
            stripos($normalized, 'Tripartite Mortgage') !== false
        ) {
            return 'Deed of Mortgage';
        }

        if (
            stripos($normalized, 'Sub Lease') !== false
        ) {
            return 'Sub Lease';
        }

        if (
            stripos($normalized, 'Occupancy Permit') !== false ||
            $normalized === 'OP'
        ) {
            return 'Occupancy Permit (OP)';
        }

        if (
            stripos($normalized, 'Devolution Order') !== false
        ) {
            return 'Devolution Order';
        }

        if (
            stripos($normalized, 'ST Assignment') !== false
        ) {
            return 'ST Assignment (Transfer of Title)';
        }

        if (
            stripos($normalized, 'ST Fragmentation') !== false
        ) {
            return 'ST Fragmentation';
        }

        // NOTE: the old 'Sectional Titling CofO' branch that used to sit here is
        // now handled by the CofO block above, which catches the spelled-out name
        // AND the "ST Certificate of Occupancy" the capture form submits.

        // Default: 1-to-1 mapping
        return $instrumentType;
    }

    /**
     * Whether an instrument type names a Certificate of Occupancy in any of the
     * spellings in use: "Certificate of Occupancy", "CofO", "C of O".
     *
     * Used to qualify the SLTR / Sectional Titling vault checks, so a type that
     * merely starts with "ST " — "ST Assignment (Transfer of Title)",
     * "ST Fragmentation" — keeps its own vault instead of being pulled into the
     * Sectional Titling CofO pagination.
     */
    private function looksLikeCofo(string $instrumentType): bool
    {
        return stripos($instrumentType, 'Certificate of Occupancy') !== false
            || stripos($instrumentType, 'CofO') !== false
            || stripos($instrumentType, 'C of O') !== false;
    }

    /**
     * Build the base / "(T)" variants of a file number. A temporary "(T)" file and its base
     * number are the same physical file, but different tables store different forms of it,
     * so a lookup keyed to one form must also match the other.
     *
     * @return array<int,string>
     */
    private function fileNumberVariants(string $fileNo): array
    {
        $fileNo = trim($fileNo);
        if ($fileNo === '') {
            return [];
        }

        $variants = [$fileNo => $fileNo];
        $base = trim((string) preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $fileNo));
        if ($base !== '') {
            $variants[$base] = $base;
            $variants[$base . '(T)'] = $base . '(T)';
        }

        return array_values($variants);
    }

    /**
     * Synchronize party names (specifically party_2/Grantee) to core system tables.
     * Targeted at 'Deed of Gift' and 'Deed of Assignment' instruments.
     *
     * @param string $fileNo
     * @param string $instrumentType
     * @param string $party2Name
     * @return void
     */
    public function syncPartyNames(?string $fileNo, ?string $instrumentType, ?string $party2Name): array
    {
        // Nullable on purpose. A capture with no resolved file number or no party 2
        // name used to hit these non-nullable hints and raise a TypeError - an
        // \Error, not an \Exception, so it escaped the caller's catch and killed the
        // whole request. Missing data must simply mean "nothing to propagate".
        $fileNo = $fileNo !== null ? trim($fileNo) : '';
        $party2Name = $party2Name !== null ? trim($party2Name) : '';

        $result = [
            'synced' => false,
            'old_name' => null,
            'new_name' => $party2Name
        ];

        if ($instrumentType === null || $instrumentType === '') {
            return $result;
        }

        $normalizedType = $this->resolveVaultName($instrumentType);

        // We only sync for Deeds of Assignment, Deeds of Gift, and Irrevocable Power of Attorney
        if ($normalizedType !== 'Deed of Assignment' && $normalizedType !== 'Power of Attorney') {
            return $result;
        }

        if (empty($fileNo) || empty($party2Name)) {
            return $result;
        }

        Log::info("Propagating party_2 name update for {$fileNo}", [
            'instrument_type' => $instrumentType,
            'new_name' => $party2Name
        ]);

        // An instrument may be registered against a temporary "(T)" number while the file's
        // core records are keyed to the base number (file_indexings holds the "(T)" in
        // temp_file_no, not file_number) — or the reverse. Matching the literal number alone
        // silently updated ZERO rows for every deed filed under a "(T)", leaving the file
        // title showing the previous owner. Match all variants of the same physical file.
        $fileNoVariants = $this->fileNumberVariants($fileNo);

        try {
            return DB::transaction(function () use ($fileNoVariants, $party2Name, &$result) {
                // Get old name for feedback (from file_indexings as primary source)
                $result['old_name'] = DB::connection('sqlsrv')->table('file_indexings')
                    ->where(function ($q) use ($fileNoVariants) {
                        $q->whereIn('file_number', $fileNoVariants)
                            ->orWhereIn('mls_file_no', $fileNoVariants)
                            ->orWhereIn('temp_file_no', $fileNoVariants);
                    })
                    ->value('file_title');

                // 1. Update file_indexings.file_title
                $affected = DB::connection('sqlsrv')->table('file_indexings')
                    ->where(function ($q) use ($fileNoVariants) {
                        $q->whereIn('file_number', $fileNoVariants)
                            ->orWhereIn('mls_file_no', $fileNoVariants)
                            ->orWhereIn('temp_file_no', $fileNoVariants);
                    })
                    ->update([
                        'file_title' => $party2Name,
                        'updated_at' => now()
                    ]);

                // 2. Update customers_staging.customer_name
                $affected += DB::connection('sqlsrv')->table('customers_staging')
                    ->whereIn('file_number', $fileNoVariants)
                    ->update([
                        'customer_name' => $party2Name,
                        'updated_at' => now()
                    ]);

                // 3. Update entities_staging.entity_name
                $affected += DB::connection('sqlsrv')->table('entities_staging')
                    ->whereIn('file_number', $fileNoVariants)
                    ->update([
                        'entity_name' => $party2Name,
                        'updated_at' => now()
                    ]);

                // 4. Update fileNumber.FileName
                $affected += DB::connection('sqlsrv')->table('fileNumber')
                    ->where(function ($q) use ($fileNoVariants) {
                        $q->whereIn('mlsfNo', $fileNoVariants)
                            ->orWhereIn('kangisFileNo', $fileNoVariants)
                            ->orWhereIn('NewKANGISFileNo', $fileNoVariants);
                    })
                    ->update([
                        'FileName' => $party2Name
                    ]);

                // Report the truth: matching nothing is a silent failure, not a sync.
                $result['rows_updated'] = $affected;
                $result['synced'] = $affected > 0;
                if ($affected === 0) {
                    Log::warning('syncPartyNames matched no core records', [
                        'file_no_variants' => $fileNoVariants,
                        'new_name' => $party2Name,
                    ]);
                }
                return $result;
            });
        } catch (\Exception $e) {
            Log::error("Failed to sync party names for {$fileNo}: " . $e->getMessage());
            // We return the result as is if it fails (or rethrow if critical)
            return $result;
        }
    }
}
