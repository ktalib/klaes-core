<?php

namespace App\Support;

use App\Models\MasterJsiReport;

/**
 * The planning clearance a parcel update needs before it can go up.
 *
 * This used to be the KAMMA/Physical Planning handshake: a four-field modal whose
 * knupda_status = 'Approved' gated Generate Recommendation, Generate Application and
 * Approve on all six workflows, and which on the three SPU controllers silently
 * flipped the whole application to approved. It stood in for a site inspection that
 * had nowhere to live. Now the inspection exists, so the gate reads it.
 *
 * TWO ways through, and the second is not optional:
 *
 *   1. an APPROVED Master JSI for the record — the rule from here on
 *   2. knupda_status = 'Approved' — the legacy pass
 *
 * Without (2) every record cleared through the handshake before the cutover would
 * deadlock: the modal is gone, so there would be no way to satisfy a gate they had
 * already satisfied. The knupda_* columns are kept for the same reason, and
 * DuplexCommitService still copies them forward into the registry.
 *
 * Ask through here rather than reading `status` at a call site — the legacy arm is
 * easy to forget, and a call site that forgets it is a page of greyed buttons with
 * no way to ungrey them.
 */
class MasterJsiGate
{
    /**
     * Has the planning clearance been given for this record?
     *
     * @param  string      $subjectType         a MasterJsiReport::SUBJECTS slug
     * @param  int         $subjectId           the row id in that workflow's table
     * @param  string|null $legacyKnupdaStatus  the record's knupda_status, if it has one
     */
    public static function cleared(string $subjectType, int $subjectId, $legacyKnupdaStatus = null): bool
    {
        if (self::legacyPass($legacyKnupdaStatus)) {
            return true;
        }

        return MasterJsiReport::visible()
            ->forSubject($subjectType, $subjectId)
            ->where('status', MasterJsiReport::STATUS_APPROVED)
            ->exists();
    }

    /**
     * The clearance for a record already in hand — same rule, no query per row.
     *
     * The listings ask this once per row, so they resolve the whole page through
     * clearedMap() and pass the answer in.
     */
    public static function clearedFor($record, string $subjectType): bool
    {
        if ($record === null) {
            return false;
        }

        return self::cleared($subjectType, (int) $record->id, $record->knupda_status ?? null);
    }

    /**
     * Which of these record ids are cleared: [id => bool].
     *
     * One query for the page instead of one per row. The listings render 25-50 rows
     * and each row asks the question two or three times over (Generate, Print,
     * Approve), so the per-row form of this would be the page's slowest query by a
     * wide margin.
     *
     * @param  iterable $records  rows carrying `id` and, where they have one, `knupda_status`
     */
    public static function clearedMap(string $subjectType, $records): array
    {
        $rows = [];
        foreach ($records as $record) {
            $rows[(int) $record->id] = $record->knupda_status ?? null;
        }

        if (empty($rows)) {
            return [];
        }

        $approved = MasterJsiReport::visible()
            ->where('subject_type', $subjectType)
            ->whereIn('subject_id', array_keys($rows))
            ->where('status', MasterJsiReport::STATUS_APPROVED)
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $approved = array_flip($approved);

        $map = [];
        foreach ($rows as $id => $knupdaStatus) {
            $map[$id] = isset($approved[$id]) || self::legacyPass($knupdaStatus);
        }

        return $map;
    }

    /** The report itself, when one exists — for the View/Edit menu item. */
    public static function reportFor(string $subjectType, int $subjectId): ?MasterJsiReport
    {
        return MasterJsiReport::visible()
            ->forSubject($subjectType, $subjectId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Which report, if any, each of these records has: [id => MasterJsiReport].
     *
     * The listings need this beside clearedMap() to decide between "Capture Master
     * JSI" and "View/Edit Master JSI", so it is resolved for the page in one query
     * for the same reason.
     */
    public static function reportMap(string $subjectType, $records): array
    {
        $ids = [];
        foreach ($records as $record) {
            $ids[] = (int) $record->id;
        }

        if (empty($ids)) {
            return [];
        }

        return MasterJsiReport::visible()
            ->where('subject_type', $subjectType)
            ->whereIn('subject_id', $ids)
            ->orderBy('id')
            ->get()
            ->keyBy(fn ($report) => (int) $report->subject_id)
            ->all();
    }

    /** Why the gate is shut, in the words the menu shows on a greyed item. */
    public static function blocker(?MasterJsiReport $report): string
    {
        if ($report === null) {
            return 'Capture the Master JSI first';
        }

        return match ($report->status) {
            MasterJsiReport::STATUS_REJECTED => 'The Master JSI was rejected — capture a new one',
            MasterJsiReport::STATUS_APPROVED => '',
            default => 'The Master JSI is awaiting approval',
        };
    }

    private static function legacyPass($knupdaStatus): bool
    {
        return $knupdaStatus !== null
            && strcasecmp((string) $knupdaStatus, 'Approved') === 0;
    }
}
