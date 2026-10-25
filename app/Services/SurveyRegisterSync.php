<?php

namespace App\Services;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseMovement;
use App\Models\Survey\SurveyRegisterFile;
use App\Services\CadastralDept\CaseWorkflow;
use Illuminate\Database\QueryException;

/**
 * Auto-registration for Survey > Register File (user transcript, 2026-10-25): any
 * file the Cadastral workflow sends to a Survey desk appears in Survey's register.
 *
 *   compensation_unit (Government acquisition check) -> a Verification line
 *   examination_unit  (examine the survey plan)      -> an Examination line
 *
 * Read from cadastral_case_movements (append-only), one line per movement, so a
 * file sent for verification and later for examination is two lines. Pulled when
 * the register is opened, which also brings in hand-overs made before this existed.
 * Nothing on the Cadastral side is changed.
 */
class SurveyRegisterSync
{
    public const DESKS_BY_PURPOSE = [
        SurveyRegisterFile::PURPOSE_VERIFICATION => 'compensation_unit',
        SurveyRegisterFile::PURPOSE_EXAMINATION  => 'examination_unit',
    ];

    /** @return int lines added */
    public function pull(): int
    {
        if (! CaseWorkflow::installed()) return 0;

        $purposeByDesk = array_flip(self::DESKS_BY_PURPOSE);

        $done = SurveyRegisterFile::withTrashed()->whereNotNull('cadastral_movement_id')->pluck('cadastral_movement_id')->all();

        $moves = CadastralCaseMovement::whereIn('to_desk', array_keys($purposeByDesk))
            ->when($done, fn ($q) => $q->whereNotIn('id', $done))
            ->orderBy('id')
            ->get();
        if ($moves->isEmpty()) return 0;

        $cases = CadastralCase::whereIn('id', $moves->pluck('cadastral_case_id')->unique())->get()->keyBy('id');

        $added = 0;
        foreach ($moves as $m) {
            $case = $cases->get($m->cadastral_case_id);
            if (! $case) continue;

            try {
                SurveyRegisterFile::create([
                    'register_ref'          => SurveyRegisterFile::nextRef('register_ref', 'SRF', 5),
                    'purpose'               => $purposeByDesk[$m->to_desk],
                    'source'                => SurveyRegisterFile::SOURCE_CADASTRAL,
                    'workflow'              => $case->workflow,
                    'cadastral_case_id'     => $case->id,
                    'cadastral_movement_id' => $m->id,
                    'cadastral_case_ref'    => $case->case_ref,
                    'file_type'             => $case->file_type,
                    'sub_type'              => $case->sub_type,
                    'allocation_route'      => $case->allocation_route,
                    'file_source'           => $case->file_source,
                    'file_source_other'     => $case->file_source_other,
                    'file_number'           => $case->file_number,
                    'file_indexing_id'      => $case->file_indexing_id,
                    'file_system'           => $case->file_system,
                    'tracking_id'           => $case->tracking_id,
                    'file_title'            => $case->file_title,
                    'plot_no'               => $case->plot_no,
                    'district'              => $case->district,
                    'lga'                   => $case->lga,
                    'location'              => $case->location,
                    'correspondence_fileno' => $case->correspondence_fileno,
                    'received_at'           => $m->acted_at ?? $m->created_at,
                    'status'                => SurveyRegisterFile::RECEIVED,
                    'remarks'               => $m->remarks,
                    'created_by'            => $m->acted_by,
                    'updated_by'            => $m->acted_by,
                ]);
                $added++;
            } catch (QueryException $e) {
                // Two people opening the register at once: the unique movement index
                // lets one of them add the line; the other simply skips it.
                if (! str_contains($e->getMessage(), 'survey_register_files_movement_unique')) throw $e;
            }
        }

        return $added;
    }
}
