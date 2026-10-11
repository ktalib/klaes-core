<?php

namespace App\Support;

class SubdivisionWorkflow
{
    /** The same prerequisites govern menu actions and their endpoints. */
    public static function blockers($record, bool $cleared): array
    {
        $commissioned = $record->status === 'commissioned' || (int) $record->commissioned_count > 0;
        $pending = $record->status === 'pending' && !$commissioned;
        $approved = in_array($record->status, ['approved', 'commissioned'], true);
        $planning = $cleared ? null : 'Complete KAMMA / Physical Planning clearance first.';
        $recommendation = $record->recommendation_generated_at ? null : 'Generate the recommendation first.';
        $decision = $pending ? null : ($commissioned ? 'Plots have already been commissioned.' : 'This application has already been '.$record->status.'.');
        $application = $planning ?? $recommendation ?? ($approved ? null : 'Approve the application first.');

        return [
            'planning' => $decision ?? ($cleared ? 'Planning clearance is already approved.' : null),
            'generate-recommendation' => $decision ?? $planning ?? ($record->recommendation_generated_at ? 'Recommendation already generated.' : null),
            'print-recommendation' => $planning ?? $recommendation,
            'decision' => $decision ?? $planning ?? $recommendation,
            'generate-application' => $application ?? ($record->application_generated_at ? 'Application already generated.' : null),
            'print-application' => $application ?? ($record->application_generated_at ? null : 'Generate the application first.'),
        ];
    }
}
