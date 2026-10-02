<?php

namespace App\Services;

use App\Models\LandRecommendation;
use App\Services\Pra\RofoPraSyncer;
use Carbon\Carbon;

/**
 * Turns an approved recommendation into a generated RofO.
 *
 * Generation is more than a status flip: the RofO carries its own copies of the
 * survey fees, development charge, surveyor flags and land-use category, and the
 * record has to be pushed to PRA. Both entry points run through here so the
 * "Generate RofO" button and the automatic generation on batch approval can never
 * produce differently-shaped records.
 */
class LandRofoGenerator
{
    public function __construct(private RofoPraSyncer $praSyncer)
    {
    }

    /**
     * @param array $overrides Values keyed in by the user on the generate form.
     *                         Anything absent falls back to what the recommendation
     *                         already holds, which is what a quick-generate does.
     */
    public function generate(LandRecommendation $recommendation, array $overrides = []): LandRecommendation
    {
        $mergedDate = ($overrides['rofo_date_generated'] ?? null) ?: $recommendation->rofo_date_generated;
        $mergedTime = ($overrides['rofo_time_generated'] ?? null) ?: $recommendation->rofo_time_generated;

        $generatedAt = now();
        if ($mergedDate && $mergedTime) {
            $generatedAt = Carbon::parse($mergedDate . ' ' . $mergedTime);
        } elseif ($mergedDate) {
            $generatedAt = Carbon::parse($mergedDate);
        }

        if (empty($overrides['rofo_director_survey']))   $overrides['rofo_director_survey']   = $recommendation->rofo_director_survey;
        if (empty($overrides['rofo_licensed_surveyor'])) $overrides['rofo_licensed_surveyor'] = $recommendation->rofo_licensed_surveyor;
        if (empty($overrides['rofo_survey_fees']))       $overrides['rofo_survey_fees']       = $recommendation->survey_fees ?? $recommendation->preparation_fees;
        if (empty($overrides['rofo_land_use_category'])) $overrides['rofo_land_use_category'] = $recommendation->land_use;

        // development_charge is free text on the recommendation ("To follow" is
        // common) while rofo_dev_charge is treated as a number — the generate form
        // validates it `numeric` and the print templates run it through
        // number_format(), which throws a TypeError on a string. Only carry the
        // value across when it actually is a number.
        if (empty($overrides['rofo_dev_charge']) && is_numeric($recommendation->development_charge)) {
            $overrides['rofo_dev_charge'] = $recommendation->development_charge;
        }

        $recommendation->update(array_merge($overrides, [
            'rofo_status'         => LandRecommendation::ROFO_GENERATED,
            'rofo_generated_at'   => $generatedAt,
            'rofo_date_generated' => $mergedDate,
            'rofo_time_generated' => $mergedTime,
        ]));

        $fresh = $recommendation->fresh();
        $this->praSyncer->syncLand($fresh);

        return $fresh;
    }
}
