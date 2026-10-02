<?php

namespace App\Services;

use App\Models\TitleStatusApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TitleStatusService
{
    private const URL_LABELS = [
        'land'      => 'Land',
        'deeds'     => 'Deeds',
        'cadastral' => 'Cadastral',
        'dciv'      => 'DCIV',
        'pp'        => 'Physical Planning',
    ];

    private const TYPE_SLUG = [
        TitleStatusApplication::TYPE_WITHDRAWAL   => 'withdrawn',
        TitleStatusApplication::TYPE_CANCELLATION => 'cancelled',
        TitleStatusApplication::TYPE_REVOKE        => 'revoked',
        TitleStatusApplication::TYPE_LITIGATION    => 'litigation',
        TitleStatusApplication::TYPE_AMENDMENT     => 'amendment',
        TitleStatusApplication::TYPE_SURRENDER     => 'surrendered',
        TitleStatusApplication::TYPE_REGRANT       => 'regranted',
        TitleStatusApplication::TYPE_RESETTLEMENT  => 'resettled',
        TitleStatusApplication::TYPE_SUBDIVISION   => 'subdivision',
        TitleStatusApplication::TYPE_MERGER        => 'merger',
        TitleStatusApplication::TYPE_PURPOSE       => 'change_of_purpose',
        TitleStatusApplication::TYPE_EXTENSION     => 'extension',
        TitleStatusApplication::TYPE_CHANGE_NAME   => 'change_of_name',
        TitleStatusApplication::TYPE_SEPARATION    => 'separation',
    ];

    private const TYPE_VERB = [
        TitleStatusApplication::TYPE_WITHDRAWAL   => 'Withdrawal',
        TitleStatusApplication::TYPE_CANCELLATION => 'Cancellation',
        TitleStatusApplication::TYPE_REVOKE        => 'Revocation',
        TitleStatusApplication::TYPE_LITIGATION    => 'Litigation',
        TitleStatusApplication::TYPE_AMENDMENT     => 'Amendment/Reconsideration',
        TitleStatusApplication::TYPE_SURRENDER     => 'Surrender',
        TitleStatusApplication::TYPE_REGRANT       => 'Re-grant',
        TitleStatusApplication::TYPE_RESETTLEMENT  => 'Resettlement',
        TitleStatusApplication::TYPE_SUBDIVISION   => 'Subdivision',
        TitleStatusApplication::TYPE_MERGER        => 'Merger',
        TitleStatusApplication::TYPE_PURPOSE       => 'Change of Purpose',
        TitleStatusApplication::TYPE_EXTENSION     => 'Extension',
        TitleStatusApplication::TYPE_CHANGE_NAME   => 'Change of Name',
        TitleStatusApplication::TYPE_SEPARATION    => 'Separation',
    ];

    private const SOURCE_TABLES = [
        'file_indexings',
        'fileNumber',
        'pra',
        'CofO_staging',
        'mls_file_no',
        'customers_staging',
        'entities_staging',
        'instrument_capture',
        'deed_registrations',
        'file_history_staging',
    ];

    public function urlLabel(string $url): string
    {
        return self::URL_LABELS[$url] ?? strtoupper($url);
    }

    public function typeSlug(string $titleType): string
    {
        return self::TYPE_SLUG[$titleType] ?? 'flagged';
    }

 

    /**
     * Template: "[Status type] was initiated by [initiator] for File [file_no] on [Time/Date] due to [Reason]"
     * Applicant/Allottee get the holder name with role suffix; Ministry/Court Order render literally.
     */
    public function generateRemark(string $titleType, string $initiatedBy, string $reason, string $applicantName = '', string $fileNo = '', string $seeFileno = ''): string
    {
        if ($titleType === TitleStatusApplication::TYPE_REGRANT) {
            return $seeFileno !== ''
                ? "This File has been Re-granted from {$seeFileno}"
                : 'This File has been Re-granted';
        }

        $statusType = self::TYPE_VERB[$titleType] ?? $titleType;
        $datetime   = now()->format('d/m/Y H:i');
        $reasonText = $reason !== '' ? $reason : '[Reason]';

        if ($initiatedBy === 'Allottee' || $initiatedBy === 'Applicant') {
            $initiator = $applicantName !== ''
                ? "{$applicantName} ({$initiatedBy})"
                : $initiatedBy;
        } elseif ($initiatedBy !== '') {
            $initiator = $initiatedBy;
        } else {
            $initiator = '[Ministry/Allottee]';
        }

        $fileRef = $fileNo !== '' ? " FileNo {$fileNo}" : '';

        return "{$statusType}{$fileRef} was initiated by {$initiator} on {$datetime} due to {$reasonText}";
    }

    /**
     * Flag the file across all source tables and copy to archive tables.
     *
     * @param bool $falseDecommissioning When true the file is NOT actually decommissioned
     *        (e.g. a Title Status update raised from File Indexing). The title-status flags
     *        are still recorded and the row is archived to decommissioned_files with
     *        false_decommissioning = 1, but the real "is_decommissioned" side effects
     *        (flagging the source file + deprecated_records) are skipped.
     */
    /**
     * @param TitleStatusApplication|TitleStatusApplication[] $records One record, or several
     *        when multiple title statuses were selected at once for the same file. The flag
     *        columns (title_status_type / title_status_remark) are then written as a combined
     *        value reflecting every selected status.
     */
    public function flagAndDecommission($records, bool $falseDecommissioning = false): void
    {
        $records = is_array($records) ? array_values($records) : [$records];
        $records = array_filter($records);
        if (empty($records)) {
            return;
        }

        $record   = $records[0];
        $fileNo   = $record->file_no;

        // Combine slugs and remarks across every selected status for the flag columns.
        $slugs    = [];
        $remarks  = [];
        foreach ($records as $r) {
            $slugs[]   = $this->typeSlug($r->title_type);
            $rRemark   = trim((string) ($r->remark ?? ''));
            if ($rRemark !== '') {
                $remarks[] = $rRemark;
            }
        }
        $slug     = implode(', ', array_values(array_unique($slugs)));
        $remark   = implode("\n", $remarks);

        $user     = Auth::user();
        $userName = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? $user->name ?? '')) : 'System';

        $this->applyFlags($fileNo, $slug, $remark, !$falseDecommissioning);

        // Archive to decommissioned_files
        try {
            $fileRecord    = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $fileNo)->first();
            $indexRecord   = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $fileNo)->first();

            // NOTE: do not use insertOrIgnore() here — SQL Server does not support it
            // (Laravel throws "This database engine does not support inserting while
            // ignoring errors."). Guard against duplicates manually instead.
            $alreadyArchived = DB::connection('sqlsrv')->table('decommissioned_files')
                ->where('file_no', $fileNo)
                ->where('false_decommissioning', $falseDecommissioning ? 1 : 0)
                ->exists();

            if (!$alreadyArchived) {
                $titleStatusRow = [
                    'file_number_id'       => (int) ($fileRecord->id ?? ($indexRecord->id ?? 0)),
                    'file_no'              => $fileNo,
                    'mls_file_no'          => $fileNo,
                    'kangis_file_no'       => $fileRecord->kangisFileNo ?? ($indexRecord->kangis_file_no ?? null),
                    'new_kangis_file_no'   => $fileRecord->NewKANGISFileNo ?? ($indexRecord->new_kangis_file_no ?? null),
                    'file_name'            => $fileRecord->FileName ?? ($indexRecord->file_title ?? $record->file_title ?? 'N/A'),
                    'commissioning_date'   => $fileRecord->commissioning_date ?? null,
                    'decommissioning_date' => now(),
                    'decommissioning_reason' => "Title Status: {$slug} — " . ($remark ?? ''),
                    'decommissioned_by'    => $userName,
                    'false_decommissioning' => $falseDecommissioning ? 1 : 0,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ];
                // A genuine title-status decommission (fd=0) shows the real Date Decommissioned and
                // is forced to the LAST line of the timeline; a fd=1 row is only a flag (the file is
                // NOT actually decommissioned) and stays suppressed. event_type added 2026_07_21.
                if (Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'event_type')) {
                    $titleStatusRow['event_type'] = $falseDecommissioning ? 'title_status_flag' : 'title_status_update';
                }
                DB::connection('sqlsrv')->table('decommissioned_files')->insert($titleStatusRow);
            }
        } catch (\Exception $e) {
            Log::warning("TitleStatus: decommissioned_files insert failed for {$fileNo}: " . $e->getMessage());
        }

        // Archive to deprecated_records — only for a REAL decommissioning. A false
        // decommissioning (Title Status from File Indexing) does not deprecate the record.
        try {
            if (!$falseDecommissioning && ($indexRecord ?? false)) {
                DB::connection('sqlsrv')->table('deprecated_records')->insert([
                    'file_indexing_id'   => (int) ($indexRecord->id ?? 0),
                    'file_number'        => $indexRecord->file_number ?? $fileNo,
                    'file_title'         => $indexRecord->file_title ?? $record->file_title,
                    'land_use_type'      => $indexRecord->land_use_type ?? $record->land_use,
                    'plot_number'        => $indexRecord->plot_number ?? $record->plot_no,
                    'district'           => $indexRecord->district ?? $record->district,
                    'lga'                => $indexRecord->lga ?? $record->lga,
                    'location'           => $indexRecord->location ?? $record->location,
                    'plot_size'          => $indexRecord->plot_size ?? null,
                    'tp_no'              => $indexRecord->tp_no ?? null,
                    'lpkn_no'            => $indexRecord->lpkn_no ?? null,
                    'tracking_id'        => $indexRecord->tracking_id ?? null,
                    'original_holder'    => $indexRecord->original_holder ?? $record->applicant_name,
                    'current_holder'     => $indexRecord->current_holder ?? $record->applicant_name,
                    'parent_prop_id'     => $indexRecord->parent_prop_id ?? null,
                    'related_fileno'     => $indexRecord->related_fileno ?? null,
                    'has_transaction'    => $indexRecord->has_transaction ?? 0,
                    'workflow_type'      => "title_status_{$slug}",
                    'decommissioned_by'  => $userName,
                    'decommissioned_at'  => now(),
                    'created_by'         => $indexRecord->created_by ?? null,
                    'updated_by'         => $userName,
                    'serial_no'          => $indexRecord->serial_no ?? null,
                    'batch_no'           => $indexRecord->batch_no ?? null,
                    'workflow_status'    => $indexRecord->workflow_status ?? null,
                    'registry'           => $indexRecord->registry ?? null,
                    'prop_id'            => $indexRecord->prop_id ?? null,
                    'phone'              => $indexRecord->phone ?? null,
                    'residence_address'  => $indexRecord->residence_address ?? null,
                    'general_registry'   => $indexRecord->general_registry ?? null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("TitleStatus: deprecated_records insert failed for {$fileNo}: " . $e->getMessage());
        }
    }

    /**
     * Write the title-status flag columns for one file across every source table
     * that holds a row for it.
     *
     * @param string $typeValue Value for title_status_type. flagAndDecommission() passes the
     *        slug ('regranted'); the Re-grant commissioning path passes the label ('Re-grant').
     * @param bool $decommission Also set is_decommissioned on tables that have the column.
     */
    public function applyFlags(string $fileNo, string $typeValue, string $remark, bool $decommission = false): void
    {
        if (trim($fileNo) === '') {
            return;
        }

        foreach (self::SOURCE_TABLES as $table) {
            try {
                if (!DB::connection('sqlsrv')->getSchemaBuilder()->hasTable($table)) {
                    continue;
                }

                $fileCol = $this->fileNumberColumn($table);

                $exists = DB::connection('sqlsrv')->table($table)->where($fileCol, $fileNo)->exists();
                if (!$exists) continue;

                $updates = ['title_status' => 1, 'title_status_type' => $typeValue, 'title_status_remark' => $remark];

                if ($decommission && DB::connection('sqlsrv')->getSchemaBuilder()->hasColumn($table, 'is_decommissioned')) {
                    $updates['is_decommissioned'] = 1;
                }

                DB::connection('sqlsrv')->table($table)->where($fileCol, $fileNo)->update($updates);
            } catch (\Exception $e) {
                Log::warning("TitleStatus: could not update {$table} for {$fileNo}: " . $e->getMessage());
            }
        }
    }

    /**
     * Record a Re-grant raised from MLS file-number commissioning: the new file is
     * commissioned as a re-grant of $oldFileNo (the selected Related File).
     *
     * Writes, all non-fatal:
     *   - flag columns on the NEW file  → "This File has been Re-granted from {old}"
     *   - flag columns on the OLD file  → "This File has been Re-granted to {new}"
     *     (flags only — a re-grant here does not decommission or archive the old file)
     *   - one title_status_applications row against the new file, see_fileno = old file
     *   - one related_file_number row linking new → old
     *
     * $oldFileNo is optional: a Re-grant may be commissioned without ticking "This file has
     * a Related File Number". The new file is then still flagged, with the shorter remark,
     * and the old-file flags / linkage row are skipped since there is no file to point at.
     *
     * @param array $context file_indexing_id, prop_id, file_title, applicant_name, plot_no,
     *                       district, lga, location, land_use, url — all optional.
     */
    public function recordRegrant(string $newFileNo, string $oldFileNo = '', array $context = []): void
    {
        $newFileNo = trim($newFileNo);
        $oldFileNo = trim($oldFileNo);
        if ($newFileNo === '') {
            return;
        }

        $regrantedFrom = $oldFileNo !== ''
            ? "This File has been Re-granted from {$oldFileNo}"
            : 'This File has been Re-granted';
        $regrantedTo   = "This File has been Re-granted to {$newFileNo}";

        $this->applyFlags($newFileNo, TitleStatusApplication::TYPE_REGRANT, $regrantedFrom);

        if ($oldFileNo !== '') {
            $this->applyFlags($oldFileNo, TitleStatusApplication::TYPE_REGRANT, $regrantedTo);
        }

        try {
            TitleStatusApplication::create([
                'url'            => $context['url'] ?? 'land',
                'title_type'     => TitleStatusApplication::TYPE_REGRANT,
                'source_table'   => 'file_indexings',
                'source_id'      => $context['file_indexing_id'] ?? null,
                'file_no'        => $newFileNo,
                'see_fileno'     => $oldFileNo !== '' ? $oldFileNo : null,
                'file_title'     => $context['file_title'] ?? null,
                'applicant_name' => $context['applicant_name'] ?? null,
                'plot_no'        => $context['plot_no'] ?? null,
                'district'       => $context['district'] ?? null,
                'lga'            => $context['lga'] ?? null,
                'location'       => $context['location'] ?? null,
                'land_use'       => $context['land_use'] ?? null,
                'initiated_by'   => 'Ministry',
                // The row is keyed on the NEW file, so it takes the "from" wording — the
                // "to" wording belongs on the OLD file's flags above. Using $regrantedTo
                // here produced "This File has been Re-granted to <itself>", and it also
                // disagreed with the ~70 rows the File Indexing dialog had already written.
                'reason'         => $regrantedFrom,
                'remark'         => $regrantedFrom,
                'status'         => TitleStatusApplication::STATUS_PENDING,
                'captured_by'    => Auth::id(),
            ]);
        } catch (\Exception $e) {
            Log::warning("TitleStatus: Re-grant application insert failed for {$newFileNo}: " . $e->getMessage());
        }

        $this->recordFileLink($newFileNo, $oldFileNo, $regrantedTo, $context, TitleStatusApplication::TYPE_REGRANT);
    }

    /**
     * Flag a Resettlement and record its title-status application + linkage. This mirrors
     * recordRegrant() exactly — the new file numbers like a normal file and keeps its own
     * "Resettlement" tag — but stamps the Resettlement title type and remarks instead.
     *
     * $oldFileNo is optional: a Resettlement may be commissioned without ticking "This file
     * has a Related File Number". The new file is then still flagged, with the shorter remark,
     * and the old-file flags / linkage row are skipped since there is no file to point at.
     *
     * @param array $context file_indexing_id, prop_id, file_title, applicant_name, plot_no,
     *                       district, lga, location, land_use, url — all optional.
     */
    public function recordResettlement(string $newFileNo, string $oldFileNo = '', array $context = []): void
    {
        $newFileNo = trim($newFileNo);
        $oldFileNo = trim($oldFileNo);
        if ($newFileNo === '') {
            return;
        }

        $resettledFrom = $oldFileNo !== ''
            ? "This File has been Resettled from {$oldFileNo}"
            : 'This File has been Resettled';
        $resettledTo   = "This File has been Resettled to {$newFileNo}";

        $this->applyFlags($newFileNo, TitleStatusApplication::TYPE_RESETTLEMENT, $resettledFrom);

        if ($oldFileNo !== '') {
            $this->applyFlags($oldFileNo, TitleStatusApplication::TYPE_RESETTLEMENT, $resettledTo);
        }

        try {
            TitleStatusApplication::create([
                'url'            => $context['url'] ?? 'land',
                'title_type'     => TitleStatusApplication::TYPE_RESETTLEMENT,
                'source_table'   => 'file_indexings',
                'source_id'      => $context['file_indexing_id'] ?? null,
                'file_no'        => $newFileNo,
                'see_fileno'     => $oldFileNo !== '' ? $oldFileNo : null,
                'file_title'     => $context['file_title'] ?? null,
                'applicant_name' => $context['applicant_name'] ?? null,
                'plot_no'        => $context['plot_no'] ?? null,
                'district'       => $context['district'] ?? null,
                'lga'            => $context['lga'] ?? null,
                'location'       => $context['location'] ?? null,
                'land_use'       => $context['land_use'] ?? null,
                'initiated_by'   => 'Ministry',
                'reason'         => $resettledTo,
                'remark'         => $resettledTo,
                'status'         => TitleStatusApplication::STATUS_PENDING,
                'captured_by'    => Auth::id(),
            ]);
        } catch (\Exception $e) {
            Log::warning("TitleStatus: Resettlement application insert failed for {$newFileNo}: " . $e->getMessage());
        }

        $this->recordFileLink($newFileNo, $oldFileNo, $resettledTo, $context, TitleStatusApplication::TYPE_RESETTLEMENT);
    }

    /**
     * Upsert the related_file_number row for a Re-grant or Resettlement. Keyed on
     * (source_table, source_id), which carries a unique constraint, so an existing row for the
     * indexing record is updated. $transactionType tags the link with the workflow that made it.
     */
    private function recordFileLink(string $newFileNo, string $oldFileNo, string $comment, array $context, string $transactionType): void
    {
        $indexingId = $context['file_indexing_id'] ?? null;
        if (empty($indexingId) || $oldFileNo === '') {
            return;
        }

        try {
            $schema = DB::connection('sqlsrv')->getSchemaBuilder();
            if (!$schema->hasTable('related_file_number')) {
                return;
            }

            $propId = $context['prop_id'] ?? null;
            $row = [
                'related_fileno' => mb_substr($oldFileNo, 0, 500),
                'prop_id'        => ($propId !== null && $propId !== '') ? (string) $propId : null,
                'file_number'    => $newFileNo,
                'updated_at'     => now(),
            ];

            $optional = [
                'transaction_type' => $transactionType,
                'comment'          => $comment,
                'file_title'       => $context['file_title'] ?? null,
            ];
            foreach ($optional as $col => $value) {
                if ($schema->hasColumn('related_file_number', $col)) {
                    $row[$col] = $value;
                }
            }

            $match    = ['source_table' => 'file_indexings', 'source_id' => (int) $indexingId];
            $existing = DB::connection('sqlsrv')->table('related_file_number')->where($match)->first();

            if ($existing) {
                DB::connection('sqlsrv')->table('related_file_number')->where('id', $existing->id)->update($row);
            } else {
                DB::connection('sqlsrv')->table('related_file_number')->insert($row + $match + ['created_at' => now()]);
            }
        } catch (\Exception $e) {
            Log::warning("TitleStatus: Re-grant related_file_number upsert failed for {$newFileNo}: " . $e->getMessage());
        }
    }

    /**
     * The column holding the MLS file number on each source table. These names are not
     * consistent across the schema, and a wrong name makes the UPDATE throw "Invalid
     * column name", which applyFlags() swallows as a warning — the flags then silently
     * never land. Verify against the real schema before changing any of these.
     */
    private function fileNumberColumn(string $table): string
    {
        return match ($table) {
            'fileNumber'           => 'mlsfNo',
            'mls_file_no'          => 'full_file_number',
            'file_indexings'       => 'file_number',
            'instrument_capture'   => 'mlsFNo',
            'deed_registrations'   => 'fileno',
            'CofO_staging'         => 'mlsFNo',
            'pra'                  => 'mlsFNo',
            'file_history_staging' => 'mlsFNo',
            default                => 'file_number',
        };
    }
}
