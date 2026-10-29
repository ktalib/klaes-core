<?php

namespace App\Support;

/**
 * Plot Extension: why each row action is not available yet (or any more), or null
 * when it is. The row menu, the batch checkboxes and the endpoints all read this,
 * so a disabled menu item cannot be reached another way.
 *
 * Order of the steps (as on the other parcel-update registers):
 *   KAMMA / Physical Planning (Master JSI clearance)
 *   -> Generate Recommendation -> Approve / Reject
 *   -> Generate Application -> Print Application -> commissioning.
 */
class PlotExtensionWorkflow
{
    /**
     * @param  object  $record   a plot_extension_applications row
     * @param  bool    $cleared  Master JSI clearance (MasterJsiGate)
     * @return array<string, ?string>
     */
    public static function blockers($record, bool $cleared): array
    {
        $status = (string) $record->status;
        $commissioned = $status === 'commissioned';
        $pending = !in_array($status, ['approved', 'rejected', 'commissioned'], true);
        $approved = in_array($status, ['approved', 'commissioned'], true);
        $kammaDone = strcasecmp(trim((string) ($record->knupda_status ?? '')), 'Approved') === 0;
        $fromDuplex = str_starts_with((string) $record->remarks, '[Duplex ');

        $decided = $pending ? null
            : ($commissioned ? 'The extension has already been commissioned.' : "This application has already been {$status}.");
        $planning = $cleared ? null : 'Complete KAMMA / Physical Planning (approved Master JSI) first.';
        $recommendation = $record->recommendation_generated_at ? null : 'Generate the recommendation first.';
        $application = $planning ?? $recommendation ?? ($approved ? null : 'Approve the application first.');

        return [
            'kamma' => $decided ?? ($kammaDone ? 'Already approved by KAMMA / Physical Planning.' : null),
            // Still open once approved: clearing the Master JSI approves an extension before
            // its memo exists, and the application cannot be generated without the memo.
            'generate-recommendation' => ($commissioned || $status === 'rejected' ? $decided : null) ?? $planning
                ?? ($record->recommendation_generated_at ? 'Recommendation already generated.' : null),
            'print-recommendation' => $planning ?? $recommendation,
            'decision' => $decided ?? $planning ?? $recommendation,
            'generate-application' => ($commissioned && !$record->application_generated_at ? $decided : null) ?? $application
                ?? ($record->application_generated_at ? 'Application already generated.' : null),
            'print-application' => $application ?? ($record->application_generated_at ? null : 'Generate the application first.'),
            'edit' => $fromDuplex ? 'Raised by a Duplex: change it through the Duplex.'
                : ($commissioned ? 'The extension has already been commissioned.'
                : ($status === 'rejected' ? 'Rejected applications cannot be edited.' : null)),
            'delete' => $approved ? ($commissioned ? 'Commissioned applications cannot be deleted.' : 'Approved applications cannot be deleted.') : null,
        ];
    }
}
