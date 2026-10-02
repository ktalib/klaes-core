<?php

namespace App\Services;

use App\Models\LandRecommendation;
use App\Models\SltrRecommendation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * MASTER DELETE for the Recommendation and RofO modules — Land, SLTR and ST.
 *
 * A recommendation is never a single row. Generating and issuing one fans out to
 * the PRA register, the shared security-paper pool, the `security_codes` tracking
 * table and the print log, and every screen in the app reads a different one of
 * those. Deleting the record alone leaves a PRA transaction naming a file that no
 * longer exists and a security paper still marked as in use.
 *
 * Every method here erases one document from every table it was written to and
 * hands back a count per table, which the controllers put in the audit entry.
 * Callers own the transaction and the permission gate — see the controllers.
 *
 * TWO LEVELS, deliberately distinct:
 *
 *   purge*Recommendation()  the record itself is gone. It takes its RofO with it,
 *                           because the RofO IS the recommendation record on Land
 *                           and SLTR — there is no separate row to leave behind.
 *
 *   purge*Rofo()            only the issuance is undone. The recommendation stays,
 *                           approved, and can be issued again. This is the one an
 *                           operator wants after a RofO was generated in error.
 *
 * The ST pair is shaped differently because ST stores them differently: its RofO
 * is a real row in the legacy `rofo` table, while its "recommendation" is only a
 * decision stamped onto the application (planning_recommendation_status and its
 * date/comment). So ST's recommendation delete RESETS those fields — it must never
 * delete the application, which is a whole ST file with its own master delete
 * elsewhere (CommissionNewSTController::masterDestroy).
 */
class RofoRecommendationPurgeService
{
    /**
     * The document_type values each module has written into print_logs.
     *
     * Several per module, and none of them redundant: the official run, the Print
     * Manager and the re-issuance flows each pass their own label, and they have
     * drifted apart over time ('Land ROFO' from the controller, 'Land RofO' from
     * the Print Manager button). A master delete has to catch all of them or a new
     * record reusing the file number inherits a print history it never had.
     *
     * Listed exactly as each call site writes them. The white-copy variants are
     * derived in deletePrintLogs() and must not be listed here.
     */
    private const DOC_TYPES_LAND_ROFO = [
        'Land ROFO',
        'Land RofO',
        'Land RofO Re-issuance',
        'Land RofO Re-issuance (Legacy)',
    ];

    private const DOC_TYPES_LAND_RECOMMENDATION = [
        'Land Recommendation',
        'Recommendation',
        'Recommendation For Grant',
        'OSS Recommendation For Grant',
    ];

    private const DOC_TYPES_SLTR_RECOMMENDATION = ['SLTR Recommendation'];

    private const DOC_TYPES_SLTR_ROFO = ['SLTR RofO'];

    private const DOC_TYPES_ST_ROFO = ['ST ROFO', 'ST RofO', 'ST RoFO'];

    /**
     * Which security-paper release reason a master delete uses.
     *
     * A sheet that has been through the printer carries the details of a record
     * that is about to stop existing, so it is retired rather than handed to the
     * next document. An unprinted sheet is blank and goes back in the pool.
     */
    private function releaseReason(bool $wasPrinted): string
    {
        return $wasPrinted
            ? SecurityPaperCodeService::REASON_MISTAKE_OUTPUT
            : SecurityPaperCodeService::REASON_DROP_SPC;
    }

    private function connection()
    {
        return DB::connection('sqlsrv');
    }

    // =====================================================================
    // LAND
    // =====================================================================

    /**
     * Erase a Land recommendation and everything it produced.
     */
    public function purgeLandRecommendation(LandRecommendation $rec): array
    {
        $counts = $this->purgeLandRofo($rec, true);

        // The recommendation's own print history, on top of the RofO's.
        $fileNumber = trim((string) $rec->file_number);
        if ($fileNumber !== '') {
            $counts['print_logs'] += $this->deletePrintLogs($fileNumber, self::DOC_TYPES_LAND_RECOMMENDATION);
        }

        $counts['land_recommendations'] = (int) $this->connection()
            ->table('land_recommendations')
            ->where('id', $rec->id)
            ->delete();

        return $counts;
    }

    /**
     * Undo a Land RofO: the issuance, its PRA row, its security paper and its
     * print history. The recommendation itself survives, still approved, and can
     * be generated again.
     *
     * @param bool $recordGoing true when the recommendation is being deleted too,
     *                          in which case there is no point writing the cleared
     *                          RofO columns back to a row about to disappear.
     */
    public function purgeLandRofo(LandRecommendation $rec, bool $recordGoing = false): array
    {
        $fileNumber = trim((string) $rec->file_number);
        $printed    = (int) ($rec->rofo_print_count ?? 0) > 0;

        $counts = [
            'pra'                        => 0,
            'security_paper_released'    => 0,
            'security_codes'             => 0,
            'print_logs'                 => 0,
            'land_recommendations'       => 0,
        ];

        // The security paper first: releasing it also drops its `security_codes`
        // tracking row, and both have to happen before the serial is cleared.
        if (filled($rec->land_rofo_serial_no)) {
            $counts['security_codes'] = (int) $this->connection()
                ->table('security_codes')
                ->where('security_paper_code', $rec->land_rofo_serial_no)
                ->where('assigned_to', 'Land ROFO')
                ->count();

            SecurityPaperCodeService::release(
                $rec->land_rofo_serial_no,
                $this->releaseReason($printed),
                'Land ROFO',
                'Released by Master Delete of Land RofO ' . ($fileNumber !== '' ? $fileNumber : $rec->id)
            );
            $counts['security_paper_released'] = 1;
        }

        if ($fileNumber !== '') {
            // RofOPraSyncer writes exactly one PRA row per file number, stamped
            // with the source it came from. Matching on source as well as the file
            // number keeps a deed or an instrument registered against the same file
            // out of range — only the row this module created is ours to remove.
            $counts['pra'] = (int) $this->connection()->table('pra')
                ->whereIn('source', ['land_rofo', 'oss_rofo'])
                ->where(function ($q) use ($fileNumber) {
                    $q->where('mlsFNo', $fileNumber)->orWhere('fileno', $fileNumber);
                })
                ->delete();

            $counts['print_logs'] = $this->deletePrintLogs($fileNumber, self::DOC_TYPES_LAND_ROFO);
        }

        if (!$recordGoing) {
            $counts['land_recommendations'] = (int) $this->connection()->table('land_recommendations')
                ->where('id', $rec->id)
                ->update($this->clearedLandRofoColumns());
        }

        return $counts;
    }

    /**
     * Every column the RofO half of a land recommendation owns, back to unissued.
     * The recommendation's own fields — applicant, location, fees, approval — are
     * untouched: undoing an issuance is not undoing the recommendation.
     */
    private function clearedLandRofoColumns(): array
    {
        return [
            'rofo_status'                   => LandRecommendation::ROFO_PENDING,
            'rofo_generated_at'             => null,
            'rofo_date_generated'           => null,
            'rofo_time_generated'           => null,
            'rofo_print_count'              => 0,
            'rofo_originals_printed_at'     => null,
            'rofo_office_copies_printed_at' => null,
            'rofo_print_run_mode'           => null,
            'rofo_survey_fees'              => null,
            'rofo_dev_charge'               => null,
            'rofo_director_survey'          => null,
            'rofo_licensed_surveyor'        => null,
            'rofo_land_use_category'        => null,
            'land_rofo_serial_no'           => null,
            'date_issued'                   => null,
            'updated_by'                    => Auth::id(),
            'updated_at'                    => now(),
        ];
    }

    // =====================================================================
    // SLTR
    // =====================================================================

    public function purgeSltrRecommendation(SltrRecommendation $rec): array
    {
        $counts = $this->purgeSltrRofo($rec, true);

        $identifier = trim((string) $rec->sltr_number);
        if ($identifier !== '') {
            $counts['print_logs'] += $this->deletePrintLogs($identifier, self::DOC_TYPES_SLTR_RECOMMENDATION);
        }

        $counts['sltr_recommendations'] = (int) $this->connection()
            ->table('sltr_recommendations')
            ->where('id', $rec->id)
            ->delete();

        return $counts;
    }

    public function purgeSltrRofo(SltrRecommendation $rec, bool $recordGoing = false): array
    {
        $identifier = trim((string) $rec->sltr_number);
        $printed    = (int) ($rec->rofo_print_count ?? 0) > 0;

        $counts = [
            'pra'                     => 0,
            'security_paper_released' => 0,
            'security_codes'          => 0,
            'print_logs'              => 0,
            'sltr_recommendations'    => 0,
        ];

        if (filled($rec->sltr_rofo_serial_no)) {
            $counts['security_codes'] = (int) $this->connection()
                ->table('security_codes')
                ->where('security_paper_code', $rec->sltr_rofo_serial_no)
                ->where('assigned_to', 'SLTR ROFO')
                ->count();

            SecurityPaperCodeService::release(
                $rec->sltr_rofo_serial_no,
                $this->releaseReason($printed),
                'SLTR ROFO',
                'Released by Master Delete of SLTR RofO ' . ($identifier !== '' ? $identifier : $rec->id)
            );
            $counts['security_paper_released'] = 1;
        }

        if ($identifier !== '') {
            // SLTR has no file_number column: the SLTR number is both the file
            // identifier and the rofo_number — see RofoPraSyncer::syncSltr().
            $counts['pra'] = (int) $this->connection()->table('pra')
                ->where('source', 'sltr_rofo')
                ->where(function ($q) use ($identifier) {
                    $q->where('mlsFNo', $identifier)->orWhere('fileno', $identifier);
                })
                ->delete();

            $counts['print_logs'] = $this->deletePrintLogs($identifier, self::DOC_TYPES_SLTR_ROFO);
        }

        if (!$recordGoing) {
            $counts['sltr_recommendations'] = (int) $this->connection()->table('sltr_recommendations')
                ->where('id', $rec->id)
                ->update([
                    'rofo_status'            => SltrRecommendation::ROFO_PENDING,
                    'rofo_generated_at'      => null,
                    'rofo_date_generated'    => null,
                    'rofo_print_count'       => 0,
                    'rofo_director_survey'   => null,
                    'rofo_licensed_surveyor' => null,
                    'sltr_rofo_serial_no'    => null,
                    'date_issued'            => null,
                    'printed_at'             => null,
                    'updated_by'             => Auth::id(),
                    'updated_at'             => now(),
                ]);
        }

        return $counts;
    }

    // =====================================================================
    // ST
    // =====================================================================

    /**
     * Erase the ST RofOs for a set of unit (sub) applications.
     *
     * One unit is the single-row delete off the SUA table; a whole primary's units
     * is what the PUA table's row stands for, since a PUA RofO is generated for
     * every unit at once and there is no one row to remove on its own.
     *
     * The unit applications themselves are never touched — only the `rofo` rows,
     * their security papers, their PRA entries and their print history.
     *
     * @param int[] $subApplicationIds
     */
    public function purgeStRofo(array $subApplicationIds): array
    {
        $subApplicationIds = array_values(array_unique(array_filter(
            array_map('intval', $subApplicationIds)
        )));

        $counts = [
            'rofo'                    => 0,
            'pra'                     => 0,
            'security_paper_released' => 0,
            'security_codes'          => 0,
            'print_logs'              => 0,
        ];

        if (empty($subApplicationIds)) {
            return $counts;
        }

        $rofos = $this->connection()->table('rofo')
            ->whereIn('sub_application_id', $subApplicationIds)
            ->get();

        // The file number a PRA row and a print log are keyed by lives on the unit
        // application, not on the RofO.
        $fileNumbers = $this->connection()->table('subapplications')
            ->whereIn('id', $subApplicationIds)
            ->pluck('fileno')
            ->map(fn ($f) => trim((string) $f))
            ->filter()
            ->unique()
            ->values();

        foreach ($rofos as $rofo) {
            if (!filled($rofo->security_paper_code ?? null)) {
                continue;
            }

            $counts['security_codes'] += (int) $this->connection()
                ->table('security_codes')
                ->where('security_paper_code', $rofo->security_paper_code)
                ->where('assigned_to', 'ST ROFO')
                ->count();

            SecurityPaperCodeService::release(
                $rofo->security_paper_code,
                $this->releaseReason((int) ($rofo->print_counter ?? 0) > 0),
                'ST ROFO',
                'Released by Master Delete of ST RofO ' . ($rofo->rofo_no ?? $rofo->id)
            );
            $counts['security_paper_released']++;
        }

        foreach ($fileNumbers as $fileNumber) {
            $counts['pra'] += (int) $this->connection()->table('pra')
                ->where('source', 'st_rofo')
                ->where(function ($q) use ($fileNumber) {
                    $q->where('mlsFNo', $fileNumber)->orWhere('fileno', $fileNumber);
                })
                ->delete();

            $counts['print_logs'] += $this->deletePrintLogs($fileNumber, self::DOC_TYPES_ST_ROFO);
        }

        $counts['rofo'] = (int) $this->connection()->table('rofo')
            ->whereIn('sub_application_id', $subApplicationIds)
            ->delete();

        return $counts;
    }

    /**
     * Reset an ST planning recommendation back to pending.
     *
     * ST keeps no recommendation record — the decision is three columns on the
     * application itself — so there is nothing to delete, only to unmake. The
     * application, its units, its memo and its RofO all survive; the file simply
     * goes back to awaiting a planning decision.
     *
     * @param string $scope 'primary' (mother_applications) or 'unit' (subapplications)
     */
    public function resetStRecommendation(string $scope, int $id): array
    {
        $isPrimary = $scope === 'primary';
        $table     = $isPrimary ? 'mother_applications' : 'subapplications';

        $updated = (int) $this->connection()->table($table)
            ->where('id', $id)
            ->update([
                'planning_recommendation_status' => 'Pending',
                'planning_approval_date'         => null,
                // The comment column is named differently on the two tables.
                ($isPrimary ? 'recomm_comments' : 'planning_recomm_comments') => null,
                'updated_at'                     => now(),
            ]);

        return [$table => $updated];
    }

    // =====================================================================

    /**
     * Drop the print history for one document, its white-copy proofs included.
     *
     * PrintLog is otherwise append-only on purpose — "printed since the last
     * reset" depends on nothing ever being removed. A master delete is the one
     * exception: the document these rows describe is being erased, and leaving
     * them behind would make a fresh record reusing the same file number look
     * like it had already been printed.
     */
    private function deletePrintLogs(string $referenceNumber, array $documentTypes): int
    {
        $types = $documentTypes;
        foreach ($documentTypes as $type) {
            $types[] = \App\Models\PrintLog::whiteCopyType($type);
        }

        return (int) $this->connection()->table('print_logs')
            ->where('reference_number', $referenceNumber)
            ->whereIn('document_type', $types)
            ->delete();
    }
}
