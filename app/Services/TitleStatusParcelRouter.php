<?php

namespace App\Services;

use App\Models\TitleStatusApplication;
use Illuminate\Support\Facades\DB;

/**
 * Parcel-update title types (Subdivision, Merger, Change of Purpose, Extension,
 * Separation) were being written into `title_status_applications` — most of them
 * raised as classification ticks from the File Indexing "Create Indexing" dialog,
 * which POSTs to the shared title-status backend. They actually belong in the
 * dedicated Parcel Update tables.
 *
 * This router is the single point that decides whether a title type is really a
 * parcel-update action and, if so, writes it to the correct table. Rows created
 * this way are marked with the {@see self::HIDDEN_STATUS} status so the Parcel
 * Update frontends keep them out of the list — only applications actually
 * processed through the Parcel Update workflow (status pending/approved/…) show.
 *
 * Used by both:
 *   - the live fix in TitleStatusController::store (stop the bleeding), and
 *   - the {@see \App\Console\Commands\MigrateTitleStatusParcelUpdates} backfill.
 */
class TitleStatusParcelRouter
{
    /** Status stamped on routed rows so the Parcel Update pages hide them for now. */
    public const HIDDEN_STATUS = 'hidden';

    /** title_type (as stored in title_status_applications) → destination table. */
    private const TYPE_TABLE = [
        TitleStatusApplication::TYPE_SUBDIVISION => 'plot_subdivision_applications',
        TitleStatusApplication::TYPE_MERGER      => 'plot_merger_applications',
        TitleStatusApplication::TYPE_PURPOSE     => 'change_of_purpose_applications',
        TitleStatusApplication::TYPE_EXTENSION   => 'plot_extension_applications',
        TitleStatusApplication::TYPE_SEPARATION  => 'plot_separation_applications',
        // Extension sub-kinds that File Indexing records as distinct types.
        'Plot Extension'                         => 'plot_extension_applications',
        'File Extension'                         => 'plot_extension_applications',
    ];

    /** Cache of real column names per destination table (avoids inserting unknown columns). */
    private array $columnCache = [];

    /** Is this title type really a parcel-update action that belongs in its own table? */
    public function isParcelType(?string $titleType): bool
    {
        return $titleType !== null && array_key_exists($titleType, self::TYPE_TABLE);
    }

    public function tableFor(?string $titleType): ?string
    {
        return $titleType === null ? null : (self::TYPE_TABLE[$titleType] ?? null);
    }

    /**
     * Insert a hidden Parcel Update row for a parcel-update title type.
     *
     * @param array $data Loose field bag (file_no, file_title, applicant_name, plot_no,
     *                    house_no, street_name, district, lga, state, location, land_use,
     *                    remark/remarks, captured_by). Only keys that exist as columns on
     *                    the destination table are written.
     * @param int|null $sourceTitleStatusId When backfilling, the originating
     *                    title_status_applications id — recorded in remarks as an idempotency
     *                    marker so a re-run does not create a duplicate.
     * @return int|null The new row id, or null if $titleType is not a parcel type.
     */
    public function route(string $titleType, array $data, ?int $sourceTitleStatusId = null): ?int
    {
        $table = $this->tableFor($titleType);
        if ($table === null) {
            return null;
        }

        // Idempotency: never migrate the same title_status row twice.
        if ($sourceTitleStatusId !== null) {
            $existing = DB::connection('sqlsrv')->table($table)
                ->where('remarks', 'LIKE', '%' . $this->marker($sourceTitleStatusId) . '%')
                ->value('id');
            if ($existing) {
                return (int) $existing;
            }
        }

        $now = now();

        $fileNo    = $this->str($data['file_no'] ?? null);
        $fileTitle = $this->firstNonEmpty($data['file_title'] ?? null, $data['applicant_name'] ?? null, $fileNo, 'N/A');
        $applicant = $this->firstNonEmpty($data['applicant_name'] ?? null, $data['file_title'] ?? null, $fileNo, 'N/A');

        $remark = trim((string) ($data['remarks'] ?? $data['remark'] ?? ''));
        if ($sourceTitleStatusId !== null) {
            $remark = trim($remark . ' ' . $this->marker($sourceTitleStatusId));
        }

        // Candidate payload; filtered down to columns that actually exist on the table.
        $candidate = [
            'file_no'        => $fileNo,
            'file_title'     => $fileTitle,
            'applicant_name' => $applicant,
            'plot_no'        => $this->str($data['plot_no'] ?? null),
            'plan_no'        => $this->str($data['plan_no'] ?? null),
            'house_no'       => $this->str($data['house_no'] ?? null),
            'street_name'    => $this->str($data['street_name'] ?? null),
            'district'       => $this->str($data['district'] ?? null),
            'lga'            => $this->str($data['lga'] ?? null),
            'state'          => $this->str($data['state'] ?? null),
            'location'       => $this->str($data['location'] ?? null),
            'land_use'       => $this->str($data['land_use'] ?? null),
            'remarks'        => $remark !== '' ? $remark : null,
            'status'         => self::HIDDEN_STATUS,
            'captured_by'    => $data['captured_by'] ?? null,
            'created_at'     => $data['created_at'] ?? $now,
            'updated_at'     => $data['updated_at'] ?? $now,
        ];

        $payload = array_intersect_key($candidate, array_flip($this->columns($table)));

        return (int) DB::connection('sqlsrv')->table($table)->insertGetId($payload);
    }

    /**
     * The idempotency marker embedded in remarks for backfilled rows.
     *
     * Deliberately delimited with {…} and NOT square brackets: this string is fed to a
     * SQL Server LIKE, where `[...]` is a character-class wildcard — a bracketed marker
     * matches almost any row and breaks the duplicate check. Curly braces and the trailing
     * `}` after the id are LIKE-literal and ASCII-safe, so the match is exact per id.
     */
    public function marker(int $sourceTitleStatusId): string
    {
        return "{from_title_status:{$sourceTitleStatusId}}";
    }

    private function columns(string $table): array
    {
        return $this->columnCache[$table] ??= DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing($table);
    }

    private function str($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function firstNonEmpty(...$values): ?string
    {
        foreach ($values as $value) {
            $v = $this->str($value);
            if ($v !== null) {
                return $v;
            }
        }
        return null;
    }
}
