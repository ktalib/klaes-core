<?php

namespace App\Services;

use App\Models\ChangeOfPurposeApplication;
use App\Models\DuplexParcelUpdate;
use App\Models\PlotExtensionApplication;
use App\Models\PlotMergerApplication;
use App\Models\PlotSeparationApplication;
use App\Models\PlotSubdivisionApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Master Delete for the Parcel Update listings (Super Admin only; user, 2026-11-02).
 *
 * Unlike each listing's own delete, it also takes an APPROVED record. It still
 * refuses once anything has been commissioned off the record: by then file numbers
 * exist and files have been decommissioned against it, and deleting the application
 * would orphan them.
 *
 * It is the same soft delete the listings already use (is_deleted / deleted_by /
 * deleted_at); the row and its status stay, so it can be put back. Every approved-
 * application picker excludes is_deleted rows, so a deleted approval can no longer
 * be commissioned.
 */
class ParcelUpdateMasterDelete
{
    /** Listing type => model class. */
    public const TYPES = [
        'subdivision'       => PlotSubdivisionApplication::class,
        'merger'            => PlotMergerApplication::class,
        'separation'        => PlotSeparationApplication::class,
        'extension'         => PlotExtensionApplication::class,
        'change-of-purpose' => ChangeOfPurposeApplication::class,
        'duplex'            => DuplexParcelUpdate::class,
    ];

    public const LABELS = [
        'subdivision'       => 'Plot Subdivision',
        'merger'            => 'Plot Merger',
        'separation'        => 'Plot Separation',
        'extension'         => 'Plot Extension',
        'change-of-purpose' => 'Change of Purpose',
        'duplex'            => 'Advance Parcel Update (Duplex)',
    ];

    /**
     * Why this record can no longer be master-deleted, or null when it can.
     * Shared by the listings (to disable the menu item) and by delete() (to refuse).
     */
    public static function commissionedReason(string $type, Model $record): ?string
    {
        $status = strtolower(trim((string) ($record->status ?? '')));

        if ($type === 'duplex') {
            if ($status === DuplexParcelUpdate::STATUS_COMMITTED || !empty($record->committed_at)) {
                return 'This duplex has been commissioned.';
            }
            $decommissioned = DB::connection('sqlsrv')->table('duplex_parcel_update_files')
                ->where('duplex_parcel_update_id', $record->id)
                ->where('decommissioned', 1)
                ->exists();
            return $decommissioned ? 'Files have already been decommissioned under this duplex.' : null;
        }

        if ($status === 'commissioned') {
            return 'This application has been commissioned.';
        }

        // A subdivision is commissioned in runs and stays 'approved' until the last
        // plot is minted, so a part-commissioned one is caught by its count.
        if ($type === 'subdivision' && (int) ($record->commissioned_count ?? 0) > 0) {
            return 'Plots have already been commissioned under this subdivision ('
                . (int) $record->commissioned_count . ' of ' . (int) $record->num_plots . ').';
        }

        return null;
    }

    /**
     * @return array{status:string,message:string}
     *         status: deleted | already_deleted | commissioned | not_found | bad_type
     */
    public function delete(string $type, int $id, ?int $userId): array
    {
        $class = self::TYPES[$type] ?? null;
        if (!$class) {
            return ['status' => 'bad_type', 'message' => 'Unknown parcel update type.'];
        }

        /** @var Model|null $record */
        $record = $class::find($id);
        if (!$record) {
            return ['status' => 'not_found', 'message' => 'Record not found.'];
        }

        if ((int) ($record->is_deleted ?? 0) === 1) {
            return ['status' => 'already_deleted', 'message' => 'This record has already been deleted.'];
        }

        if ($reason = self::commissionedReason($type, $record)) {
            return ['status' => 'commissioned', 'message' => $reason . ' Master Delete is not allowed.'];
        }

        $ref = (string) ($record->duplex_id ?? $record->file_no ?? $record->id);
        $old = ['status' => $record->status, 'is_deleted' => $record->is_deleted ?? 0];

        $record->update([
            'is_deleted' => 1,
            'deleted_by' => $userId,
            'deleted_at' => now(),
        ]);

        try {
            app(AuditService::class)->logAction(
                'MASTER_DELETE',
                class_basename($class),
                $record->id,
                $old,
                ['is_deleted' => 1, 'deleted_by' => $userId],
                self::LABELS[$type] . ' master-deleted: ' . $ref . ' (was ' . $record->status . ')'
            );
        } catch (\Throwable $e) {
            // The delete has committed; a missing audit table must not report it as failed.
            Log::warning('Could not audit parcel update master delete', ['type' => $type, 'id' => $id, 'error' => $e->getMessage()]);
        }

        Log::info('Parcel update master-deleted', [
            'type' => $type,
            'id' => $record->id,
            'ref' => $ref,
            'status_at_delete' => $record->status,
            'user_id' => $userId,
        ]);

        return ['status' => 'deleted', 'message' => self::LABELS[$type] . ' ' . $ref . ' deleted.'];
    }
}
