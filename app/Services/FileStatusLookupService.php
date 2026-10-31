<?php

namespace App\Services;

use App\Models\LandRecommendation;
use App\Models\TitleStatusApplication;
use App\Support\DecommissionScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Survey > File Status search (CR 30102026 item 29, user 2026-10-31): one file looked
 * up in every File Status list (RofO, CofO, Decommissioned, Duplicates, Regrant Files,
 * Title Status, DCIV and the Encumbrance lists). Each check reads the table that list's
 * own page reads, with the same base filter, so the answer agrees with the tab.
 * Read-only.
 *
 * A file is matched by all of its numbers: one file can have several fileNumber rows
 * and the same number in different columns, so the number searched is widened to
 * every number recorded beside it before any list is checked.
 */
class FileStatusLookupService
{
    public const BY = ['file' => 'File No.', 'op' => 'OP Serial No.', 'name' => 'Name'];

    /** Candidate files for a name, at most this many. */
    private const NAME_LIMIT = 50;

    /** Rows shown per list for one file. */
    private const ROW_LIMIT = 20;

    private const MORTGAGE_TYPES = ['Deed of Mortgage', 'Tripartite Mortgage'];

    private const SURRENDER_TYPES = [
        'Deed of Surrender and Release', 'Deed of Surrender & Release', 'Deed of Surrender, and Release',
        'Surrender and Release', 'Surrender & Release',
    ];

    private function db()
    {
        return DB::connection('sqlsrv');
    }

    public static function normalise(?string $no): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $no)));
    }

    /**
     * The files a search points at: [['file_number', 'title', 'via'], ...].
     * A file number resolves to itself; an OP serial to the files its OPs (pra) are on;
     * a name to the files whose holder / applicant / title contains it.
     */
    public function candidates(string $q, string $by): array
    {
        $q = trim($q);
        if ($q === '') return [];

        return match ($by) {
            'op'   => $this->byOpSerial($q),
            'name' => $this->byName($q),
            default => [['file_number' => self::normalise($q), 'title' => $this->titleOf(self::normalise($q)), 'via' => null]],
        };
    }

    private function byOpSerial(string $serial): array
    {
        $out = [];
        $this->db()->table('pra')
            ->where('op_serial_number', $serial)
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->limit(self::NAME_LIMIT)
            ->get(['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'Grantee', 'op_type'])
            ->each(function ($r) use (&$out, $serial) {
                $no = $this->firstNumber([$r->mlsFNo, $r->kangisFileNo, $r->NewKANGISFileno, $r->fileno]);
                if ($no !== '') $out[$no] ??= ['file_number' => $no, 'title' => $r->Grantee, 'via' => 'OP ' . $serial . ($r->op_type ? ' (' . $r->op_type . ')' : '')];
            });

        // Survey's own OPs (survey_op_records) are not here: they carry plot numbers, not a
        // file number, and reach pra once sent to Deeds.
        return array_values($out);
    }

    private function byName(string $name): array
    {
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $name) . '%';
        $out = [];
        $add = function ($no, $title, $via) use (&$out) {
            $no = self::normalise($no);
            if ($no !== '' && count($out) < self::NAME_LIMIT) $out[$no] ??= ['file_number' => $no, 'title' => $title, 'via' => $via];
        };

        $this->db()->table('file_indexings')->where('file_title', 'like', $like)
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->whereNotNull('file_number')
            ->orderByDesc('id')->limit(self::NAME_LIMIT)
            ->get(['file_number', 'file_title'])
            ->each(fn ($r) => $add($r->file_number, $r->file_title, 'File title'));

        $this->db()->table('land_recommendations')->where('applicant_name', 'like', $like)
            ->orderByDesc('id')->limit(self::NAME_LIMIT)
            ->get(['file_number', 'applicant_name'])
            ->each(fn ($r) => $add($r->file_number, $r->applicant_name, 'RofO applicant'));

        $this->db()->table('pra')->where('Grantee', 'like', $like)
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')->limit(self::NAME_LIMIT)
            ->get(['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'Grantee'])
            ->each(fn ($r) => $add($this->firstNumber([$r->mlsFNo, $r->kangisFileNo, $r->NewKANGISFileno, $r->fileno]), $r->Grantee, 'OP grantee'));

        return array_values($out);
    }

    private function firstNumber(array $nos): string
    {
        foreach ($nos as $n) {
            $n = self::normalise($n);
            if ($n !== '' && $n !== 'N/A') return $n;
        }

        return '';
    }

    private function titleOf(string $no): ?string
    {
        $t = $this->db()->table('file_indexings')->where('file_number', $no)
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')->value('file_title');

        return $t ?: $this->db()->table('fileNumber')
            ->where(fn ($w) => $w->where('mlsfNo', $no)->orWhere('kangisFileNo', $no)->orWhere('NewKANGISFileNo', $no))
            ->whereNotNull('FileName')->orderByDesc('id')->value('FileName');
    }

    /** The number searched plus every number recorded beside it on a fileNumber row. */
    public function aliases(string $no): array
    {
        $no = self::normalise($no);
        $all = [$no];
        $this->db()->table('fileNumber')
            ->where(fn ($w) => $w->where('mlsfNo', $no)->orWhere('kangisFileNo', $no)->orWhere('NewKANGISFileNo', $no))
            ->limit(20)
            ->get(['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo'])
            ->each(function ($r) use (&$all) {
                foreach ([$r->mlsfNo, $r->kangisFileNo, $r->NewKANGISFileNo] as $n) {
                    $n = self::normalise($n);
                    if ($n !== '' && $n !== 'N/A' && ! in_array($n, $all, true)) $all[] = $n;
                }
            });

        return $all;
    }

    /** Where any of these columns holds any of these numbers. */
    private function anyOf($q, array $columns, array $nos)
    {
        return $q->where(function ($w) use ($columns, $nos) {
            foreach ($columns as $c) $w->orWhereIn($c, $nos);
        });
    }

    private function live($q, string $col = 'is_deleted')
    {
        return $q->where(fn ($w) => $w->whereNull($col)->orWhere($col, 0));
    }

    /** A JSON list of numbers as "A, B"; anything else as it is. */
    private static function listText($raw): string
    {
        $raw = trim((string) $raw);
        $list = str_starts_with($raw, '[') ? json_decode($raw, true) : null;

        return is_array($list) ? implode(', ', array_filter(array_map('trim', $list))) : $raw;
    }

    private static function date($v): string
    {
        if (blank($v)) return '';
        try {
            return Carbon::parse($v)->format('d M Y');
        } catch (\Throwable $e) {
            return (string) $v;
        }
    }

    /**
     * Every list, in the File Status tab order. Each entry:
     * key, group (the tab), label, found, rows (label => value), link (the tab, filtered).
     */
    public function status(string $fileNo): array
    {
        $nos = $this->aliases($fileNo);
        $fs = urlencode($fileNo);
        $sections = [];
        $add = function (string $key, string $group, string $label, array $rows, ?string $link, ?string $note = null) use (&$sections) {
            $sections[] = ['key' => $key, 'group' => $group, 'label' => $label, 'found' => count($rows) > 0,
                'rows' => array_slice($rows, 0, self::ROW_LIMIT), 'more' => max(0, count($rows) - self::ROW_LIMIT),
                'link' => $link, 'note' => $note];
        };

        // RofO: approved recommendations and OSS letters, as LandRofoController@index lists them.
        $rows = $this->db()->table('land_recommendations')
            ->whereIn('file_number', $nos)
            ->where(fn ($w) => $w->where('status', LandRecommendation::STATUS_APPROVED)->orWhereRaw("UPPER(ISNULL(type, '')) = 'OSS'"))
            ->selectRaw("file_number, applicant_name, land_rofo_serial_no, is_reissuance, rofo_batch_id, date_issued,
                CASE WHEN ISNULL(rofo_print_count, 0) > 0
                      AND (UPPER(ISNULL(type, '')) <> 'OSS'
                           OR EXISTS (SELECT 1 FROM security_codes sc WHERE sc.document_id = land_recommendations.id AND sc.document_type = 'Land ROFO'))
                     THEN 1 ELSE 0 END AS is_printed")
            ->orderByDesc('id')->get()
            ->map(fn ($r) => [
                'File No.' => $r->file_number,
                'Applicant' => $r->applicant_name,
                'Status' => ($r->is_reissuance ? 'Re-issuance · ' : '') . ($r->is_printed ? 'Printed' : 'Not Printed') . ($r->rofo_batch_id ? ' · Batch' : ''),
                'RofO Serial' => $r->land_rofo_serial_no,
                'Date Issued' => self::date($r->date_issued),
            ])->all();
        $add('rofo', 'RofO', 'RofO', $rows, route('land-rofos.index', ['url' => 'survey', 'search' => $fileNo, 'searched' => 1]));

        // CofO: the CofO register (CofO_staging), as PropertyCardController@getCofOData reads it.
        $rows = $this->anyOf($this->db()->table('CofO_staging'), ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno'], $nos)
            ->orderByDesc('id')->limit(50)
            ->get(['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'regNo', 'instrument_type', 'cofo_type', 'Grantee', 'transaction_date'])
            ->map(fn ($r) => [
                'File No.' => $this->firstNumber([$r->mlsFNo, $r->kangisFileNo, $r->NewKANGISFileno]),
                'Instrument' => $r->instrument_type ?: $r->cofo_type,
                'Reg. Particulars' => $r->regNo,
                'Grantee' => $r->Grantee,
                'Date' => self::date($r->transaction_date),
            ])->all();
        $add('cofo', 'CofO', 'CofO', $rows, route('propertycard.cofo', ['url' => 'survey', 'fs' => $fileNo]));

        // Decommissioned: flagged fileNumber rows and the decommissioned_files archive.
        $rows = $this->live($this->anyOf($this->db()->table('fileNumber'), ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo'], $nos))
            ->where('is_decommissioned', 1)
            ->get(['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'decommissioning_date', 'decommissioning_reason', 'successor_file_no'])
            ->map(fn ($r) => [
                'File No.' => $this->firstNumber([$r->mlsfNo, $r->kangisFileNo, $r->NewKANGISFileNo]),
                'Decommissioned' => self::date($r->decommissioning_date),
                'Reason' => $r->decommissioning_reason,
                'Successor' => $r->successor_file_no,
            ]);
        $archive = $this->anyOf($this->db()->table('decommissioned_files'), ['mls_file_no', 'kangis_file_no', 'new_kangis_file_no', 'file_no'], $nos)
            ->where(fn ($w) => $w->where('false_decommissioning', '<>', DecommissionScope::FALSE_DECOMMISSIONING)->orWhereNull('false_decommissioning'))
            ->orderBy('decommissioning_date')
            ->get(['mls_file_no', 'kangis_file_no', 'new_kangis_file_no', 'file_no', 'decommissioning_date', 'decommissioning_reason', 'successor_file_no'])
            ->map(fn ($r) => [
                'File No.' => $this->firstNumber([$r->mls_file_no, $r->kangis_file_no, $r->new_kangis_file_no, $r->file_no]),
                'Decommissioned' => self::date($r->decommissioning_date),
                'Reason' => $r->decommissioning_reason,
                'Successor' => $r->successor_file_no,
            ]);
        // One line per file and date: the archive repeats what the flag already says.
        $rows = $rows->concat($archive)->unique(fn ($r) => $r['File No.'] . '|' . $r['Decommissioned'])->values()->all();
        $add('decommissioned', 'Decommissioned', 'Decommissioned', $rows, route('file-decommissioning.decommissioned', ['url' => 'survey', 'fs' => $fileNo]));

        // Duplicates.
        $rows = $this->db()->table('duplicate_fileno')->whereIn('file_number', $nos)
            ->orderByDesc('created_at')
            ->get(['file_number', 'registry', 'file_title', 'category', 'comment'])
            ->map(fn ($r) => [
                'File No.' => $r->file_number,
                'Registry' => $r->registry,
                'Title' => $r->file_title,
                'Category' => $r->category,
                'Comment' => $r->comment,
            ])->all();
        $add('duplicates', 'Duplicates', 'Duplicates', $rows, route('file-search-db.index', ['url' => 'survey', 'fs' => $fileNo]));

        // Regrant Files: the register (either side of a re-grant) and the files due for re-grant.
        $rows = $this->live($this->anyOf($this->db()->table('title_status_applications'), ['file_no', 'see_fileno'], $nos))
            ->whereIn('title_type', TitleStatusApplication::REGRANT_TYPES)
            ->orderByDesc('created_at')
            ->get(['file_no', 'see_fileno', 'title_type', 'applicant_name', 'file_title', 'created_at'])
            ->map(fn ($r) => [
                'File No.' => $r->file_no,
                'Type' => $r->title_type,
                'See File No.' => $r->see_fileno,
                'Holder' => $r->applicant_name ?: $r->file_title,
                'Captured' => self::date($r->created_at),
            ])->all();
        $add('regrant', 'Regrant Files', 'Regrant Register', $rows, route('regrant.index', ['tab' => 'register', 'url' => 'survey', 'search' => $fileNo]));

        $rows = app(RegrantTermService::class)->allDue()
            ->filter(fn ($r) => in_array(self::normalise($r->file_no ?? ''), $nos, true))
            ->map(fn ($r) => [
                'File No.' => $r->file_no,
                'Holder' => $r->holder ?? '',
                'Term' => ($r->term_years ?? '') !== '' ? $r->term_years . ' years' : '',
                'Expiry Year' => $r->expiry_year ?? '',
                'From' => $r->date_source ?? '',
            ])->values()->all();
        $add('regrant_due', 'Regrant Files', 'To be Regranted', $rows, route('regrant.index', ['tab' => 'due', 'url' => 'survey', 'search' => $fileNo]));

        // Title Status: every registry's records, as the read-only (Deeds / DCIV) view aggregates them.
        $rows = $this->live($this->db()->table('title_status_applications')->whereIn('file_no', $nos))
            ->orderByDesc('created_at')
            ->get(['file_no', 'title_type', 'title_no', 'status', 'url', 'created_at'])
            ->map(fn ($r) => [
                'File No.' => $r->file_no,
                'Title Type' => $r->title_type,
                'Title No.' => $r->title_no,
                'Status' => $r->status,
                'Raised by' => $r->url ? ucfirst($r->url) : '',
                'Date' => self::date($r->created_at),
            ])->all();
        $add('title_status', 'Title Status', 'Title Status', $rows, route('title-status.index', ['url' => 'survey', 'search' => $fileNo]));

        // DCIV: a DCIV file numbered or related to this one, and a land file linked to its DCIV file.
        // related_fileno is a JSON list on most rows (["RES-2014-2459"]), plain text on the rest.
        $rows = $this->live($this->db()->table('dciv_file_no'))
            ->where(function ($w) use ($nos) {
                $w->whereIn('full_file_number', $nos)->orWhereIn('related_fileno', $nos);
                foreach ($nos as $n) $w->orWhere('related_fileno', 'like', '%"' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $n) . '"%');
            })
            ->orderByDesc('id')
            ->get(['full_file_number', 'related_fileno', 'file_name', 'dciv_reason', 'reason', 'commissioning_date'])
            ->map(fn ($r) => [
                'DCIV File No.' => $r->full_file_number,
                'Related File' => self::listText($r->related_fileno),
                'Title' => $r->file_name,
                'Reason' => $r->dciv_reason ?: $r->reason,
                'Commissioned' => self::date($r->commissioning_date),
            ]);
        $linked = $this->live($this->db()->table('file_indexings'))
            ->where(function ($w) use ($nos) {
                $w->where(fn ($x) => $x->whereIn('file_number', $nos)->whereNotNull('dciv_fileno')->where('dciv_fileno', '<>', ''))
                  ->orWhere(fn ($x) => $x->whereIn('file_number', $nos)->whereIn('registry', ['DCIV', 'LPCC']));
            })
            ->get(['file_number', 'dciv_fileno', 'file_title', 'dciv_reason', 'dciv_status', 'created_at'])
            ->map(fn ($r) => [
                'DCIV File No.' => $r->dciv_fileno ?: $r->file_number,
                'Related File' => $r->dciv_fileno ? $r->file_number : '',
                'Title' => $r->file_title,
                'Reason' => $r->dciv_reason ?: $r->dciv_status,
                'Commissioned' => self::date($r->created_at),
            ]);
        $rows = $rows->concat($linked)->unique(fn ($r) => mb_strtoupper((string) $r['DCIV File No.']))->values()->all();
        $add('dciv', 'DCIV', 'DCIV', $rows, route('dciv-generation.index', ['url' => 'survey', 'fs' => $fileNo]));

        // Encumbrance.
        $rows = $this->anyOf($this->db()->table('caveats'), ['file_number', 'file_number_kangis', 'file_number_mlsf', 'file_number_new_kangis'], $nos)
            ->orderByDesc('start_date')
            ->get(['caveat_number', 'file_number', 'file_number_mlsf', 'file_number_kangis', 'file_number_new_kangis', 'status', 'petitioner', 'start_date', 'release_date'])
            ->map(fn ($r) => [
                'Caveat No.' => $r->caveat_number,
                'File No.' => $this->firstNumber([$r->file_number, $r->file_number_mlsf, $r->file_number_kangis, $r->file_number_new_kangis]),
                'Status' => ucfirst((string) $r->status),
                'Caveator' => $r->petitioner,
                'Placed' => self::date($r->start_date),
                'Lifted' => self::date($r->release_date),
            ])->all();
        $add('caveat', 'Encumbrance', 'Caveat', $rows, route('caveat.index') . '?url=survey&fs=' . $fs);

        $add('mortgage', 'Encumbrance', 'Mortgage', $this->instruments($nos, fn ($q) => $q->whereIn('instrument_type', self::MORTGAGE_TYPES)),
            route('mortgages.index') . '?url=survey&fs=' . $fs);

        $surrender = function ($q) {
            $q->where(fn ($w) => $w->whereIn('instrument_type', self::SURRENDER_TYPES)
                ->orWhereRaw("LOWER(REPLACE(REPLACE(instrument_type, '&', 'and'), '  ', ' ')) IN (?, ?, ?)",
                    ['deed of surrender and release', 'deed of surrender & release', 'deed of surrender, and release']));
        };
        $add('surrender', 'Encumbrance', 'Surrender & Release', $this->instruments($nos, $surrender),
            route('surrender-release.index', ['url' => 'survey', 'fs' => $fileNo]));

        $add('lien', 'Encumbrance', 'Lien', [], null, 'No Lien list yet.');

        return $sections;
    }

    /** Registered instruments of a kind on the three sources the Mortgage / Surrender pages union. */
    private function instruments(array $nos, \Closure $type): array
    {
        $cols = ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno'];
        $out = collect();

        $this->live($this->anyOf($this->db()->table('instrument_capture'), $cols, $nos))->where($type)
            ->orderByDesc('id')->limit(50)
            ->get(['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'instrument_type', 'serial_no', 'page_no', 'volume_no', 'party_1_name', 'party_2_name', 'instrument_date', 'reg_date'])
            ->each(fn ($r) => $out->push([
                'File No.' => $this->firstNumber([$r->mlsFNo, $r->kangisFileNo, $r->NewKANGISFileno]),
                'Instrument' => $r->instrument_type,
                'Reg. Particulars' => $this->particulars($r->serial_no, $r->page_no, $r->volume_no),
                'Parties' => trim(implode(' / ', array_filter([$r->party_1_name, $r->party_2_name]))),
                'Date' => self::date($r->reg_date ?: $r->instrument_date),
            ]));

        foreach (['pra', 'file_history_staging'] as $table) {
            $this->live($this->anyOf($this->db()->table($table), $cols, $nos))->where($type)
                ->orderByDesc('id')->limit(50)
                ->get(['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'instrument_type', 'serialNo', 'pageNo', 'volumeNo',
                    'Mortgagor', 'Mortgagee', 'Surrenderor', 'Surrenderee', 'transaction_date'])
                ->each(fn ($r) => $out->push([
                    'File No.' => $this->firstNumber([$r->mlsFNo, $r->kangisFileNo, $r->NewKANGISFileno]),
                    'Instrument' => $r->instrument_type,
                    'Reg. Particulars' => $this->particulars($r->serialNo, $r->pageNo, $r->volumeNo),
                    'Parties' => trim(implode(' / ', array_filter([$r->Mortgagor ?: $r->Surrenderor, $r->Mortgagee ?: $r->Surrenderee]))),
                    'Date' => self::date($r->transaction_date),
                ]));
        }

        // The same instrument is often on more than one source.
        return $out->unique(fn ($r) => $r['File No.'] . '|' . $r['Instrument'] . '|' . $r['Reg. Particulars'])->values()->all();
    }

    private function particulars($serial, $page, $volume): string
    {
        $serial = trim((string) $serial); $page = trim((string) $page); $volume = trim((string) $volume);

        return ($serial . $page . $volume) === '' ? '' : "{$serial}/{$page}/{$volume}";
    }
}
