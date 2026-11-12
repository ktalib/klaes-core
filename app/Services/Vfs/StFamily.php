<?php

namespace App\Services\Vfs;

use Illuminate\Support\Facades\DB;

/**
 * The ST family of a file number (CR-11112026-2 item 15, user 2026-11-12), read from st_file_numbers:
 *
 *   PRIMARY row   np_fileno = the Primary ST (ST-RES-2026-18), fileno = its Land File (RES-RC-1982-47)
 *   PUA row       fileno = the unit (ST-MIXED-2026-1-003), parent_id = its PRIMARY row
 *   SUA row       fileno = the unit (ST-COM-2026-16-001); np_fileno is an imaginary mother: no PRIMARY row,
 *                 no Land File, nothing indexed. An SUA stands alone (user, 2026-11-12).
 *
 * Read only. Each member carries its file_indexings id when it is indexed, so the VFS can link to it.
 */
class StFamily
{
    private function db()
    {
        return DB::connection('sqlsrv');
    }

    /**
     * The family of $number, or null when it is not an ST file nor the Land File of one.
     *
     * @return array{kind: string, number: string, land: ?array, primary: ?array, units: array, sua_mother: ?string}|null
     */
    public function of(?string $number): ?array
    {
        $number = trim((string) $number);
        if ($number === '') return null;

        $cols = ['id', 'np_fileno', 'fileno', 'mls_fileno', 'file_no_type', 'parent_id'];
        $st = $this->db()->table('st_file_numbers');

        // An SUA: itself only, its mother number as a label.
        $sua = (clone $st)->where('file_no_type', 'SUA')->where('fileno', $number)->first($cols);
        if ($sua) {
            return ['kind' => 'sua', 'number' => $number, 'land' => null, 'primary' => null, 'units' => [],
                'sua_mother' => $sua->np_fileno ?: null];
        }

        $kind = null;
        $primary = null;
        if ($pua = (clone $st)->where('file_no_type', 'PUA')->where('fileno', $number)->first($cols)) {
            $kind = 'pua';
            $primary = $pua->parent_id ? (clone $st)->where('id', $pua->parent_id)->first($cols) : null;
            // A unit whose Primary row is missing still names its Primary by np_fileno.
            $primary = $primary ?: (clone $st)->where('file_no_type', 'PRIMARY')->where('np_fileno', $pua->np_fileno)->first($cols);
        } elseif ($primary = (clone $st)->where('file_no_type', 'PRIMARY')->where('np_fileno', $number)->first($cols)) {
            $kind = 'primary';
        } elseif ($primary = (clone $st)->where('file_no_type', 'PRIMARY')->where(fn ($q) => $q->where('fileno', $number)->orWhere('mls_fileno', $number))->orderByDesc('id')->first($cols)) {
            $kind = 'land';
        }
        if (! $kind || ! $primary) {
            return $kind === 'pua'
                ? ['kind' => 'pua', 'number' => $number, 'land' => null, 'primary' => null, 'units' => [], 'sua_mother' => null]
                : null;
        }

        $landNo = trim((string) ($primary->fileno ?: $primary->mls_fileno));
        $units = (clone $st)->where('file_no_type', 'PUA')
            ->where(fn ($q) => $q->where('parent_id', $primary->id)->orWhere('np_fileno', $primary->np_fileno))
            ->orderBy('unit_sequence')->orderBy('fileno')->pluck('fileno')->map(fn ($n) => trim((string) $n))->unique()->values();

        $ids = $this->indexedIds(array_merge([$landNo, $primary->np_fileno], $units->all()));
        $member = fn (string $n) => ['number' => $n, 'indexing_id' => $ids[mb_strtoupper($n)] ?? null];

        return [
            'kind' => $kind,
            'number' => $number,
            'land' => $landNo !== '' ? $member($landNo) : null,
            'primary' => $member(trim((string) $primary->np_fileno)),
            'units' => $units->map($member)->all(),
            'sua_mother' => null,
        ];
    }

    /** Every file number in the family (for widening a search), or [] when there is none. */
    public function numbers(?string $number): array
    {
        $f = $this->of($number);
        if (! $f) return [];

        return collect([$f['number'], $f['land']['number'] ?? null, $f['primary']['number'] ?? null])
            ->merge(collect($f['units'])->pluck('number'))
            ->filter()->unique()->values()->all();
    }

    /** file_indexings ids by upper-cased file number, live rows only. */
    private function indexedIds(array $numbers): array
    {
        $numbers = array_values(array_filter(array_unique($numbers)));
        if (! $numbers) return [];

        return $this->db()->table('file_indexings')->whereIn('file_number', $numbers)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')->get(['id', 'file_number'])   // newest first, so the oldest row is written last and wins
            ->mapWithKeys(fn ($r) => [mb_strtoupper(trim((string) $r->file_number)) => (int) $r->id])
            ->all();
    }
}
