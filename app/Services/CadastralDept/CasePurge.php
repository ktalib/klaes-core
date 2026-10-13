<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use Illuminate\Support\Facades\DB;

/**
 * Master Delete of a Cadastral Department file: the case and every row the
 * workflow made for it (movements, tasks, FMC and its log, charting cards and
 * beacons, field jacket and its reassignments, correspondence custody, Yes/No
 * form). The survey job is not deleted: it was registered before it was attached
 * (user, 2026-10-12), so its file, surveyor and I to S are cleared and it waits to
 * be attached again; its own survey location stays.
 *
 * Records the case only reads, or that belong to the file rather than the case,
 * are kept: the file number and its indexing, the correspondence file (shadow
 * file, csf flags), the plan description, and P&D bills. A live P&D bill blocks
 * the delete, because Deeds Bill Balance reads it as the survey fee.
 *
 * The caller runs this inside a sqlsrv transaction.
 */
class CasePurge
{
    private const CONN = 'sqlsrv';

    /** Reasons the case cannot be deleted; empty when it can. */
    public function blockers(CadastralCase $case): array
    {
        $out = [];
        if ($bill = PndBills::liveBill((string) $case->file_number)) {
            $out[] = "{$case->file_number} has a live P&D bill ({$bill->bill_ref}). Cancel the bill first.";
        }

        return $out;
    }

    /** @return array<string,int> rows deleted, per table */
    public function purge(CadastralCase $case): array
    {
        $db = DB::connection(self::CONN);
        $schema = $db->getSchemaBuilder();
        $id = $case->id;
        $counts = [];
        $del = function (string $table, string $col, $value) use ($db, $schema, &$counts) {
            if (!$schema->hasTable($table)) {
                return;
            }
            $q = $db->table($table);
            is_iterable($value) ? $q->whereIn($col, collect($value)->all()) : $q->where($col, $value);
            if ($n = $q->delete()) {
                $counts[$table] = $n;
            }
        };
        $ids = fn (string $table) => $schema->hasTable($table)
            ? $db->table($table)->where('cadastral_case_id', $id)->pluck('id') : collect();

        $fmcs = $ids('cadastral_fmcs');
        if ($fmcs->isNotEmpty()) {
            $del('cadastral_fmc_logs', 'cadastral_fmc_id', $fmcs);
        }
        $del('cadastral_fmcs', 'cadastral_case_id', $id);

        $charts = $ids('cadastral_chartings');
        if ($charts->isNotEmpty()) {
            $del('cadastral_charting_beacons', 'cadastral_charting_id', $charts);
        }
        $del('cadastral_chartings', 'cadastral_case_id', $id);

        $jackets = $ids('cadastral_field_jackets');
        if ($jackets->isNotEmpty()) {
            $del('cadastral_field_jacket_assignments', 'cadastral_field_jacket_id', $jackets);
        }
        $del('cadastral_field_jackets', 'cadastral_case_id', $id);
        if ($schema->hasTable('cadastral_survey_jobs') && $schema->hasColumn('cadastral_survey_jobs', 'cadastral_case_id')) {
            $detached = $db->table('cadastral_survey_jobs')->where('cadastral_case_id', $id)->update([
                'cadastral_case_id' => null, 'field_jacket_no' => null, 'cadastral_index_card_id' => null,
                'file_number' => null, 'file_title' => null,
                'cadastral_surveyor_id' => null, 'surveyor_name' => null, 'firm_name' => null,
                'its_number' => null, 'its_issued_at' => null, 'its_recipient' => null, 'its_issued_by' => null,
                'its_issued_by_user_id' => null, 'its_officer_post' => null, 'its_instructions' => null,
                'its_signature_path' => null, 'its_signature_method' => null, 'its_signature_verified_at' => null, 'its_signature_approved_by' => null,
                'status' => 'Draft', 'issued_at' => null, 'submitted_at' => null, 'accepted_at' => null,
                'updated_at' => now(),
            ]);
            if ($detached) {
                $counts['cadastral_survey_jobs (detached, back to the surveyor)'] = $detached;
            }
        }

        $del('cadastral_correspondence_custody', 'cadastral_case_id', $id);
        $del('cadastral_yes_no_forms', 'cadastral_case_id', $id);
        $del('cadastral_case_tasks', 'cadastral_case_id', $id);
        $del('cadastral_case_movements', 'cadastral_case_id', $id);
        $del('cadastral_cases', 'id', $id);

        return $counts;
    }
}
