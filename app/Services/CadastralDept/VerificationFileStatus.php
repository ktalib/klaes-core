<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\TitleStatusApplication;
use App\Services\RegrantTermService;
use App\Support\DecommissionScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * During Verification: which of the Verification > File Status lists the
 * file is on (user, 2026-10-15). RofO, CofO, Decommissioned, Duplicate (the
 * Problem Files database), Re-grant Register, To Be Re-granted, Title Status,
 * DCIV, Caveat and Mortgage. A mortgage is checked for a Deed of Surrender &
 * Release on the same file.
 *
 * Read only. The file is matched on every number it is known by (the case's
 * number, its file_indexings row and the fileNumber rows that share one of
 * those numbers) plus its PropID, because one file can sit under different
 * columns in different tables.
 */
class VerificationFileStatus
{
    private const CONN = 'sqlsrv';

    public const MORTGAGE_TYPES = ['Deed of Mortgage', 'Tripartite Mortgage'];

    /** As SurrenderReleaseController / MortgageController::getAssociatedSurrenderReleases. */
    public const RELEASE_TYPES = [
        'Deed of Surrender and Release',
        'Deed of Surrender & Release',
        'Deed of Surrender, and Release',
        'Surrender and Release',
        'Surrender & Release',
    ];

    /** Instrument tables and the columns that can hold the file number. */
    private const INSTRUMENT_TABLES = [
        'instrument_capture' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
        'pra' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno'],
        'file_history_staging' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'temp_fileno'],
    ];

    public function __construct(private RegrantTermService $regrantTerms)
    {
    }

    /**
     * One row per list, in the sidebar's order. tone: 'warn' (needs attention),
     * 'found' (on the list), 'clear' (not on it), 'error' (could not check).
     *
     * note: the plain-language notice for the officer, worded as Legal Search words
     * its own (user, 2026-10-15), or '' when the row needs no notice.
     *
     * @return array<int, array{key: string, label: string, tone: string, status: string, detail: string, note: string}>
     */
    public function results(CadastralCase $case): array
    {
        [$numbers, $props] = $this->identity($case);

        $checks = [
            'rofo' => 'RofO',
            'cofo' => 'CofO',
            'decommissioned' => 'Decommissioned Files',
            'duplicate' => 'Duplicate Files',
            'regrant_register' => 'Re-grant Register',
            'regrant_due' => 'To Be Re-granted',
            'title_status' => 'Title Status Update',
            'dciv' => 'DCIV Management',
            'caveat' => 'Caveat',
            'mortgage' => 'Mortgage',
        ];

        $out = [];
        foreach ($checks as $key => $label) {
            $note = '';
            try {
                $res = $numbers || $props
                    ? $this->{'check' . str_replace('_', '', ucwords($key, '_'))}($numbers, $props)
                    : ['error', 'Not checked', 'The case has no file number.'];
                [$tone, $status, $detail] = $res;
                $note = $res[3] ?? '';
            } catch (\Throwable $e) {
                Log::warning('Verification file status check failed', ['case' => $case->id, 'check' => $key, 'error' => $e->getMessage()]);
                [$tone, $status, $detail] = ['error', 'Could not check', ''];
            }
            // A recommendation without a RofO yet is titled as such (user, 2026-10-15).
            $label = $key === 'rofo' && $status === 'Recommended' ? 'RofO recommendation' : $label;
            $out[] = compact('key', 'label', 'tone', 'status', 'detail', 'note');
        }

        return $out;
    }

    /**
     * Every file number the file is known by, and its PropIDs.
     *
     * @return array{0: string[], 1: string[]}
     */
    public function identity(CadastralCase $case): array
    {
        $db = DB::connection(self::CONN);
        $numbers = [trim((string) $case->file_number)];
        $props = [];

        if ($case->file_indexing_id) {
            $fi = $db->table('file_indexings')->where('id', $case->file_indexing_id)
                ->first(['file_number', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no', 'dciv_fileno', 'prop_id']);
            if ($fi) {
                array_push($numbers, $fi->file_number, $fi->mls_file_no, $fi->kangis_file_no, $fi->new_kangis_file_no, $fi->dciv_fileno);
                $props[] = $fi->prop_id;
            }
        }

        $numbers = $this->clean($numbers);
        if ($numbers) {
            $db->table('fileNumber')
                ->where(fn ($q) => $q->whereIn('mlsfNo', $numbers)->orWhereIn('kangisFileNo', $numbers)->orWhereIn('NewKANGISFileNo', $numbers))
                ->get(['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'dciv_fileno'])
                ->each(function ($r) use (&$numbers) {
                    array_push($numbers, $r->mlsfNo, $r->kangisFileNo, $r->NewKANGISFileNo, $r->dciv_fileno);
                });
        }

        return [$this->clean($numbers), $this->clean($props)];
    }

    /** Land recommendations, and SLTR recommendations (keyed by sltr_number) for SLTR files. */
    private function checkRofo(array $numbers, array $props): array
    {
        $db = DB::connection(self::CONN);
        $rows = $db->table('land_recommendations')
            ->whereIn('file_number', $numbers)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->get(['status', 'rofo_status', 'land_rofo_serial_no', 'rofo_generated_at', 'date_issued'])
            ->each(fn ($r) => $r->kind = '');
        $sltr = $db->table('sltr_recommendations')
            ->whereIn('sltr_number', $numbers)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get(['status', 'rofo_status', 'sltr_rofo_serial_no as land_rofo_serial_no', 'rofo_generated_at', 'date_issued'])
            ->each(fn ($r) => $r->kind = 'SLTR ');
        $rows = $rows->concat($sltr)->values();

        $generated = $rows->first(fn ($r) => strtolower((string) $r->rofo_status) === 'generated');
        if ($generated) {
            return ['found', 'Yes', $this->join([
                $generated->kind ? trim($generated->kind) . ' RofO' : null,
                $generated->land_rofo_serial_no ? 'Serial ' . $generated->land_rofo_serial_no : null,
                $this->date($generated->rofo_generated_at, 'generated'),
                $this->date($generated->date_issued, 'issued'),
            ])];
        }

        if ($rows->isEmpty()) {
            return ['clear', 'No', ''];
        }
        $rec = $rows->first();

        return ['found', 'Recommended', $this->join([
            $rec->status ? ucfirst(strtolower((string) $rec->status)) . ' ' . $rec->kind . 'recommendation' : $rec->kind . 'Recommendation on record',
            'RofO not generated yet',
        ]), 'This file has ' . ($rec->status ? 'a' . (preg_match('/^[aeiou]/i', (string) $rec->status) ? 'n ' : ' ') . strtolower((string) $rec->status) . ' ' : 'a ')
            . $rec->kind . 'recommendation; the RofO has not been generated yet.'];
    }

    private function checkCofo(array $numbers, array $props): array
    {
        $rows = DB::connection(self::CONN)->table('CofO_staging')
            ->where(fn ($q) => $this->matchFile($q, ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno'], $numbers, $props))
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->get(['cofo_no', 'regNo', 'cofo_type', 'instrument_type']);

        if ($rows->isEmpty()) {
            // An SLTR CofO is registered in deed_registrations (as SltrCofoPipeline reads it).
            $sltr = DB::connection(self::CONN)->table('deed_registrations')
                ->where('instrument_type', \App\Services\Cofo\SltrCofoPipeline::INSTRUMENT_TYPE)
                ->where('status', 'registered')
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->where(fn ($q) => $this->matchFile($q, ['fileno'], $numbers, $props))
                ->orderByDesc('id')
                ->first(['registration_number', 'deeds_date']);

            return $sltr
                ? ['found', 'Yes', $this->join(['SLTR CofO', $sltr->registration_number ? 'Reg. ' . $sltr->registration_number : null, $this->date($sltr->deeds_date, 'registered')])]
                : ['clear', 'No', ''];
        }
        $first = $rows->first();

        return ['found', 'Yes', $this->join([
            $rows->count() > 1 ? $rows->count() . ' records' : null,
            $first->cofo_no ? 'CofO No. ' . $first->cofo_no : null,
            $first->regNo ? 'Reg. ' . $first->regNo : null,
            $first->cofo_type ?: $first->instrument_type,
        ])];
    }

    private function checkDecommissioned(array $numbers, array $props): array
    {
        $db = DB::connection(self::CONN);
        $row = $db->table('fileNumber')
            ->where('is_decommissioned', 1)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(fn ($q) => $q->whereIn('mlsfNo', $numbers)->orWhereIn('kangisFileNo', $numbers)->orWhereIn('NewKANGISFileNo', $numbers))
            ->orderByDesc('id')
            ->first(['decommissioning_date', 'decommissioning_reason', 'successor_file_no']);

        if (!$row) {
            $row = $db->table('decommissioned_files')
                ->where(fn ($q) => $q->where('false_decommissioning', '<>', DecommissionScope::FALSE_DECOMMISSIONING)->orWhereNull('false_decommissioning'))
                ->where(fn ($q) => $q->whereIn('file_no', $numbers)->orWhereIn('mls_file_no', $numbers)
                    ->orWhereIn('kangis_file_no', $numbers)->orWhereIn('new_kangis_file_no', $numbers))
                ->orderByDesc('decommissioning_date')
                ->first(['decommissioning_date', 'decommissioning_reason', 'successor_file_no']);
        }

        return $row
            ? ['found', 'Decommissioned', $this->join([
                $this->date($row->decommissioning_date, 'on'),
                $row->successor_file_no ? 'now ' . $row->successor_file_no : null,
                $row->decommissioning_reason,
            ]), 'This file has been decommissioned' . ($row->successor_file_no ? ' and replaced by ' . $row->successor_file_no : '')
                . (trim((string) $row->decommissioning_reason) !== '' ? ' (' . trim($row->decommissioning_reason) . ')' : '') . '.']
            : ['clear', 'No', ''];
    }

    private function checkDuplicate(array $numbers, array $props): array
    {
        $rows = DB::connection(self::CONN)->table('duplicate_fileno')
            ->where(fn ($q) => $this->matchFile($q, ['file_number'], $numbers, $props))
            ->orderByDesc('id')
            ->get(['registry', 'category', 'comment']);

        if ($rows->isEmpty()) {
            return ['clear', 'No', ''];
        }
        $first = $rows->first();

        $wrc = $rows->contains(fn ($r) => stripos((string) $r->category . ' ' . $r->comment, 'WRC') !== false);

        return ['warn', 'Listed', $this->join([
            $rows->count() > 1 ? $rows->count() . ' entries' : null,
            $first->registry,
            $first->category,
            $first->comment,
        ]), $wrc
            ? 'This file is listed in the Problem Files database as Withdrawn / Revoked / Cancelled (W/R/C).'
            : 'This file number is listed in the Problem Files database (duplicate file). Confirm it is the right file before going on.'];
    }

    private function checkRegrantRegister(array $numbers, array $props): array
    {
        $rows = DB::connection(self::CONN)->table('title_status_applications')
            ->whereIn('title_type', TitleStatusApplication::REGRANT_TYPES)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(fn ($q) => $q->whereIn('file_no', $numbers)->orWhereIn('see_fileno', $numbers))
            ->orderByDesc('id')
            ->get(['title_type', 'file_no', 'see_fileno']);

        if ($rows->isEmpty()) {
            return ['clear', 'No', ''];
        }

        // Both directions are stored as separate rows. This file's own rows say it as
        // it is; a row on the other file is read back the other way round.
        $wanted = array_map([$this, 'norm'], $numbers);
        $own = $rows->filter(fn ($r) => in_array($this->norm($r->file_no), $wanted, true));
        $lines = $own->isNotEmpty()
            ? $own->map(fn ($r) => trim($r->title_type . ' ' . $r->see_fileno))
            : $rows->map(fn ($r) => match ($r->title_type) {
                'Re-granted From' => 'Re-granted To ' . $r->file_no,
                'Re-granted To' => 'Re-granted From ' . $r->file_no,
                default => 'Re-grant with ' . $r->file_no,
            });

        $text = $lines->unique()->implode(' · ');

        return ['found', 'Yes', $text, 'This file is on the Re-grant Register (' . $text . ').'];
    }

    private function checkRegrantDue(array $numbers, array $props): array
    {
        $wanted = array_map([$this, 'norm'], $numbers);
        $row = $this->regrantTerms->allDue()->first(fn ($r) => in_array($this->norm($r->file_no ?? ''), $wanted, true));

        return $row
            ? ['warn', 'Due for Re-grant', $this->join([
                isset($row->expiry_date) && $row->expiry_date ? $this->date($row->expiry_date, 'term ran out') : null,
                isset($row->years_overdue) ? $row->years_overdue . ' yr(s) overdue' : null,
            ]), 'The term of this title has run out: the file is due for Re-grant.']
            : ['clear', 'No', ''];
    }

    private function checkTitleStatus(array $numbers, array $props): array
    {
        $types = DB::connection(self::CONN)->table('title_status_applications')
            ->whereNotIn('title_type', TitleStatusApplication::REGRANT_TYPES)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->whereIn('file_no', $numbers)
            ->orderByDesc('id')
            ->pluck('title_type')->filter()->unique();

        return $types->isNotEmpty()
            ? ['found', 'Yes', $types->implode(', '), 'A Title Status Update is recorded on this file: ' . $types->implode(', ') . '.']
            : ['clear', 'No', ''];
    }

    /**
     * Under Investigation, as Legal Search reads it (LegalSearchService::resolveDcivInvestigation):
     * the land file a DCIV / LPCC file was opened against (master_dciv_links.related_file_number,
     * dciv_file_no.related_fileno). Only the land file is under investigation: picking the DCIV /
     * LPCC file itself (DCIV-1, LPC-102) gives no notice (user's voice note, 2026-10-20).
     */
    private function checkDciv(array $numbers, array $props): array
    {
        $db = DB::connection(self::CONN);
        $upper = array_values(array_unique(array_map(fn ($n) => strtoupper(trim($n)), $numbers)));
        $link = null;
        if ($upper && \Illuminate\Support\Facades\Schema::connection(self::CONN)->hasTable('master_dciv_links')) {
            $links = $db->table('master_dciv_links')
                ->where(function ($q) use ($upper) {
                    $q->whereIn(DB::raw('UPPER(LTRIM(RTRIM(related_file_number)))'), $upper);
                })
                ->orderByDesc('id')
                ->get(['dciv_file_number', 'related_file_number', 'dciv_reason']);
            $link = $links->first(fn ($l) => trim((string) $l->dciv_reason) !== '') ?? $links->first();
        }
        $row = $db->table('dciv_file_no')
            ->whereIn('related_fileno', $numbers)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first(['full_file_number', 'related_fileno', 'commissioning_date']);

        if (!$link && !$row) {
            return ['clear', 'No', ''];
        }
        $dcivNo = trim((string) ($link->dciv_file_number ?? '')) ?: trim((string) ($row->full_file_number ?? ''));
        $reason = trim((string) ($link->dciv_reason ?? ''));

        return ['warn', 'Under Investigation', $this->join([
            $dcivNo ?: null,
            $row && $row->related_fileno ? 'for ' . $row->related_fileno : ($link && $link->related_file_number ? 'for ' . $link->related_file_number : null),
            $row ? $this->date($row->commissioning_date, 'commissioned') : null,
            $reason ? 'Reason: ' . $reason : null,
        ]), 'This Property is under Investigation.' . ($dcivNo ? ' DCIV file ' . $dcivNo . '.' : '') . ($reason ? ' Reason: ' . $reason . '.' : '')];
    }

    private function checkCaveat(array $numbers, array $props): array
    {
        $rows = DB::connection(self::CONN)->table('caveats')
            ->where(fn ($q) => $this->matchFile($q, ['file_number', 'file_number_kangis', 'file_number_mlsf', 'file_number_new_kangis'], $numbers, $props))
            ->orderByDesc('id')
            ->get(['caveat_number', 'status', 'start_date', 'release_date']);

        if ($rows->isEmpty()) {
            return ['clear', 'No', ''];
        }
        $active = $rows->filter(fn ($r) => !$r->release_date && strtolower((string) $r->status) !== 'released' && strtolower((string) $r->status) !== 'lifted');
        if ($active->isNotEmpty()) {
            $c = $active->first();

            return ['warn', 'Active caveat', $this->join([$c->caveat_number, $this->date($c->start_date, 'since')]),
                'N.B. This Property is Under an Active Caveat!!!' . ($c->caveat_number ? ' (' . $c->caveat_number . ')' : '')];
        }
        $c = $rows->first();

        return ['found', 'Lifted', $this->join([$c->caveat_number, $this->date($c->release_date, 'released')]),
            'A caveat on this property has been lifted' . ($c->caveat_number ? ' (' . $c->caveat_number . ')' : '') . '.'];
    }

    /** A mortgage counts as released when the file also has a Deed of Surrender & Release. */
    private function checkMortgage(array $numbers, array $props): array
    {
        $mortgages = $this->instruments(fn ($q) => $q->whereIn('instrument_type', self::MORTGAGE_TYPES), $numbers, $props);
        if ($mortgages->isEmpty()) {
            return ['clear', 'No', ''];
        }
        $releases = $this->instruments(function ($q) {
            $q->whereIn('instrument_type', self::RELEASE_TYPES)
                ->orWhereRaw("LOWER(REPLACE(REPLACE(instrument_type, '&', 'and'), '  ', ' ')) IN (?, ?)", [
                    'deed of surrender and release',
                    'deed of surrender, and release',
                ]);
        }, $numbers, $props);

        // One deed is often captured in more than one table: count it once per
        // registration number (rows without one each count on their own).
        $mCount = $this->distinctDeeds($mortgages);
        $rCount = $this->distinctDeeds($releases);
        $m = $mCount . ' mortgage' . ($mCount > 1 ? 's' : '');
        $mReg = $mortgages->pluck('reg')->filter()->unique()->implode(', ');
        if ($rCount === 0) {
            return ['warn', 'Not released', $this->join([$m . ($mReg ? ' (' . $mReg . ')' : ''), 'no Deed of Release on the file']),
                'This Property is Under an Active Mortgage !!! No Deed of Surrender & Release is on the file.'];
        }
        $rReg = $releases->pluck('reg')->filter()->unique()->implode(', ');
        $r = $rCount . ' Deed' . ($rCount > 1 ? 's' : '') . ' of Release';
        if ($rCount < $mCount) {
            return ['warn', 'Partly released', $this->join([$m, $r . ($rReg ? ' (' . $rReg . ')' : ''), 'check each mortgage has its release']),
                "This Property is Under an Active Mortgage !!! Only {$rCount} of {$mCount} mortgages have a Deed of Surrender & Release."];
        }

        return ['found', 'Released', $this->join([$m . ($mReg ? ' (' . $mReg . ')' : ''), $r . ($rReg ? ' (' . $rReg . ')' : '')]),
            'The mortgage on this property has been released by a Deed of Surrender & Release' . ($rReg ? ' (Reg. ' . $rReg . ')' : '') . '.'];
    }

    private function distinctDeeds($rows): int
    {
        return $rows->pluck('reg')->filter()->map(fn ($r) => $this->norm($r))->unique()->count()
            + $rows->filter(fn ($r) => $r['reg'] === '')->count();
    }

    /** Matching rows from the three instrument tables, as [reg]. */
    private function instruments(\Closure $type, array $numbers, array $props)
    {
        $db = DB::connection(self::CONN);
        $rows = collect();
        foreach (self::INSTRUMENT_TABLES as $table => $cols) {
            $reg = $table === 'instrument_capture' ? 'registration_number' : 'regNo';
            $rows = $rows->concat($db->table($table)
                ->where($type)
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->where(fn ($q) => $this->matchFile($q, $cols, $numbers, $props))
                ->get([DB::raw("CAST(ISNULL($reg, '') AS NVARCHAR(200)) as reg")]));
        }

        return $rows->map(fn ($r) => ['reg' => trim((string) $r->reg)]);
    }

    /** Any of the file-number columns holds one of the numbers, or the PropID matches. */
    private function matchFile($q, array $cols, array $numbers, array $props): void
    {
        foreach ($cols as $col) {
            if ($numbers) {
                $q->orWhereIn($col, $numbers);
            }
        }
        if ($props) {
            $q->orWhereIn('prop_id', $props);
        }
    }

    private function clean(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($v) => trim((string) $v), $values),
            fn ($v) => $v !== '' && !in_array(strtoupper($v), ['N/A', 'NA', '-', '—', 'NULL'], true))));
    }

    private function norm(?string $v): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $v));
    }

    private function date($value, string $prefix): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return $prefix . ' ' . \Carbon\Carbon::parse($value)->format('d M Y');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function join(array $parts): string
    {
        return implode(' · ', array_filter(array_map(fn ($p) => trim((string) $p), $parts), fn ($p) => $p !== ''));
    }
}
