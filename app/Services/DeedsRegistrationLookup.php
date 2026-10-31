<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * The registration a consent or a valuation ended in, for the "Reg" line shown
 * under the File No on the Applications for Consent and Valuation tables.
 *
 * A consent is registered when an instrument capture names it
 * (instrument_capture.consent_application_id) and that capture produced a
 * deed_registrations row. A valuation reaches a registration through the
 * consent billed on it (consent_applications.valuation_report_id), falling
 * back to a consent on the same file number - most valuations predate that
 * link. The latest registration wins when there are several.
 */
class DeedsRegistrationLookup
{
    /**
     * @return array<int, object{registration_number: string, instrument_type: string}> keyed by consent id
     */
    public function forConsents(): array
    {
        $rows = DB::connection('sqlsrv')->table('instrument_capture as ic')
            ->join('deed_registrations as dr', 'dr.instrument_capture_id', '=', 'ic.id')
            ->whereNotNull('ic.consent_application_id')
            ->where(fn ($q) => $q->whereNull('dr.is_deleted')->orWhere('dr.is_deleted', 0))
            ->orderBy('dr.id')
            ->get(['ic.consent_application_id', 'dr.registration_number', 'dr.instrument_type']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->consent_application_id] = $row; // later rows overwrite: latest wins
        }

        return $map;
    }

    /**
     * @param  iterable<object{id:int, file_number:?string}>  $reports
     * @return array<int, object{registration_number: string, instrument_type: string}> keyed by valuation report id
     */
    public function forValuations(iterable $reports): array
    {
        $ids = [];
        $files = [];
        foreach ($reports as $report) {
            $ids[] = (int) $report->id;
            $file = strtoupper(trim((string) $report->file_number));
            if ($file !== '') {
                $files[$file][] = (int) $report->id;
            }
        }

        if (!$ids) {
            return [];
        }

        $rows = DB::connection('sqlsrv')->table('consent_applications as ca')
            ->join('instrument_capture as ic', 'ic.consent_application_id', '=', 'ca.id')
            ->join('deed_registrations as dr', 'dr.instrument_capture_id', '=', 'ic.id')
            ->where(function ($q) use ($ids, $files) {
                $q->whereIn('ca.valuation_report_id', $ids);
                if ($files) {
                    $q->orWhereIn(DB::raw('UPPER(LTRIM(RTRIM(ca.file_number)))'), array_keys($files));
                }
            })
            ->where(fn ($q) => $q->whereNull('dr.is_deleted')->orWhere('dr.is_deleted', 0))
            ->orderBy('dr.id')
            ->get(['ca.valuation_report_id', 'ca.file_number', 'dr.registration_number', 'dr.instrument_type']);

        $direct = [];
        $byFile = [];
        foreach ($rows as $row) {
            if ($row->valuation_report_id !== null && in_array((int) $row->valuation_report_id, $ids, true)) {
                $direct[(int) $row->valuation_report_id] = $row;
            }
            $byFile[strtoupper(trim((string) $row->file_number))] = $row;
        }

        $map = [];
        foreach ($files as $file => $reportIds) {
            foreach ($reportIds as $id) {
                if (isset($direct[$id])) {
                    $map[$id] = $direct[$id];
                } elseif (isset($byFile[$file])) {
                    $map[$id] = $byFile[$file];
                }
            }
        }

        return $map + $direct;
    }
}
