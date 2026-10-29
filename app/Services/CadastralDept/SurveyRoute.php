<?php

namespace App\Services\CadastralDept;

use App\Models\Cadastral\CadastralSurveyor;
use App\Models\CadastralDept\CadastralCase;
use App\Services\InstrumentWorkflow\WorkflowGuardException;

/**
 * Final Charting's two ways on (user, 2026-10-28): a file surveyed by a Government Surveyor goes to
 * the SG to sign and upload the Final Survey Plan; a private surveyor's file skips the SG and goes
 * straight to AD Cad Info.
 *
 * Government = the Computation File's surveyor is one of the Survey Department's own staff (the
 * cadastral_surveyors copies of surveyors_list, linked by surveyors_list_id; see SurveyStaffSync).
 * Any other surveyor is private. With no surveyor on the file both answers stay open.
 */
class SurveyRoute
{
    public const STEP = 'final_charting';
    public const GOVERNMENT = 'government';
    public const PRIVATE = 'private';

    /** 'government', 'private', or null when the file has no surveyor recorded. */
    public function kind(CadastralCase $case): ?string
    {
        try {
            $jacket = FieldJackets::installed() ? app(FieldJackets::class)->forCase($case) : null;
            $surveyor = $jacket && $jacket->cadastral_surveyor_id ? CadastralSurveyor::find($jacket->cadastral_surveyor_id) : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
        if (!$surveyor) {
            return null;
        }

        return !empty($surveyor->surveyors_list_id) ? self::GOVERNMENT : self::PRIVATE;
    }

    /** At Final Charting, offer only the answer the file's surveyor calls for. */
    public function filter(CadastralCase $case, array $actions): array
    {
        if ($case->current_step !== self::STEP || !($kind = $this->kind($case))) {
            return $actions;
        }
        $mine = array_values(array_filter($actions, fn ($a) => ($a['outcome'] ?? null) === $kind));

        return $mine ?: $actions;
    }

    /** Refuse the other route when the surveyor is known (CaseWorkflow::advance). */
    public function assertAllowed(CadastralCase $case, string $outcome): void
    {
        if ($case->current_step !== self::STEP || !in_array($outcome, [self::GOVERNMENT, self::PRIVATE], true)) {
            return;
        }
        $kind = $this->kind($case);
        if ($kind && $kind !== $outcome) {
            throw WorkflowGuardException::because($kind === self::GOVERNMENT
                ? 'This file was surveyed by a Government Surveyor: it goes to the SG for signing.'
                : 'This file was surveyed by a private surveyor: it skips the SG and goes to AD Cad Info.');
        }
    }
}
