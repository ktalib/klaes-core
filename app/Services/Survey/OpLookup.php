<?php

namespace App\Services\Survey;

use Illuminate\Support\Facades\DB;

/**
 * Occupancy Permits for the Plot Confirmation app (2026-10-30). The OPs live in
 * Deeds, not in Survey (survey_op_records is empty): instrument_capture rows of
 * type "Occupancy Permit (OP)", and the older standalone pra rows (the same split
 * as App\Services\OpRecordSource). Keyed "capture-<id>" / "pra-<id>".
 *
 * The allottee is party 2 / Grantee: the OP's original allottee, never the current
 * holder (App\Support\OpAllottee).
 */
class OpLookup
{
    private const OP = 'Occupancy Permit (OP)';

    /** Up to $limit OPs whose serial, allottee, plot no., file no. or reg. no. match. */
    public function search(string $term, int $limit = 30): array
    {
        $term = trim(preg_replace('/\s+/', ' ', $term));
        if (mb_strlen($term) < 2) {
            return [];
        }
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';

        $capture = $this->captureQuery()
            ->where(function ($q) use ($like, $term) {
                $q->whereRaw('CAST(ic.op_serial_number AS NVARCHAR(50)) = ?', [$term])
                  ->orWhere('ic.party_2_name', 'like', $like)
                  ->orWhere('ic.plot_number', 'like', $like)
                  ->orWhere('ic.temp_fileno', 'like', $like)
                  ->orWhere('ic.mlsFNo', 'like', $like)
                  ->orWhere('ic.registration_number', 'like', $like);
            })
            ->orderByDesc('ic.id')->limit($limit)->get();

        $pra = $this->praQuery()
            ->where(function ($q) use ($like, $term) {
                $q->whereRaw('CAST(p.op_serial_number AS NVARCHAR(50)) = ?', [$term])
                  ->orWhere('p.Grantee', 'like', $like)
                  ->orWhere('p.plot_no', 'like', $like)
                  ->orWhere('p.temp_fileno', 'like', $like)
                  ->orWhere('p.mlsFNo', 'like', $like)
                  ->orWhereRaw('CAST(p.regNo AS NVARCHAR(100)) LIKE ?', [$like]);
            })
            ->orderByDesc('p.id')->limit($limit)->get();

        return $capture->map(fn ($r) => $this->fromCapture($r))
            ->merge($pra->map(fn ($r) => $this->fromPra($r)))
            ->take($limit)->values()->all();
    }

    public function find(string $key): ?array
    {
        if (! preg_match('/^(capture|pra)-(\d+)$/', $key, $m)) {
            return null;
        }
        if ($m[1] === 'capture') {
            $r = $this->captureQuery()->where('ic.id', (int) $m[2])->first();

            return $r ? $this->fromCapture($r) : null;
        }
        $r = $this->praQuery()->where('p.id', (int) $m[2])->first();

        return $r ? $this->fromPra($r) : null;
    }

    public static function key(?string $source, $id): ?string
    {
        return $source && $id ? "{$source}-{$id}" : null;
    }

    private function captureQuery()
    {
        return DB::connection('sqlsrv')->table('instrument_capture as ic')
            ->where('ic.instrument_type', self::OP)
            ->where(fn ($q) => $q->whereNull('ic.is_deleted')->orWhere('ic.is_deleted', 0))
            ->select(['ic.id', 'ic.op_serial_number', 'ic.op_type', 'ic.party_2_name', 'ic.party_2_phone', 'ic.plot_number', 'ic.plot_size',
                      'ic.district', 'ic.lga', 'ic.property_description', 'ic.property_location', 'ic.lpkn_no', 'ic.tp_no',
                      'ic.temp_fileno', 'ic.mlsFNo', 'ic.registration_number', 'ic.land_use', 'ic.reg_date']);
    }

    private function praQuery()
    {
        return DB::connection('sqlsrv')->table('pra as p')
            ->where('p.instrument_type', self::OP)
            ->whereNull('p.instrument_capture_id')
            ->where(fn ($q) => $q->whereNull('p.is_deleted')->orWhere('p.is_deleted', 0))
            ->select(['p.id', 'p.op_serial_number', 'p.op_type', 'p.Grantee', 'p.plot_no', 'p.plot_size', 'p.districtName', 'p.location',
                      'p.property_description', 'p.lpkn_no', 'p.tp_no', 'p.layout', 'p.temp_fileno', 'p.mlsFNo', 'p.regNo']);
    }

    private function fromCapture(object $r): array
    {
        return [
            'key'         => 'capture-' . $r->id,
            'source'      => 'capture',
            'id'          => (int) $r->id,
            'serial'      => $this->s($r->op_serial_number),
            'type'        => $this->s($r->op_type),
            'allottee'    => $this->s($r->party_2_name),
            'phone'       => $this->s($r->party_2_phone),
            'plot_no'     => $this->s($r->plot_number),
            'plot_size'   => $this->s($r->plot_size),
            'district'    => $this->s($r->district),
            'lga'         => $this->s($r->lga),
            'location'    => $this->s($r->property_location) ?? $this->s($r->property_description),
            'layout'      => $this->s($r->lpkn_no) ?? $this->s($r->tp_no),
            'file_number' => $this->s($r->mlsFNo) ?? $this->s($r->temp_fileno),
            'reg_no'      => $this->s($r->registration_number),
            'land_use'    => $this->s($r->land_use),
        ];
    }

    private function fromPra(object $r): array
    {
        return [
            'key'         => 'pra-' . $r->id,
            'source'      => 'pra',
            'id'          => (int) $r->id,
            'serial'      => $this->s($r->op_serial_number),
            'type'        => $this->s($r->op_type),
            'allottee'    => $this->s($r->Grantee),
            'phone'       => null,
            'plot_no'     => $this->s($r->plot_no),
            'plot_size'   => $this->s($r->plot_size),
            'district'    => $this->s($r->districtName),
            'lga'         => null,
            'location'    => $this->s($r->location) ?? $this->s($r->property_description),
            'layout'      => $this->s($r->lpkn_no) ?? $this->s($r->tp_no) ?? $this->s($r->layout),
            'file_number' => $this->s($r->mlsFNo) ?? $this->s($r->temp_fileno),
            'reg_no'      => $this->s($r->regNo),
            'land_use'    => null,
        ];
    }

    /** Trimmed text, or null for blank / placeholder phones. */
    private function s($v): ?string
    {
        $v = trim((string) $v);

        return $v === '' || preg_match('/^0+$/', $v) ? null : $v;
    }
}
