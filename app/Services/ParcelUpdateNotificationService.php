<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ParcelUpdateNotificationService
{
    private const MODULE = 'parcel_update';

    public function __construct(
        protected UserNotificationService $notifier

    ) {}


    
    /**
     * Notify Land (MLS) users that a new Parcel Update application was created.
     */
    public function notifyCreated(string $type, int|null $appId, string $fileNo, string $fileTitle, string $applicantName = ''): void
    {
        $label = $this->typeLabel($type);
        $title = "New {$label} Application Submitted";
        $body  = "A new {$label} application for file {$fileNo}" . ($fileTitle ? " ({$fileTitle})" : '') . " has been submitted and is pending review.";

        $this->dispatchToLand($title, $body, [
            'type'           => $type,
            'app_id'         => $appId,
            'file_no'        => $fileNo,
            'file_title'     => $fileTitle,
            'applicant_name' => $applicantName,
            'event'          => 'created',
        ]);
    }

    /**
     * Notify Land (MLS) users that a Parcel Update application has been approved and is ready for commissioning.
     */
    public function notifyApproved(string $type, int|null $appId, string $fileNo, string $fileTitle, string $approvedBy = ''): void
    {
        $label = $this->typeLabel($type);
        $title = "{$label} Application Approved – Ready for Commissioning";
        $body  = "The {$label} application for file {$fileNo}" . ($fileTitle ? " ({$fileTitle})" : '') . " has been approved. Please proceed to commission the new file number.";

        $this->dispatchToLand($title, $body, [
            'type'        => $type,
            'app_id'      => $appId,
            'file_no'     => $fileNo,
            'file_title'  => $fileTitle,
            'approved_by' => $approvedBy,
            'event'       => 'approved',
        ]);
    }

    /**
     * Notify Deeds users that a file number has been commissioned for a Parcel Update.
     */
    public function notifyCommissioned(string $type, int|null $appId, string $sourceFileNo, string $newFileNo, string $commissionedBy = ''): void
    {
        $label = $this->typeLabel($type);
        $title = "File Number Commissioned for {$label}";
        $body  = "File number {$newFileNo} has been commissioned for the {$label} of {$sourceFileNo}.";

        $this->dispatchToDeeds($title, $body, [
            'type'             => $type,
            'app_id'           => $appId,
            'source_file_no'   => $sourceFileNo,
            'new_file_no'      => $newFileNo,
            'commissioned_by'  => $commissionedBy,
            'event'            => 'commissioned',
        ]);
    }

    /**
     * Notify Deeds that a Master JSI has been handed over.
     *
     * The handover itself is a stamp on the report — no registry writes — so this
     * notice is how Deeds learns there is something to read. Reuses the same Deeds
     * resolution as notifyCommissioned() rather than growing a second one.
     */
    public function notifyJsiSentToDeeds(
        string $type,
        int $reportId,
        string $jsiRef,
        string $fileNo,
        string $fileTitle = '',
        string $sentBy = ''
    ): void {
        $label = $this->typeLabel($type);
        $title = "Master JSI {$jsiRef} Sent to Deeds";
        $body  = "The Master JSI for the {$label} of file {$fileNo}"
            . ($fileTitle ? " ({$fileTitle})" : '')
            . " has been approved by Physical Planning and sent to Deeds.";

        $this->dispatchToDeeds($title, $body, [
            'type'       => $type,
            'report_id'  => $reportId,
            'jsi_ref'    => $jsiRef,
            'file_no'    => $fileNo,
            'file_title' => $fileTitle,
            'sent_by'    => $sentBy,
            'event'      => 'jsi_sent_to_deeds',
        ]);
    }

    // -------------------------------------------------------------------------

    /**
     * The table each parcel-update type lives in, so a notification can find the
     * officers on the record it is about. Same mapping MasterJsiReport keeps.
     */
    private const TYPE_TABLES = [
        'subdivision'       => 'plot_subdivision_applications',
        'separation'        => 'plot_separation_applications',
        'merger'            => 'plot_merger_applications',
        'extension'         => 'plot_extension_applications',
        'change_of_purpose' => 'change_of_purpose_applications',
        'duplex'            => 'duplex_parcel_updates',
    ];

    /**
     * Roles whose holders follow parcel updates as a matter of course, per side.
     *
     * Matched by NAME. `users.assign_role` is a comma-separated list of role names —
     * "Dashboard,CRM - Person,Log a File,…" — not of ids, which is the whole reason the
     * original filter on role id 10055 selected nobody and was replaced by a
     * department-wide sweep.
     *
     * The first two of each pair are the roles built for this work and currently have
     * almost no holders; the file-number roles are the ones people actually carry. Both
     * are listed so that assigning the purpose-built roles later widens the list on its
     * own, with no code change.
     */
    private const LAND_ROLES = [
        'Parcel/Title Management-Land',
        'Duplex Parcel Update-Land',
        'Generate New FileNo (MLSFileNo)',
        'Lands – Manage MLSFileNo',
    ];

    private const DEEDS_ROLES = [
        'Parcel/Title Management-Deeds',
        'Duplex Parcel Update-Deeds',
    ];

    /**
     * Who hears about one parcel update.
     *
     * Three groups, deduplicated:
     *
     *   1. the officers ON the record — who raised it, approved it, last touched it.
     *      captured_by is populated on every one of the 1,023 rows in these six tables,
     *      so this group is never empty for a real record and needs nothing assigned to
     *      anybody to work;
     *   2. anyone holding a parcel-update role for this side;
     *   3. the administrators, who carry the system.
     *
     * This replaces "everyone in the department", which sent each event to 168 Land or
     * 44 Deeds users — 4,056 notifications, 96% of them unread, because almost every
     * recipient had nothing to do with the record.
     *
     * Group 2 is deliberately additive rather than the whole rule: a role says who MAY
     * do parcel updates, not who is on this one, and the roles built for it have between
     * 0 and 1 holders today. Resting the whole thing on them would repeat the original
     * defect of notifying nobody.
     *
     * @param  string $side 'land' or 'deeds' — which roles count
     * @return array<int>   user ids
     */
    private function recipientsFor(string $type, ?int $appId, string $side): array
    {
        $ids = array_merge(
            $this->officersOnRecord($type, $appId),
            $this->holdersOfRoles($side === 'land' ? self::LAND_ROLES : self::DEEDS_ROLES),
            $this->administrators()
        );

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        return $this->minusOptedOut($ids);
    }

    /** The officers named on the record itself. */
    private function officersOnRecord(string $type, ?int $appId): array
    {
        $table = self::TYPE_TABLES[$type] ?? null;

        if (!$table || !$appId) {
            return [];
        }

        try {
            $columns = Schema::connection('sqlsrv')->getColumnListing($table);

            // Not every table carries every one of these; a merger has no approved_by.
            $wanted = array_values(array_intersect(
                ['captured_by', 'approved_by', 'updated_by', 'committed_by'],
                $columns
            ));

            if (empty($wanted)) {
                return [];
            }

            $row = DB::connection('sqlsrv')->table($table)->where('id', $appId)->first($wanted);

            return $row ? array_values(array_filter((array) $row)) : [];
        } catch (\Throwable $e) {
            Log::warning('ParcelUpdateNotificationService: could not read the officers on the record', [
                'type'  => $type,
                'id'    => $appId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Everyone carrying one of these roles.
     *
     * Matched on comma boundaries rather than as a bare substring: role names overlap
     * ("Parcel/Title Management-Land" sits inside nothing today, but "New ST FileNo"
     * inside "Generate New ST FileNo" is the shape of the problem), and a LIKE '%name%'
     * would hand the wrong people someone else's notifications.
     */
    private function holdersOfRoles(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }

        try {
            return DB::connection('sqlsrv')->table('users')
                ->where(function ($q) use ($roles) {
                    foreach ($roles as $role) {
                        // The stored value has no leading or trailing comma, so one is
                        // added on each side before matching ",<role>,".
                        $q->orWhereRaw(
                            "',' + REPLACE(ISNULL(assign_role, ''), ', ', ',') + ',' LIKE ?",
                            ['%,' . $role . ',%']
                        );
                    }
                })
                ->pluck('id')
                ->all();
        } catch (\Throwable $e) {
            Log::warning('ParcelUpdateNotificationService: could not read role holders', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function administrators(): array
    {
        try {
            return DB::connection('sqlsrv')->table('users')->where('is_admin', 1)->pluck('id')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Drop anyone who has turned in-app notifications off.
     *
     * user_notification_settings has carried this flag all along and this service has
     * never consulted it, so the one person who opted out has been notified anyway.
     * Absence of a settings row means on, which is how the rest of the system reads it.
     */
    private function minusOptedOut(array $ids): array
    {
        if (empty($ids)) {
            return $ids;
        }

        try {
            $off = DB::connection('sqlsrv')->table('user_notification_settings')
                ->whereIn('user_id', $ids)
                ->where('enable_in_app', 0)
                ->pluck('user_id')
                ->all();

            return array_values(array_diff($ids, array_map('intval', $off)));
        } catch (\Throwable $e) {
            // A missing settings table must not stop the notification.
            return $ids;
        }
    }

    /** Write one notification per recipient. */
    private function dispatchTo(array $userIds, string $title, string $body, array $data): void
    {
        if (empty($userIds)) {
            // Worth a warning rather than a silent success: the previous rule selected
            // nobody for months and read as "notified" in the log.
            Log::warning('ParcelUpdateNotificationService: no recipients matched', [
                'type'  => $data['type'] ?? null,
                'id'    => $data['app_id'] ?? null,
                'event' => $data['event'] ?? null,
            ]);

            return;
        }

        try {
            foreach ($userIds as $id) {
                $this->notifier->create($id, 'info', $title, $body, $data, ['module' => self::MODULE]);
            }

            Log::info('ParcelUpdateNotificationService: notified', [
                'count' => count($userIds),
                'event' => $data['event'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('ParcelUpdateNotificationService: failed to notify', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);
        }
    }

    /** Land side: the officers on the record, the Land role holders, the admins. */
    private function dispatchToLand(string $title, string $body, array $data): void
    {
        $this->dispatchTo(
            $this->recipientsFor($data['type'] ?? '', $data['app_id'] ?? null, 'land'),
            $title,
            $body,
            $data
        );
    }

    /** Deeds side: the same, with the Deeds roles. */
    private function dispatchToDeeds(string $title, string $body, array $data): void
    {
        $this->dispatchTo(
            $this->recipientsFor($data['type'] ?? '', $data['app_id'] ?? null, 'deeds'),
            $title,
            $body,
            $data
        );
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'subdivision'       => 'Plot Subdivision',
            'separation'        => 'Plot Separation',
            'merger'            => 'Plot Merger',
            'extension'         => 'Plot Extension',
            'change_of_purpose' => 'Change of Purpose',
            'duplex'            => 'APU - Advance Parcel Update (Duplex)',
            default             => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
