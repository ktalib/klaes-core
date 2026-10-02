<?php

namespace App\Support;

/**
 * Logger for the Recommendation capture screen (/land-recommendations/create).
 *
 * Writes to storage/logs/land_recommendation.log — see the "land_recommendation"
 * channel in config/logging.php and the shared behaviour in {@see ChannelLog}.
 *
 * Covers the whole screen rather than just the inserts, because that is where the
 * questions come from: a batch save can end in a validation rejection, a partial
 * POST truncated by max_input_vars, a duplicate-file clash, a rolled-back
 * transaction, or a draft the officer thought was saved. LandRecommendationController
 * (create/store/storeBatch), LandRecommendationBatchDraftController (autosave,
 * resume, discard) and the browser itself (via
 * LandRecommendationDiagnosticsController::clientLog) all land here, stamped with
 * the same user/ip/route, so one capture reads as one timeline.
 *
 * Entries carry counts, file numbers and outcomes — the identifiers needed to find
 * a record — not a mirror of every field the officer typed.
 */
class LandRecommendationLog extends ChannelLog
{
    public const CHANNEL = 'land_recommendation';
}
