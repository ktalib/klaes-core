<?php

namespace App\Services;

use App\Models\Cadastral\CadastralSurveyJob;
use App\Models\Survey\SurveyChangeReport;
use App\Models\Survey\SurveyIts;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Instruction to Survey that GKN records attach to (user decision, 2026-10-25):
 * Survey's own (survey_its) or one issued by Cadastral (cadastral_survey_jobs, read
 * only). Keyed "survey-<id>" or "cadastral-<id>" in forms and URLs.
 *
 * Shared by the Surveyors Report, List of Coordinates and Field Observations.
 *
 * A third kind, "private-<id>", is a Surveyor's Report (Private) under
 * Examination/Verification (change list p.2, 2026-10-29): the same List of
 * Coordinates, Beacon Nos, Field Observations and Sketch Plan, on the report.
 * It is never in all(), so the GKN lists stay government-only, and its pages
 * live under Examination's routes ('routes' prefix) with a link back to the report.
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

    /**
     * Surveyor's Reports (Private) not yet issued, newest first: key => anchor. For the
     * Computation & Field Observations app (2026-10-30); all() stays government-only.
     */
    public function privateOpen(): array
    {
        $out = [];
        foreach (SurveyChangeReport::where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', SurveyChangeReport::ISSUED))->orderByDesc('id')->get() as $c) {
            $out['private-' . $c->id] = $this->fromPrivate($c);
        }

        return $out;
    }

    public function resolve(string $key): ?array
    {
        if (! preg_match('/^(survey|cadastral|private)-(\d+)$/', $key, $m)) return null;

        if ($m[1] === 'private') {
            $c = SurveyChangeReport::find((int) $m[2]);

            return $c ? $this->fromPrivate($c) : null;
        }

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
        foreach (['survey_its_id', 'cadastral_survey_job_id', 'survey_change_report_id'] as $col) {
            if (isset($anchor['keys'][$col])) {
                return $q->where($col, $anchor['keys'][$col]);
            }
        }

        return $q->whereRaw('1 = 0');
    }

    /** Whether a coordinate / observation row belongs to the anchor. */
    public static function owns($row, array $anchor): bool
    {
        foreach (['survey_its_id', 'cadastral_survey_job_id', 'survey_change_report_id'] as $col) {
            if (isset($anchor['keys'][$col])) {
                return (int) $row->{$col} === (int) $anchor['keys'][$col];
            }
        }

        return false;
    }

    private function fromSurvey(SurveyIts $i): array
    {
        return [
            'key'     => 'survey-' . $i->id,
            'source'  => 'survey',
            'label'   => "{$i->its_number} · {$i->file_number}" . ($i->surveyor_name ? " · {$i->surveyor_name}" : '') . ' (Survey)',
            // A finished job's records are the record of the survey: no more changes.
            'locked'  => $i->isClosed(),
            'routes'  => 'survey-module.gkn',
            'back'    => null,
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
            'routes'  => 'survey-module.gkn',
            'back'    => null,
            'keys'    => ['its_source' => 'cadastral', 'cadastral_survey_job_id' => $j->id],
            'prefill' => ['its_number' => $j->its_number, 'job_ref' => $j->job_number, 'file_number' => $j->file_number,
                          'grantee_name' => $j->file_title, 'plot_no' => $j->prop_plot, 'district' => $j->prop_district, 'lga' => $j->prop_lga,
                          'location' => implode(', ', array_filter([$j->prop_district, $j->prop_lga])) ?: null, 'surveyor_name' => $j->surveyor_name],
        ];
    }

    private function fromPrivate(SurveyChangeReport $c): array
    {
        return [
            'key'     => 'private-' . $c->id,
            'source'  => 'private',
            'label'   => "{$c->report_ref} · {$c->file_number}" . ($c->surveyor_name ? " · {$c->surveyor_name}" : '') . ' (Private)',
            // An issued report is the record of the examination: no more changes.
            'locked'  => $c->isIssued(),
            'routes'  => 'survey-module.workflow.private',
            'back'    => ['url' => route('survey-module.workflow.change-reports.edit', $c), 'label' => "Surveyor's Report {$c->report_ref}"],
            'keys'    => ['its_source' => 'private', 'survey_change_report_id' => $c->id],
            'prefill' => ['its_number' => $c->report_ref, 'job_ref' => $c->report_ref, 'file_number' => $c->file_number,
                          'grantee_name' => $c->file_title, 'plot_no' => $c->plot_no, 'district' => $c->district, 'lga' => $c->lga,
                          'location' => implode(', ', array_filter([$c->district, $c->lga])) ?: null, 'surveyor_name' => $c->surveyor_name],
        ];
    }
}
