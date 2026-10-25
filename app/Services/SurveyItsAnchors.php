<?php

namespace App\Services;

use App\Models\Cadastral\CadastralSurveyJob;
use App\Models\Survey\SurveyIts;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Instruction to Survey that GKN records attach to (user decision, 2026-10-25):
 * Survey's own (survey_its) or one issued by Cadastral (cadastral_survey_jobs, read
 * only). Keyed "survey-<id>" or "cadastral-<id>" in forms and URLs.
 *
 * Shared by the Surveyors Report, List of Coordinates and Field Observations.
 */
class SurveyItsAnchors
{
    /** Every issued, uncancelled I to S, newest first: key => anchor. */
    public function all(): array
    {
        $out = [];
        foreach (SurveyIts::whereNotNull('its_number')->where('status', '!=', SurveyIts::CANCELLED)->orderByDesc('id')->get() as $i) {
            $out['survey-' . $i->id] = $this->fromSurvey($i);
        }
        foreach (CadastralSurveyJob::whereNotNull('its_number')->orderByDesc('id')->get() as $j) {
            $out['cadastral-' . $j->id] = $this->fromCadastral($j);
        }

        return $out;
    }

    public function resolve(string $key): ?array
    {
        if (! preg_match('/^(survey|cadastral)-(\d+)$/', $key, $m)) return null;

        if ($m[1] === 'survey') {
            $i = SurveyIts::whereNotNull('its_number')->find((int) $m[2]);

            return $i && $i->status !== SurveyIts::CANCELLED ? $this->fromSurvey($i) : null;
        }

        $j = CadastralSurveyJob::whereNotNull('its_number')->find((int) $m[2]);

        return $j ? $this->fromCadastral($j) : null;
    }

    /** Scope a query on a table carrying its_source / survey_its_id / cadastral_survey_job_id to one anchor. */
    public static function scope(Builder $q, array $anchor): Builder
    {
        return isset($anchor['keys']['survey_its_id'])
            ? $q->where('survey_its_id', $anchor['keys']['survey_its_id'])
            : $q->where('cadastral_survey_job_id', $anchor['keys']['cadastral_survey_job_id']);
    }

    private function fromSurvey(SurveyIts $i): array
    {
        return [
            'key'     => 'survey-' . $i->id,
            'source'  => 'survey',
            'label'   => "{$i->its_number} · {$i->file_number}" . ($i->surveyor_name ? " · {$i->surveyor_name}" : '') . ' (Survey)',
            // A finished job's records are the record of the survey: no more changes.
            'locked'  => $i->isClosed(),
            'keys'    => ['its_source' => 'survey', 'survey_its_id' => $i->id],
            'prefill' => ['its_number' => $i->its_number, 'job_ref' => $i->job_ref, 'file_number' => $i->file_number,
                          'grantee_name' => $i->file_title, 'plot_no' => $i->plot_no, 'district' => $i->district, 'lga' => $i->lga,
                          'location' => $i->location, 'surveyor_name' => $i->surveyor_name],
        ];
    }

    private function fromCadastral(CadastralSurveyJob $j): array
    {
        return [
            'key'     => 'cadastral-' . $j->id,
            'source'  => 'cadastral',
            'label'   => "{$j->its_number} · {$j->file_number}" . ($j->surveyor_name ? " · {$j->surveyor_name}" : '') . ' (Cadastral)',
            'locked'  => in_array($j->status, ['Accepted', 'Cancelled'], true),
            'keys'    => ['its_source' => 'cadastral', 'cadastral_survey_job_id' => $j->id],
            'prefill' => ['its_number' => $j->its_number, 'job_ref' => $j->job_number, 'file_number' => $j->file_number,
                          'grantee_name' => $j->file_title, 'plot_no' => $j->prop_plot, 'district' => $j->prop_district, 'lga' => $j->prop_lga,
                          'location' => implode(', ', array_filter([$j->prop_district, $j->prop_lga])) ?: null, 'surveyor_name' => $j->surveyor_name],
        ];
    }
}
