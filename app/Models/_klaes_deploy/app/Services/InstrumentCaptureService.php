<?php

namespace App\Services;

use App\Services\PropertyIdAllocationService;
use App\Services\InstrumentRegistrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Exception;

class InstrumentCaptureService
{
    private ?bool $instrumentCaptureHasRegTime = null;

    public function __construct(
        protected PropertyIdAllocationService $propIdService,
        protected InstrumentRegistrationService $regService
    ) {
    }

    /**
     * Capture a new instrument.
     *
     * @param array $data Form data from request
     * @return array Result with status and message
     * @throws Exception
     */
    public function capture(array $data): array
    {
        return DB::connection('sqlsrv')->transaction(function () use ($data) {
            // 1. Validation & Pre-processing
            $instrumentType = $data['instrument_type'] ?? null;
            if (!$instrumentType) {
                throw new Exception("Instrument type is required.");
            }

            // Validate OP Serial Number for Occupancy Permits
            if (stripos($instrumentType, 'Occupancy Permit') !== false) {
                if (empty($data['op_serial_number'])) {
                    throw new Exception("OP Serial Number is required for Occupancy Permits.");
                }
            }

            // 2. Duplicate Guard – reject if the same temp_fileno or OP serial already exists
            $incomingTempFileno = isset($data['temp_fileno']) ? trim((string) $data['temp_fileno']) : null;
            if (empty($incomingTempFileno)) {
                $genericFileno = isset($data['fileno']) ? trim((string) $data['fileno']) : null;
                if ($genericFileno && preg_match('/^TEMP-/i', $genericFileno)) {
                    $incomingTempFileno = $genericFileno;
                }
            }

            if (!empty($incomingTempFileno)) {
                $existingByTemp = DB::connection('sqlsrv')->table('instrument_capture')
                    ->where('temp_fileno', $incomingTempFileno)
                    ->where(function ($q) {
                        $q->where('is_deleted', 0)->orWhereNull('is_deleted');
                    })
                    ->exists();

                if ($existingByTemp) {
                    throw new Exception("An instrument with temp file number {$incomingTempFileno} has already been captured. Duplicate submission prevented.");
                }
            }

            // 3. PropID Allocation
            // Gather identifiers from data
            $mls = isset($data['mlsFNo']) ? trim((string) $data['mlsFNo']) : null;
            $kangis = isset($data['kangisFileNo']) ? trim((string) $data['kangisFileNo']) : null;
            $newKangis = isset($data['NewKANGISFileno']) ? trim((string) $data['NewKANGISFileno']) : null;

            $tempFileno = isset($data['temp_fileno']) ? trim((string) $data['temp_fileno']) : null;
            $derivedOfficialFileno = null;
            $genericFileno = isset($data['fileno']) ? trim((string) $data['fileno']) : null;

            if (empty($tempFileno) && !empty($genericFileno)) {
                if (preg_match('/^TEMP-/i', $genericFileno) || preg_match('/\(T\)$/i', $genericFileno)) {
                    $tempFileno = $genericFileno;
                }
            }

            // Only fall back to generic fileno if no specific file number is detected
            if (empty($mls) && empty($kangis) && empty($newKangis) && empty($tempFileno)) {
                if (!empty($genericFileno)) {
                    // Temporary variants like "RES-2026-20(T)" should still resolve to the
                    // permanent base number for PropID lookup/allocation.
                    if (preg_match('/^TEMP-/i', $genericFileno)) {
                        $tempFileno = $genericFileno;
                    } elseif (preg_match('/\(T\)$/i', $genericFileno)) {
                        $tempFileno = $genericFileno;
                        $derivedOfficialFileno = trim((string) preg_replace('/\(T\)$/i', '', $genericFileno));
                    } else {
                        $derivedOfficialFileno = $genericFileno;
                    }
                }
            }

            // Server-side fallback: auto-generate temp_fileno for OP captures when
            // the client didn't supply one (race condition where user submits before
            // the async /get-next-temp-fileno response arrives).
            if (
                empty($tempFileno) && empty($mls) && empty($kangis) && empty($newKangis) && empty($derivedOfficialFileno)
                && stripos((string) $instrumentType, 'Occupancy Permit') !== false
            ) {
                try {
                    $seqId = DB::connection('sqlsrv')->table('temp_fileno_sequence')->insertGetId([
                        'created_by' => $data['created_by'] ?? auth()->id(),
                        'is_used' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $tempFileno = 'TEMP-' . str_pad((string) $seqId, 5, '0', STR_PAD_LEFT);
                    $data['temp_fileno'] = $tempFileno;
                    Log::info('Server-side temp_fileno fallback generated for OP capture', [
                        'temp_fileno' => $tempFileno,
                        'instrument_type' => $instrumentType,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Server-side temp_fileno fallback failed', ['error' => $e->getMessage()]);
                }
            }

            $allocationPrimary = $mls ?: $kangis ?: $newKangis ?: $derivedOfficialFileno ?: $tempFileno;

            // PropID_Master (via PropertyIdAllocationService) is authoritative for prop_id.
            // A client-supplied prop_id (a sourced OP record, an OP->ToT mint) is honored
            // ONLY when it is a real REGISTERED parcel id (exists in PropID_Master); an
            // unvalidated/legacy value is discarded and re-resolved below. Honouring the raw
            // client value unconditionally is what let divergent 8-10 digit prop_ids reach
            // deed_registrations during Feb-May 2026.
            $propId = (!empty($data['prop_id']) && $this->isRegisteredPropId($data['prop_id']))
                ? $data['prop_id']
                : null;
            try {
                // Determine if we have enough info for PropID
                if (empty($propId) && $allocationPrimary) {
                    $propId = $this->propIdService->allocateOrRetrievePropId(
                        $allocationPrimary,
                        $mls ?: $derivedOfficialFileno,
                        $kangis,
                        $newKangis,
                        [
                            'temp_fileno' => $tempFileno,
                            // OP captures can be temp-first and still require a prop_id.
                            'allow_temp_only' => (
                                stripos((string) $instrumentType, 'Occupancy Permit') !== false &&
                                empty($mls) &&
                                empty($kangis) &&
                                empty($newKangis) &&
                                empty($derivedOfficialFileno) &&
                                !empty($tempFileno)
                            ),
                        ]
                    );
                }
            } catch (Exception $e) {
                Log::warning("PropID allocation failed for Instrument Capture: " . $e->getMessage());
                // Decide if this should block or allow null. For now, allow null but log.
            }

            // 4. Registration Number Generation
            $regNumberData = null;
            $rootRegNo = $data['root_reg_number'] ?? null;
            $isTempReg = filter_var($data['is_temporary_reg'] ?? false, FILTER_VALIDATE_BOOLEAN);

            // NEW: Universal Atomic Registration for all non-temporary captures
            // We only generate numbers if NOT a temporary registration
            if (!$isTempReg) {
                // Atomic Logic: Reserve Number & Enforce Server Time
                $regNumberData = $this->regService->getRegistrationNumber($instrumentType, false, $data);
                $data['registrationDate'] = now()->format('Y-m-d'); // Enforce Server Date
            }

            $resolvedRegDate = $data['registrationDate'] ?? ($data['reg_date'] ?? date('Y-m-d'));
            $resolvedRegTime = $data['registrationTime'] ?? ($data['reg_time'] ?? null);

            // 5. Map Parties based on Type
            $parties = $this->mapParties($instrumentType, $data);

            // 6. Prepare Insert Data
            $insertData = [
                'instrument_type' => $instrumentType,
                'mlsFNo' => $mls,
                'kangisFileNo' => $kangis,
                'NewKANGISFileno' => $newKangis,
                'temp_fileno' => $tempFileno,
                'prop_id' => $propId,
                'is_temporary_file' => filter_var($data['is_temporary_file'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'is_temporary_reg' => $isTempReg,

                // Registration
                'root_reg_number' => null,
                'reg_no' => null, // e.g. for Deed of Gift
                // Use generated particulars for all non-temporary registrations
                'registration_number' => $regNumberData ? $regNumberData['formatted'] : null,
                'volume_no' => $regNumberData ? $regNumberData['volume'] : null,
                'page_no' => $regNumberData ? $regNumberData['page'] : null,
                'serial_no' => $regNumberData ? $regNumberData['serial'] : null,
                'reg_date' => $resolvedRegDate,
                'is_deed_registered' => $regNumberData ? 1 : 0,

                // Parties
                'party_1_name' => $parties['party_1']['name'],
                'party_1_address' => $parties['party_1']['address'],
                'party_1_phone' => $parties['party_1']['phone'],
                'party_1_nationality' => $parties['party_1']['nationality'],
                'party_1_id_type' => $parties['party_1']['id_type'],
                'party_1_id_number' => $parties['party_1']['id_number'],
                'party_1_house_no' => $data['firstPartyHouseNo'] ?? null,
                'party_1_plot_no' => $data['firstPartyPlotNo'] ?? null,
                'party_1_state' => $data['firstPartyState'] ?? null,
                'party_1_lga' => $data['firstPartyLga'] ?? null,
                'party_1_city' => $data['firstPartyCity'] ?? null,
                'party_1_district' => $data['firstPartyDistrict'] ?? null,

                'party_2_name' => $parties['party_2']['name'],
                'party_2_gender' => $this->normalizeGender($data['secondPartyGender'] ?? null),
                'party_2_address' => $parties['party_2']['address'],
                'party_2_phone' => $parties['party_2']['phone'],
                'party_2_nationality' => $parties['party_2']['nationality'],
                'party_2_id_type' => $parties['party_2']['id_type'],
                'party_2_id_number' => $parties['party_2']['id_number'],
                'party_2_house_no' => $data['secondPartyHouseNo'] ?? null,
                'party_2_plot_no' => $data['secondPartyPlotNo'] ?? null,
                'party_2_state' => $data['secondPartyState'] ?? null,
                'party_2_lga' => $data['secondPartyLga'] ?? null,
                'party_2_city' => $data['secondPartyCity'] ?? null,
                'party_2_district' => $data['secondPartyDistrict'] ?? null,

                'party_3_name' => $parties['party_3']['name'],
                'party_3_address' => $parties['party_3']['address'],
                'party_3_phone' => $parties['party_3']['phone'],
                'party_3_nationality' => $parties['party_3']['nationality'],
                'party_3_id_type' => $parties['party_3']['id_type'],
                'party_3_id_number' => $parties['party_3']['id_number'],
                'party_3_state' => $data['thirdPartyState'] ?? null,
                'party_3_lga' => $data['thirdPartyLga'] ?? null,
                'party_3_city' => $data['thirdPartyCity'] ?? null,
                'party_3_district' => $data['coMortgagorDistrict'] ?? ($data['thirdPartyDistrict'] ?? null),

                'party_4_name' => $parties['party_4']['name'],
                'party_4_address' => $parties['party_4']['address'],
                'party_4_phone' => $parties['party_4']['phone'],
                'party_4_nationality' => $parties['party_4']['nationality'],
                'party_4_id_type' => $parties['party_4']['id_type'],
                'party_4_id_number' => $parties['party_4']['id_number'],
                'party_4_state' => $data['thirdPartyState'] ?? null,
                'party_4_lga' => $data['thirdPartyLga'] ?? null,
                'party_4_city' => $data['thirdPartyCity'] ?? null,
                'party_4_district' => $data['thirdPartyDistrict'] ?? null,

                'party_5_name' => $parties['party_5']['name'],
                'party_5_address' => $parties['party_5']['address'],
                'party_5_phone' => $parties['party_5']['phone'],
                'party_5_nationality' => $parties['party_5']['nationality'],
                'party_5_id_type' => $parties['party_5']['id_type'],
                'party_5_id_number' => $parties['party_5']['id_number'],
                'party_5_state' => $data['party5State'] ?? null,
                'party_5_lga' => $data['party5Lga'] ?? null,
                'party_5_city' => $data['party5City'] ?? null,
                'party_5_district' => $data['party5District'] ?? null,

                'solicitor_name' => $data['solicitorName'] ?? null,
                'solicitor_district' => (($data['solicitorDistrict'] ?? null) === 'Other' ? ($data['solicitorDistrictOther'] ?? $data['solicitorDistrictCustom'] ?? null) : ($data['solicitorDistrict'] ?? null)),
                'solicitor_address' => trim(($data['solicitorAddress'] ?? '') . ' ' . ($data['solicitorDistrict'] ?? '') . ' ' . ($data['solicitorLga'] ?? $data['solicitorCity'] ?? '') . ' ' . ($data['solicitorState'] ?? '')) ?: null,

                // Property
                'property_description' => $data['propertyDescription'] ?? ($data['plotDescription'] ?? null),
                'plot_number' => $data['plotNumber'] ?? null,
                'tp_no' => $data['tp_no'] ?? null,
                'plot_size' => $data['size'] ?? ($data['plotSize'] ?? ($data['propertySize'] ?? null)),
                'lga' => $data['lga'] ?? null,
                'district' => $data['district'] ?? null,
                'land_use' => $data['land_use'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'land_use_id' => $data['land_use_id'] ?? null,
                'purpose_id' => $data['purpose_id'] ?? null,
                'survey_plan_no' => $data['surveyPlanNo'] ?? null,

                // Specifics
                'instrument_date' => $data['entryDate'] ?? ($data['instrumentDate'] ?? ($data['dateOfExecution'] ?? null)),
                'duration' => $data['duration'] ?? ($data['leaseTerm'] ?? ($data['assignmentTerm'] ?? null)),
                'start_date' => $data['start_date'] ?? ($data['cofoTermStartDate'] ?? null),
                // 'end_date' => ... calculate or input?
                'annual_rent' => $data['annualRent'] ?? null,
                'consideration_amount' => $data['consideration'] ?? ($data['subLeaseAmount'] ?? ($data['compensationAmount'] ?? null)),
                'bank_name' => $data['bankName'] ?? null,
                'mortgage_date' => $data['mortgageDate'] ?? null,
                'governor_consent_date' => $data['governorSignDate'] ?? null,
                'chief_magistrate_date' => $data['chiefMagistrateSignDate'] ?? null,
                'cofo_date' => $data['cofoDate'] ?? null,
                'cofo_reg_particulars' => $data['cofoRegParticulars'] ?? null,
                'number_of_plots' => $data['numberOfPlots'] ?? null,
                'original_plot_size' => $data['originalPlotSize'] ?? null,
                'merger_date' => $data['mergerDate'] ?? null,
                'merged_entities' => $data['mergedEntities'] ?? null,
                'new_entity_name' => $data['newEntityName'] ?? null,
                'surrender_reason' => $data['surrenderReason'] ?? null,
                'release_reg_particulars' => $data['releaseRegParticulars'] ?? null,
                'original_instrument_reg' => $data['originalInstrumentRegParticulars'] ?? null,
                'deceased_name' => $data['deceasedName'] ?? null,
                'date_of_death' => $data['dateOfDeath'] ?? null,
                'will_reference' => $data['willReference'] ?? null,
                'encumbrances' => $data['encumbrances'] ?? null,
                'supporting_docs' => $data['supportingDocs'] ?? null,
                'blockchain_hash' => $data['blockchainHash'] ?? null,
                'registrar_name' => $data['registrarName'] ?? null,
                'registrar_signature' => $data['registrarSignature'] ?? null,
                'op_serial_number' => $data['op_serial_number'] ?? null,
                'op_type' => $data['op_type'] ?? null,
                'cofo_type' => $data['cofo_type'] ?? null,
                'lpkn_no' => $data['lpkn_no'] ?? null,

                // Missing Mapping
                'property_location' => $data['propertyLocation'] ?? ($data['plotLocation'] ?? ($data['location'] ?? null)),

                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($this->hasInstrumentCaptureRegTimeColumn()) {
                $insertData['reg_time'] = $resolvedRegTime;
            }

            $id = DB::connection('sqlsrv')->table('instrument_capture')->insertGetId($insertData);

            // NEW: Universal Atomic Registration - persist to deed_registrations
            $deedRegId = null;
            if ($regNumberData) {
                $regData = $data;
                $regData['fileno'] = $allocationPrimary; // Use resolved Primary FileNo
                $regData['temp_fileno'] = $tempFileno;
                $regData['prop_id'] = $propId;
                $regData['instrument_date'] = $data['registrationDate']; // Enforce server date
                $regData['grantor'] = $parties['party_1']['name'];
                $regData['grantee'] = $parties['party_2']['name'];

                $regData['instrument_capture_id'] = $id;
                $regResult = $this->regService->registerInstrument($regData, $regNumberData);
                $deedRegId = $regResult['id'];
                $syncResult = $regResult['sync_result'];
            }

            // Mark temp file number as used
            if ($tempFileno && strpos($tempFileno, 'TEMP-') === 0) {
                $sequenceId = (int) str_replace('TEMP-', '', $tempFileno);
                DB::connection('sqlsrv')->table('temp_fileno_sequence')
                    ->where('id', $sequenceId)
                    ->update(['is_used' => 1, 'updated_at' => now()]);
            }

            // Mirror Certificate of Occupancy captures into CofO_staging so they
            // surface in the CofO timeline, legal search and property history
            // alongside CofOs created via the Property Record Assistant.
            $cofoStagingId = null;
            if ($this->isCofoInstrument($instrumentType)) {
                $cofoStagingId = $this->syncCofoStaging($instrumentType, $data, [
                    'mls' => $mls,
                    'kangis' => $kangis,
                    'newKangis' => $newKangis,
                    'temp_fileno' => $tempFileno,
                    'fileno' => $allocationPrimary,
                    'prop_id' => $propId,
                    'parties' => $parties,
                    'reg' => $regNumberData,
                    'reg_date' => $resolvedRegDate,
                    'reg_time' => $resolvedRegTime,
                    'insert' => $insertData,
                ]);
            }

            $finalResult = [
                'success' => true,
                'id' => $id,
                'cofo_staging_id' => $cofoStagingId,
                'deed_registration_id' => $deedRegId ?? null,
                'message' => 'Instrument captured successfully',
                'reg_number' => $insertData['registration_number'],
                'registration_number' => $insertData['registration_number'],
                'volume_no' => $insertData['volume_no'],
                'page_no' => $insertData['page_no'],
                'serial_no' => $insertData['serial_no'],
                'deeds_date' => date('d F Y', strtotime($insertData['reg_date'])),
                'deeds_time' => !empty($insertData['reg_time'])
                    ? date('g:i A', strtotime($insertData['reg_time']))
                    : date('g:i A', strtotime($insertData['reg_date'])),
                'fileno' => $allocationPrimary,
                'instrument_type' => $instrumentType
            ];

            if (isset($syncResult)) {
                $finalResult['sync_result'] = $syncResult;
            }

            return $finalResult;
        });
    }

    /**
     * Map specific form fields to standardized Party 1/2/3 columns.
     */
    private function mapParties(string $type, array $data): array
    {
        // 1. Identify Roles
        // Defaults
        $p1City = $data['firstPartyLga'] ?? ($data['firstPartyCity'] ?? '');
        $p1District = $data['firstPartyDistrict'] ?? '';
        $p1Address = $data['GrantorAddress'] ?? trim(($data['firstPartyStreet'] ?? '') . ' ' . $p1District . ' ' . $p1City . ' ' . ($data['firstPartyState'] ?? ''));
        $p1 = [
            'name' => $data['Grantor'] ?? ($data['firstPartyName'] ?? null),
            'address' => $p1Address ?: null,
            'phone' => $data['firstPartyPhone'] ?? null
        ];

        $p2City = $data['secondPartyLga'] ?? ($data['secondPartyCity'] ?? '');
        $p2District = $data['secondPartyDistrict'] ?? '';
        $p2Address = $data['GranteeAddress'] ?? trim(($data['secondPartyStreet'] ?? '') . ' ' . $p2District . ' ' . $p2City . ' ' . ($data['secondPartyState'] ?? ''));
        $p2 = [
            'name' => $data['Grantee'] ?? ($data['secondPartyName'] ?? null),
            'address' => $p2Address ?: null,
            'phone' => $data['secondPartyPhone'] ?? null
        ];

        $p3City = $data['thirdPartyLga'] ?? ($data['thirdPartyCity'] ?? '');
        $p3District = $data['coMortgagorDistrict'] ?? ($data['thirdPartyDistrict'] ?? '');
        $p3Address = $data['thirdPartyAddress'] ?? ($data['coMortgagorAddress'] ?? null);

        if ($p3Address) {
            $p3Address = trim($p3Address . ' ' . $p3District . ' ' . $p3City . ' ' . ($data['thirdPartyState'] ?? ''));
        }

        $p3 = [
            'name' => $data['coMortgagorName'] ?? ($data['thirdPartyName'] ?? null),
            'address' => $p3Address ?: null,
            'phone' => $data['coMortgagorPhone'] ?? ($data['thirdPartyPhone'] ?? null)
        ];

        // Specifics logic based on report
        // Note: The frontend sends 'Grantor'/'Grantee' generic fields BUT also instrument-specific ones might exist?
        // Actually, the JS maps everything to 'Grantor'/'Grantee' hidden fields before submit.
        // So standard logic mostly works, EXCEPT for Party 3.

        // Check for specific overrides if the JS didn't squash them (which it does, but let's be safe).

        // Tripartite Mortgage
        if (stripos($type, 'tripartite') !== false) {
            // Party 3 comes from where?
            $p3['name'] = $data['coMortgagorName'] ?? ($data['party_3_name'] ?? null);
            $p3['address'] = $data['coMortgagorAddress'] ?? ($data['party_3_address'] ?? null);
        }

        // Deed of Surrender with Co-Surrenderor
        if (stripos($type, 'surrender') !== false) {
            $p3['name'] = $data['coSurrenderorName'] ?? ($data['party_3_name'] ?? null);
            $p3['address'] = $data['coSurrenderorAddress'] ?? ($data['party_3_address'] ?? null);
        }

        // Normalize structure
        $p4City = $data['party4Lga'] ?? ($data['party4City'] ?? '');
        $p4District = $data['party4District'] ?? '';
        $p4Address = $data['party4Address'] ?? null;

        if ($p4Address) {
            $p4Address = trim($p4Address . ' ' . $p4District . ' ' . $p4City . ' ' . ($data['party4State'] ?? ''));
        }

        $p4 = [
            'name' => $data['thirdPartyName'] ?? ($data['party_4_name'] ?? null),
            'address' => $p4Address ?: null,
            'phone' => $data['thirdPartyPhone'] ?? ($data['party_4_phone'] ?? null)
        ];

        // Party 5 (Fourth Party)
        $p5City = $data['party5Lga'] ?? ($data['party5City'] ?? '');
        $p5District = $data['party5District'] ?? '';
        $p5Address = $data['party5Address'] ?? null;

        if ($p5Address) {
            $p5Address = trim($p5Address . ' ' . $p5District . ' ' . $p5City . ' ' . ($data['party5State'] ?? ''));
        }

        $p5 = [
            'name' => $data['party5Name'] ?? ($data['party_5_name'] ?? null),
            'address' => $p5Address ?: null,
            'phone' => $data['party5Phone'] ?? ($data['party_5_phone'] ?? null)
        ];

        return [
            'party_1' => $this->normalizeParty($p1, $data, 'party_1'),
            'party_2' => $this->normalizeParty($p2, $data, 'party_2'),
            'party_3' => $this->normalizeParty($p3, $data, 'party_3'),
            'party_4' => $this->normalizeParty($p4, $data, 'party_4'),
            'party_5' => $this->normalizeParty($p5, $data, 'party_5'),
        ];
    }

    private function normalizeParty(array $base, array $source, string $prefix): array
    {
        return [
            'name' => $base['name'] ?? null,
            'address' => $base['address'] ?? null, // Address often combined in old form.
            'phone' => $base['phone'] ?? ($source["{$prefix}_phone"] ?? null), // Use base phone if set
            'nationality' => $source["{$prefix}_nationality"] ?? ($source['donorNationality'] ?? ($source['doneeNationality'] ?? null)), // Deed of Gift specific
            'id_type' => $source["{$prefix}_id_type"] ?? ($source['donorIdDocument'] ?? ($source['doneeIdDocument'] ?? null)),
            'id_number' => $source["{$prefix}_id_number"] ?? ($source['donorIdNumber'] ?? ($source['doneeIdNumber'] ?? null)),
        ];
    }

    /**
     * Canonicalise a submitted gender to one of GenderNormalizer::CANON.
     *
     * The form posts the NAME from the `genders` lookup, not an id, so this only
     * has to guard against legacy/hand-edited values ("male", "M", "Government")
     * and the empty placeholder — anything unrecognised is stored as null rather
     * than polluting the column with a fifth vocabulary.
     */
    private function normalizeGender($raw): ?string
    {
        return app(GenderNormalizer::class)->normalize(is_string($raw) ? $raw : null);
    }
    /**
     * Whether the given instrument type is a Certificate of Occupancy variant
     * (regular, ST or SLTR) that should also be mirrored into CofO_staging.
     */
    private function isCofoInstrument(?string $type): bool
    {
        if (!$type) {
            return false;
        }

        return stripos($type, 'Certificate of Occupancy') !== false;
    }

    /**
     * Mirror a captured Certificate of Occupancy into the CofO_staging table.
     *
     * Mirrors the canonical mapping used by the Property Record Assistant
     * (PropertyRecordController) so CofOs captured via /instruments/create show
     * up in the same downstream views (timeline, legal search, property card).
     * Best-effort: any failure is logged and swallowed so it never blocks the
     * primary instrument capture. Runs inside the caller's transaction, so a
     * later rollback also discards this row.
     *
     * @param array $ctx Resolved capture context (file numbers, prop_id,
     *                    parties, registration data, and the instrument_capture
     *                    insert payload).
     */
    private function syncCofoStaging(string $instrumentType, array $data, array $ctx): ?int
    {
        try {
            if (!Schema::connection('sqlsrv')->hasTable('CofO_staging')) {
                Log::warning('CofO_staging table missing; skipped CofO mirror for instrument capture.');
                return null;
            }

            $cofoColumns = Schema::connection('sqlsrv')->getColumnListing('CofO_staging');

            $reg = $ctx['reg'] ?? null;
            $insert = $ctx['insert'] ?? [];
            $parties = $ctx['parties'] ?? [];

            $titleTypeMap = [
                'certificate of occupancy' => 'C of O',
                'st certificate of occupancy' => 'ST C of O',
                'sltr certificate of occupancy' => 'SLTR C of O',
            ];
            $titleType = $titleTypeMap[strtolower($instrumentType)] ?? 'C of O';

            $serialNo = $reg['serial'] ?? null;
            $pageNo = $reg['page'] ?? null;
            $volumeNo = $reg['volume'] ?? null;
            $regNo = $reg['formatted'] ?? null;

            // instrument_capture has no reg_time; fall back to the capture time
            // so deeds_time / transaction_time are never blank.
            $regTime = !empty($ctx['reg_time']) ? $ctx['reg_time'] : now()->format('H:i:s');

            // Idempotency guard: don't create a second mirror row if this exact
            // registration already landed in CofO_staging.
            if ($regNo && in_array('regNo', $cofoColumns, true)) {
                $exists = DB::connection('sqlsrv')->table('CofO_staging')
                    ->where('regNo', $regNo)
                    ->where(function ($q) {
                        $q->where('is_deleted', 0)->orWhereNull('is_deleted');
                    })
                    ->exists();

                if ($exists) {
                    Log::info('CofO_staging mirror skipped; registration already present.', ['regNo' => $regNo]);
                    return null;
                }
            }

            $grantor = $parties['party_1']['name'] ?? null;
            $grantee = $parties['party_2']['name'] ?? null;

            $allCofOData = [
                'np_fileno' => $data['np_fileno'] ?? null,
                'mlsFNo' => $ctx['mls'] ?: null,
                'kangisFileNo' => $ctx['kangis'] ?: null,
                'NewKANGISFileno' => $ctx['newKangis'] ?: null,
                'temp_fileno' => $ctx['temp_fileno'] ?: null,
                'fileno' => $ctx['fileno'] ?: null,
                'prop_id' => $ctx['prop_id'] ?? null,
                'title_type' => $titleType,
                'transaction_type' => $instrumentType,
                'instrument_type' => $instrumentType,
                'transaction_date' => $ctx['reg_date'] ?? null,
                'transaction_time' => $regTime,
                // CofO_staging has no reg_date/reg_time; deeds_* are the
                // registration equivalents downstream consumers read.
                'deeds_date' => $ctx['reg_date'] ?? null,
                'deeds_time' => $regTime,
                'serialNo' => $serialNo,
                'pageNo' => $pageNo,
                'volumeNo' => $volumeNo,
                'regNo' => $regNo,
                'cofo_type' => $data['cofo_type'] ?? null,
                'cofo_date' => $data['cofoDate'] ?? ($data['cofo_date'] ?? null),
                'period' => isset($data['period']) && $data['period'] !== '' ? (int) $data['period'] : null,
                'period_unit' => $data['periodUnit'] ?? ($data['period_unit'] ?? null),

                // Parties (CofO is Grantor -> Grantee)
                'Grantor' => $grantor,
                'Grantee' => $grantee,
                'party_1' => $grantor,
                'party_2' => $grantee,
                'party_3' => $parties['party_3']['name'] ?? null,
                'party_4' => $parties['party_4']['name'] ?? null,

                // Property
                'property_description' => $insert['property_description'] ?? null,
                'location' => $insert['property_location'] ?? null,
                'plot_no' => $insert['plot_number'] ?? null,
                'lgsaOrCity' => $insert['lga'] ?? null,
                'districtName' => $insert['district'] ?? null,
                'streetName' => $data['streetName'] ?? ($data['property_street_name'] ?? null),
                'house_no' => $data['house_no'] ?? ($data['houseNo'] ?? null),
                'tp_no' => $insert['tp_no'] ?? null,
                'lpkn_no' => $insert['lpkn_no'] ?? null,
                'approved_plan_no' => $data['approved_plan_no'] ?? ($data['approvedPlanNo'] ?? null),
                'plot_size' => $insert['plot_size'] ?? null,
                'land_use' => $insert['land_use'] ?? null,
                'related_file_number' => $data['related_file_number'] ?? ($data['relatedFileNumber'] ?? null),

                'source' => 'instrument_capture',
                'captured_by' => Auth::id(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'is_deleted' => 0,
            ];

            // Keep only keys that exist as columns in CofO_staging.
            $filteredCofOData = [];
            foreach ($allCofOData as $key => $value) {
                if (in_array($key, $cofoColumns, true)) {
                    $filteredCofOData[$key] = $value;
                }
            }

            $id = DB::connection('sqlsrv')->table('CofO_staging')->insertGetId($filteredCofOData);

            Log::info('CofO mirrored into CofO_staging from instrument capture.', [
                'cofo_staging_id' => $id,
                'regNo' => $regNo,
                'prop_id' => $ctx['prop_id'] ?? null,
                'instrument_type' => $instrumentType,
            ]);

            return $id;
        } catch (\Throwable $e) {
            // Never block the primary instrument capture because of the mirror.
            Log::error('Failed to mirror CofO into CofO_staging: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update an existing instrument.
     *
     * @param int|string $id Instrument ID
     * @param array $data Form data from request
     * @return array Result with status and message
     * @throws Exception
     */
    public function update($id, array $data): array
    {
        return DB::connection('sqlsrv')->transaction(function () use ($id, $data) {
            // 1. Verify existence
            $existing = DB::connection('sqlsrv')->table('instrument_capture')->find($id);
            if (!$existing) {
                throw new Exception("Instrument record not found.");
            }

            // 2. Validation & Pre-processing (basic)
            $instrumentType = $data['instrument_type'] ?? $existing->instrument_type;

            // 3. PropID Allocation (Re-run to ensure consistency if file numbers changed)
            $mls = $data['mlsFNo'] ?? $existing->mlsFNo;
            $kangis = $data['kangisFileNo'] ?? $existing->kangisFileNo;
            $newKangis = $data['NewKANGISFileno'] ?? $existing->NewKANGISFileno;
            $tempFileno = $data['temp_fileno'] ?? $existing->temp_fileno;
            $primary = $mls ?? $kangis ?? $newKangis ?? $tempFileno;

            $propId = $existing->prop_id;
            try {
                if ($primary) {
                    $propId = $this->propIdService->allocateOrRetrievePropId(
                        $primary,
                        $mls,
                        $kangis,
                        $newKangis,
                        ['temp_fileno' => $tempFileno]
                    );
                }
            } catch (Exception $e) {
                Log::warning("PropID allocation/retrieval failed during Update: " . $e->getMessage());
            }

            // 4. Registration Number - Update only if explicitly changed in form?
            // The form fields for reg number (root_reg_number, etc) should be present.
            // If explicit fields are passed, use them.
            // We do NOT auto-generate new ones on update typically.

            // 5. Map Parties based on Type
            $parties = $this->mapParties($instrumentType, $data);

            // 6. Prepare Update Data
            // We merge existing data with new data? Or overwrite? 
            // Typically overwrite with form data.
            $updateData = [
                'instrument_type' => $instrumentType,
                'mlsFNo' => $mls,
                'kangisFileNo' => $kangis,
                'NewKANGISFileno' => $newKangis,
                'temp_fileno' => $tempFileno,
                'prop_id' => $propId,
                'is_temporary_file' => filter_var($data['is_temporary_file'] ?? $existing->is_temporary_file, FILTER_VALIDATE_BOOLEAN),
                // 'is_temporary_reg' => ...

                // Registration Details (Use form data if present, else fallback seems wrong for update? 
                // Form submit usually sends everything. If a field is missing in $data, it might be null.
                // We'll trust the form data for editable fields.
                'root_reg_number' => $data['root_reg_number'] ?? $existing->root_reg_number,
                'reg_date' => $data['registrationDate'] ?? ($data['reg_date'] ?? ($existing->reg_date ?? null)),

                // If user changes particulars manually?
                // 'registration_number' => ... 

                // Parties
                'party_1_name' => $parties['party_1']['name'],
                'party_1_address' => $parties['party_1']['address'],
                'party_1_phone' => $parties['party_1']['phone'],
                'party_1_nationality' => $parties['party_1']['nationality'],
                'party_1_id_type' => $parties['party_1']['id_type'],
                'party_1_id_number' => $parties['party_1']['id_number'],
                'party_1_house_no' => $data['firstPartyHouseNo'] ?? ($existing->party_1_house_no ?? null),
                'party_1_plot_no' => $data['firstPartyPlotNo'] ?? ($existing->party_1_plot_no ?? null),
                'party_1_state' => $data['firstPartyState'] ?? ($existing->party_1_state ?? null),
                'party_1_lga' => $data['firstPartyLga'] ?? ($existing->party_1_lga ?? null),
                'party_1_city' => $data['firstPartyCity'] ?? ($existing->party_1_city ?? null),
                'party_1_district' => $data['firstPartyDistrict'] ?? ($existing->party_1_district ?? null),

                'party_2_name' => $parties['party_2']['name'],
                // Keep the stored value when the edit form submits a blank gender,
                // the way every other party_2 field below falls back to $existing.
                'party_2_gender' => $this->normalizeGender($data['secondPartyGender'] ?? null)
                    ?? ($existing->party_2_gender ?? null),
                'party_2_address' => $parties['party_2']['address'],
                'party_2_phone' => $parties['party_2']['phone'],
                'party_2_nationality' => $parties['party_2']['nationality'],
                'party_2_id_type' => $parties['party_2']['id_type'],
                'party_2_id_number' => $parties['party_2']['id_number'],
                'party_2_house_no' => $data['secondPartyHouseNo'] ?? ($existing->party_2_house_no ?? null),
                'party_2_plot_no' => $data['secondPartyPlotNo'] ?? ($existing->party_2_plot_no ?? null),
                'party_2_state' => $data['secondPartyState'] ?? ($existing->party_2_state ?? null),
                'party_2_lga' => $data['secondPartyLga'] ?? ($existing->party_2_lga ?? null),
                'party_2_city' => $data['secondPartyCity'] ?? ($existing->party_2_city ?? null),
                'party_2_district' => $data['secondPartyDistrict'] ?? ($existing->party_2_district ?? null),

                'party_3_name' => $parties['party_3']['name'],
                'party_3_address' => $parties['party_3']['address'],
                'party_3_phone' => $parties['party_3']['phone'],
                'party_3_nationality' => $parties['party_3']['nationality'],
                'party_3_id_type' => $parties['party_3']['id_type'],
                'party_3_id_number' => $parties['party_3']['id_number'],
                'party_3_state' => $data['thirdPartyState'] ?? ($existing->party_3_state ?? null),
                'party_3_lga' => $data['thirdPartyLga'] ?? ($existing->party_3_lga ?? null),
                'party_3_city' => $data['thirdPartyCity'] ?? ($existing->party_3_city ?? null),
                'party_3_district' => $data['coMortgagorDistrict'] ?? ($data['thirdPartyDistrict'] ?? ($existing->party_3_district ?? null)),

                'party_4_name' => $parties['party_4']['name'],
                'party_4_address' => $parties['party_4']['address'],
                'party_4_phone' => $parties['party_4']['phone'],
                'party_4_nationality' => $parties['party_4']['nationality'],
                'party_4_id_type' => $parties['party_4']['id_type'],
                'party_4_id_number' => $parties['party_4']['id_number'],
                'party_4_state' => $data['thirdPartyState'] ?? ($existing->party_4_state ?? null),
                'party_4_lga' => $data['thirdPartyLga'] ?? ($existing->party_4_lga ?? null),
                'party_4_city' => $data['thirdPartyCity'] ?? ($existing->party_4_city ?? null),
                'party_4_district' => $data['thirdPartyDistrict'] ?? ($existing->party_4_district ?? null),

                'party_5_name' => $parties['party_5']['name'],
                'party_5_address' => $parties['party_5']['address'],
                'party_5_phone' => $parties['party_5']['phone'],
                'party_5_nationality' => $parties['party_5']['nationality'],
                'party_5_id_type' => $parties['party_5']['id_type'],
                'party_5_id_number' => $parties['party_5']['id_number'],
                'party_5_state' => $data['party5State'] ?? ($existing->party_5_state ?? null),
                'party_5_lga' => $data['party5Lga'] ?? ($existing->party_5_lga ?? null),
                'party_5_city' => $data['party5City'] ?? ($existing->party_5_city ?? null),
                'party_5_district' => $data['party5District'] ?? ($existing->party_5_district ?? null),

                'solicitor_name' => $data['solicitorName'] ?? $existing->solicitor_name,
                'solicitor_district' => (($data['solicitorDistrict'] ?? null) === 'Other' ? ($data['solicitorDistrictOther'] ?? $data['solicitorDistrictCustom'] ?? $existing->solicitor_district ?? null) : ($data['solicitorDistrict'] ?? ($existing->solicitor_district ?? null))),
                'solicitor_address' => trim(($data['solicitorAddress'] ?? '') . ' ' . ($data['solicitorDistrict'] ?? '') . ' ' . ($data['solicitorLga'] ?? $data['solicitorCity'] ?? '') . ' ' . ($data['solicitorState'] ?? '')) ?: $existing->solicitor_address,

                // Property
                'property_description' => $data['propertyDescription'] ?? ($data['plotDescription'] ?? $existing->property_description),
                'property_location' => $data['propertyLocation'] ?? ($data['plotLocation'] ?? ($data['location'] ?? $existing->property_location)),
                'plot_number' => $data['plotNumber'] ?? $existing->plot_number,
                'tp_no' => $data['tp_no'] ?? $existing->tp_no,
                'plot_size' => $data['size'] ?? ($data['plotSize'] ?? ($data['propertySize'] ?? $existing->plot_size)),
                'lga' => $data['lga'] ?? $existing->lga,
                'district' => $data['district'] ?? $existing->district,
                'land_use' => $data['land_use'] ?? $existing->land_use,
                'purpose' => $data['purpose'] ?? ($existing->purpose ?? null),
                'land_use_id' => $data['land_use_id'] ?? ($existing->land_use_id ?? null),
                'purpose_id' => $data['purpose_id'] ?? ($existing->purpose_id ?? null),
                'survey_plan_no' => $data['surveyPlanNo'] ?? $existing->survey_plan_no,
                'op_serial_number' => $data['op_serial_number'] ?? $existing->op_serial_number,
                'op_type' => $data['op_type'] ?? $existing->op_type,
                'cofo_type' => $data['cofo_type'] ?? ($existing->cofo_type ?? null),
                'lpkn_no' => $data['lpkn_no'] ?? $existing->lpkn_no,

                // Specifics
                'instrument_date' => $data['instrumentDate'] ?? ($data['dateOfExecution'] ?? $existing->instrument_date),
                'duration' => $data['duration'] ?? $existing->duration,
                'start_date' => $data['start_date'] ?? $existing->start_date,
                'annual_rent' => $data['annualRent'] ?? $existing->annual_rent,
                'consideration_amount' => $data['consideration'] ?? ($data['subLeaseAmount'] ?? ($data['compensationAmount'] ?? $existing->consideration_amount)),
                'bank_name' => $data['bankName'] ?? $existing->bank_name,

                // Metadata
                'updated_at' => now(),
                'updated_by' => Auth::id(),
            ];

            if ($this->hasInstrumentCaptureRegTimeColumn()) {
                $updateData['reg_time'] = $data['registrationTime'] ?? ($data['reg_time'] ?? ($existing->reg_time ?? null));
            }

            // Filter out keys that might overwrite with null if we want partial updates?
            // HTML forms usually submit all or nothing. We assume full submit.

            DB::connection('sqlsrv')->table('instrument_capture')
                ->where('id', $id)
                ->update($updateData);

            // Propagate name changes to core tables for specific instrument types (Deed of Assignment/Gift)
            $syncResult = $this->regService->syncPartyNames($primary, $instrumentType, $parties['party_2']['name']);

            return [
                'success' => true,
                'message' => 'Instrument updated successfully',
                'id' => $id,
                'sync_result' => $syncResult
            ];
        });
    }

    private function hasInstrumentCaptureRegTimeColumn(): bool
    {
        if ($this->instrumentCaptureHasRegTime === null) {
            $this->instrumentCaptureHasRegTime = Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'reg_time');
        }

        return $this->instrumentCaptureHasRegTime;
    }

    /**
     * Is the given value a REAL, registered canonical parcel id (a PropID_Master.prop_id)?
     * Canonical prop_ids are small positive ints (PropID_Master.prop_id is a 32-bit int).
     * A non-numeric or over-long value (e.g. the Feb-May 2026 divergent 8-10 digit ids that
     * are not in PropID_Master) is rejected without querying, avoiding int-overflow on the
     * comparison. Used to decide whether a client-supplied prop_id may be trusted.
     */
    private function isRegisteredPropId($propId): bool
    {
        $pid = trim((string) $propId);
        // >9 digits cannot be a canonical id and would overflow the int column comparison.
        if ($pid === '' || !ctype_digit($pid) || strlen($pid) > 9 || (int) $pid <= 0) {
            return false;
        }

        return DB::connection('sqlsrv')
            ->table('PropID_Master')
            ->where('prop_id', (int) $pid)
            ->exists();
    }
}
