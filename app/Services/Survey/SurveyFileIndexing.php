<?php

namespace App\Services\Survey;

use App\Http\Controllers\Survey\GknApplicationController;
use App\Models\FileIndexing;
use App\Models\Survey\SurveyCoFile;
use App\Support\AddressBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Survey files in File Indexing, and where they sit until first tracked (user, 2026-11-09).
 *
 * "CO files and other survey files commissioned or captured should go through file indexing": a CO file
 * commissioned or captured gets its file_indexings row (registry Survey) at once, kept in step when the
 * CO file is edited, crossed out when it is deleted. GKN / LPKN / LPPL / Misc KN / RDKN numbers already
 * reach file_indexings through the GKN generator and File Indexing.
 *
 * DIIT locations: an indexed Survey file that has never been tracked is at its unit's office, not in an
 * archive. office() names it; diitRows() feeds FileCommissioningTrackingService, which draws the line.
 *
 * Never sets indexed_by_user_id / indexed_at: those pay the File Indexing form's piece rate.
 */
class SurveyFileIndexing
{
    public const REGISTRY = 'Survey';
    public const SOURCE_COMMISSIONED = 'Survey CO File (Commissioned)';
    public const SOURCE_CAPTURED = 'Survey CO File (Captured)';

    /** Type => office the file sits at until first logged out (user, 2026-11-09). */
    public const OFFICES = [
        'co'   => ['code' => 'COMP-U', 'name' => 'Compensation Unit',  'department' => 'Survey'],
        'gkn'  => ['code' => 'GKN-R',  'name' => 'GKN Registry',       'department' => 'Survey'],
        'lpkn' => ['code' => 'GKN-R',  'name' => 'GKN Registry',       'department' => 'Survey'],
        'lppl' => ['code' => 'CAD-R',  'name' => 'Cadastral Registry', 'department' => 'Cadastral'],
        'misc' => ['code' => 'SUR-G',  'name' => "SG's Office",        'department' => 'Survey'],
        'rdkn' => ['code' => 'SUR-G',  'name' => "SG's Office",        'department' => 'Survey'],
    ];

    /** co | gkn | lpkn | lppl | misc | rdkn, or null for a file that is not a Survey file. */
    public static function typeOf(?string $fileNumber): ?string
    {
        $n = strtoupper(trim((string) $fileNumber));
        if ($n === '') return null;
        if (preg_match('/^CO-\d+-\d{4}\b/', $n)) return 'co';

        return GknApplicationController::typeOf($n);
    }

    /** @return array{code:string,name:string,department:string}|null */
    public static function office(?string $fileNumber): ?array
    {
        $type = self::typeOf($fileNumber);

        return $type ? self::OFFICES[$type] : null;
    }

    /** The file_indexings values a CO file holds. Location is district + LGA + state, never the plot. */
    public function coFileRow(SurveyCoFile $f): array
    {
        $district = AddressBuilder::resolve($f->prop_district, $f->prop_district_other);
        $lga = AddressBuilder::resolve($f->prop_lga);

        return [
            'file_title'  => trim((string) $f->title) ?: $f->co_file_no,
            'plot_number' => $f->prop_plot ?: null,
            'district'    => $district ?: null,
            'lga'         => $lga ?: null,
            'location'    => AddressBuilder::join([$district, $lga, $f->prop_state]) ?: null,
        ];
    }

    /**
     * Index a CO file, or bring its row up to date. $oldNumber: the number before an edit (captured files
     * may be corrected). Returns what happened: created | updated | removed_number (crossed out earlier).
     */
    public function indexCoFile(SurveyCoFile $f, ?string $oldNumber = null, ?string $byName = null): string
    {
        $result = $this->indexCoFileRow($f, $oldNumber, $byName);
        // Its co_grouping row (Print File Labels), made or renumbered, with the tracking ID (user, 2026-11-09).
        app(CoGrouping::class)->ensure($f, $byName ?: self::userName());

        return $result;
    }

    private function indexCoFileRow(SurveyCoFile $f, ?string $oldNumber = null, ?string $byName = null): string
    {
        $db = DB::connection('sqlsrv');
        $values = $this->coFileRow($f);

        // file_number is unique across crossed-out rows too, so look at every row.
        $row = $db->table('file_indexings')->where('file_number', $f->co_file_no)->orderBy('id')->first(['id', 'is_deleted']);
        if (! $row && $oldNumber && $oldNumber !== $f->co_file_no) {
            $row = $db->table('file_indexings')->where('file_number', $oldNumber)->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderBy('id')->first(['id', 'is_deleted']);
            if ($row) $values['file_number'] = $f->co_file_no;
        }

        if ($row && (int) $row->is_deleted === 1) {
            Log::warning('Survey CO file not indexed: its number was crossed out in File Indexing', ['co_file_no' => $f->co_file_no]);

            return 'removed_number';
        }
        if ($row) {
            // The CO file is the authority for these; a blank on it does not blank the index.
            $db->table('file_indexings')->where('id', $row->id)->update(array_filter($values, fn ($v) => $v !== null) + [
                'registry' => self::REGISTRY, 'updated_at' => now(),
            ]);

            return 'updated';
        }

        // forceCreate: 'source' is not fillable. The model's creating hooks fill gender and prop_id.
        FileIndexing::on('sqlsrv')->forceCreate($values + [
            'file_number'     => $f->co_file_no,
            'registry'        => self::REGISTRY,
            'workflow_status' => 'indexed',
            'source'          => $f->source === SurveyCoFile::SOURCE_CAPTURED ? self::SOURCE_CAPTURED : self::SOURCE_COMMISSIONED,
            'created_by'      => $byName ?: self::userName(),
            // The file entered KLAES when the CO file was made, so its DIIT clock starts there.
            'created_at'      => $f->created_at ?: now(),
            'updated_at'      => now(),
        ]);

        return 'created';
    }

    /** A deleted CO file: its index row is crossed out (flagged, never deleted). */
    public function removeCoFile(SurveyCoFile $f): void
    {
        DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $f->co_file_no)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->update(['is_deleted' => 1, 'deleted_at' => now(), 'updated_at' => now()]);
        app(CoGrouping::class)->remove($f, self::userName());
    }

    /**
     * The DIIT line of each Survey file among $fileNumbers: its live index row (title, who indexed it, when).
     * Keyed by upper-cased number. Files that are not Survey files are left out.
     *
     * @return array<string, object>
     */
    public function diitRows(array $fileNumbers): array
    {
        $wanted = array_values(array_unique(array_filter(array_map(fn ($n) => trim((string) $n), $fileNumbers), fn ($n) => self::typeOf($n) !== null)));
        $out = [];
        foreach (array_chunk($wanted, 1000) as $chunk) {
            $rows = DB::connection('sqlsrv')->table('file_indexings')->whereIn('file_number', $chunk)
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderBy('id')->get(['file_number', 'file_title', 'created_by', 'created_at', 'tracking_id', 'source']);
            foreach ($rows as $r) {
                $out[strtoupper(trim((string) $r->file_number))] ??= $r;
            }
        }

        return $out;
    }

    /** The info array FileCommissioningTrackingService uses, for one diitRows() row. */
    public static function diitInfo(object $row): array
    {
        $office = self::office($row->file_number);
        $commissioned = in_array($row->source, [self::SOURCE_COMMISSIONED, GknApplicationController::COMMISSIONED_SOURCE], true);

        return [
            'file_number'       => trim((string) $row->file_number),
            'file_title'        => trim((string) $row->file_title) ?: null,
            'commissioned_at'   => rescue(fn () => Carbon::parse($row->created_at), Carbon::now(), false),
            'commissioned_by'   => trim((string) $row->created_by) ?: null,
            'tracking_id'       => trim((string) $row->tracking_id) ?: null,
            'source'            => trim((string) $row->source) ?: null,
            'office_code'       => $office['code'],
            'office_name'       => $office['name'],
            'office_department' => $office['department'],
            'label'             => $commissioned ? 'File Commissioning' : 'File Indexing',
            'notes'             => ($commissioned ? 'Survey file commissioned in KLAES' : 'Survey file indexed in KLAES') . " — at the {$office['name']}.",
            'survey'            => true,
        ];
    }

    private static function userName(): string
    {
        $u = auth()->user();

        return $u ? (trim($u->name ?: ($u->first_name . ' ' . $u->last_name)) ?: ($u->email ?: (string) $u->id)) : 'System';
    }
}
