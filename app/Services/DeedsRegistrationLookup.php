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
 *
 * A consent with no linked registration falls back to one on the same file
 * whose parties match its own (Party 1 = grantor, Party 2 = grantee) and whose
 * instrument fits the consent type: a file can be registered several times,
 * once per dealing, and duplicate consents for one dealing share its
 * registration (user, 2026-11-03).
 */
class DeedsRegistrationLookup
{
    /** Instrument-type words each consent type accepts in the party fallback. */
    private const TYPE_WORDS = [
        'assignment' => ['assignment', 'purchase', 'transfer'],
        'gift' => ['gift', 'assignment'],
        'mortgage' => ['mortgage'],
    ];

    /** Titles and company suffixes ignored when comparing party names. */
    private const NAME_NOISE = [
        'MR', 'MRS', 'MS', 'MISS', 'DR', 'ALH', 'ALHAJI', 'ALHAJA', 'ALHAJJI', 'HAJIYA', 'HAJIA', 'HAJI', 'MALAM', 'MAL',
        'CHIEF', 'PROF', 'ENGR', 'ENG', 'BARR', 'ARC', 'SIR', 'LT', 'COL', 'MAJ', 'GEN', 'CAPT', 'HON',
        'LTD', 'LIMITED', 'PLC', 'NIG', 'NIGERIA', 'NIGERIAN', 'CO', 'COMPANY', 'INC', 'AND', 'THE', 'OF', 'OTHERS',
    ];

    private const REG_COLUMNS = ['dr.id', 'dr.registration_number', 'dr.instrument_type', 'dr.deeds_date',
                                 'dr.volume_no', 'dr.page_no', 'dr.serial_no', 'dr.grantor', 'dr.grantee'];

    /**
     * @param  iterable<object{id:int, file_number:?string, consent_type:?string, applicant_name:?string, party_name:?string}>|null  $consents
     *         consents to try the party fallback on (none when omitted)
     * @return array<int, object{registration_number: string, instrument_type: string}> keyed by consent id
     */
    public function forConsents(?iterable $consents = null): array
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

        return $consents === null ? $map : $this->addPartyMatches($map, $consents);
    }

    /**
     * The same for the given consents only (a page of the Deeds Applications table), with the
     * registration date and volume / page / serial (user, 2026-11-02: shown under Consent Type).
     *
     * @param  array<int>  $consentIds
     * @return array<int, object> keyed by consent id
     */
    public function forConsentIds(array $consentIds): array
    {
        $map = [];
        foreach (array_chunk(array_values(array_unique(array_map('intval', $consentIds))), 1000) as $chunk) {
            $rows = DB::connection('sqlsrv')->table('instrument_capture as ic')
                ->join('deed_registrations as dr', 'dr.instrument_capture_id', '=', 'ic.id')
                ->whereIn('ic.consent_application_id', $chunk)
                ->where(fn ($q) => $q->whereNull('dr.is_deleted')->orWhere('dr.is_deleted', 0))
                ->orderBy('dr.id')
                ->get(['ic.consent_application_id', 'dr.registration_number', 'dr.instrument_type', 'dr.deeds_date',
                       'dr.volume_no', 'dr.page_no', 'dr.serial_no']);
            foreach ($rows as $row) {
                $map[(int) $row->consent_application_id] = $row; // later rows overwrite: latest wins
            }
        }

        $missing = array_values(array_diff(array_map('intval', $consentIds), array_keys($map)));
        if ($missing) {
            $consents = collect();
            foreach (array_chunk($missing, 1000) as $chunk) {
                $consents = $consents->concat(DB::connection('sqlsrv')->table('consent_applications')->whereIn('id', $chunk)
                    ->get(['id', 'file_number', 'consent_type', 'applicant_name', 'party_name']));
            }
            $map = $this->addPartyMatches($map, $consents);
        }

        return $map;
    }

    /**
     * Fills in consents that have no linked registration from a registration on the same file
     * (deed_registrations.fileno, or any file-number column of its instrument capture) with a
     * fitting instrument type and matching parties. The latest such registration wins.
     *
     * @param  array<int, object>  $map  registrations already found, keyed by consent id
     * @param  iterable<object>  $consents
     * @return array<int, object>
     */
    private function addPartyMatches(array $map, iterable $consents): array
    {
        $byFile = [];
        foreach ($consents as $consent) {
            $file = strtoupper(trim((string) $consent->file_number));
            if ($file !== '' && !isset($map[(int) $consent->id])) {
                $byFile[$file][] = $consent;
            }
        }
        if (!$byFile) {
            return $map;
        }

        $live = fn ($q) => $q->whereNull('dr.is_deleted')->orWhere('dr.is_deleted', 0);
        $regsByFile = [];
        foreach (array_chunk(array_keys($byFile), 500) as $chunk) {
            $direct = DB::connection('sqlsrv')->table('deed_registrations as dr')
                ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(dr.fileno)))'), $chunk)
                ->where($live)
                ->get(array_merge(self::REG_COLUMNS, [DB::raw('UPPER(LTRIM(RTRIM(dr.fileno))) as match_file')]));
            // A file can sit in any number column of the capture (several fileNumber columns per file).
            $viaCapture = collect();
            foreach (['ic.mlsFNo', 'ic.kangisFileNo', 'ic.NewKANGISFileno'] as $column) {
                $viaCapture = $viaCapture->concat(DB::connection('sqlsrv')->table('deed_registrations as dr')
                    ->join('instrument_capture as ic', 'ic.id', '=', 'dr.instrument_capture_id')
                    ->whereIn(DB::raw("UPPER(LTRIM(RTRIM($column)))"), $chunk)
                    ->where($live)
                    ->get(array_merge(self::REG_COLUMNS, [DB::raw("UPPER(LTRIM(RTRIM($column))) as match_file")])));
            }
            foreach ($direct->concat($viaCapture) as $reg) {
                $regsByFile[$reg->match_file][(int) $reg->id] = $reg;
            }
        }

        foreach ($byFile as $file => $fileConsents) {
            $regs = $regsByFile[$file] ?? [];
            krsort($regs); // latest first
            foreach ($fileConsents as $consent) {
                foreach ($regs as $reg) {
                    if ($this->typeFits($consent->consent_type, $reg->instrument_type)
                        && $this->namesMatch($consent->applicant_name, $reg->grantor)
                        && $this->namesMatch($consent->party_name, $reg->grantee)) {
                        $map[(int) $consent->id] = $reg;
                        break;
                    }
                }
            }
        }

        return $map;
    }

    private function typeFits(?string $consentType, ?string $instrumentType): bool
    {
        $consentType = strtolower(trim((string) $consentType));
        $instrumentType = strtolower((string) $instrumentType);
        if ($consentType === '' || str_contains($instrumentType, 'fragmentation')) {
            return false;
        }

        foreach (self::TYPE_WORDS[$consentType] ?? [$consentType] as $word) {
            if (str_contains($instrumentType, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether two party names are the same person or company, allowing for titles, punctuation,
     * word order and small spelling slips (RABI'U / RABIU, LTD / LIMITED, IFEANYI / IFENYI).
     */
    private function namesMatch(?string $a, ?string $b): bool
    {
        $ta = $this->nameTokens($a);
        $tb = $this->nameTokens($b);
        if (!$ta || !$tb) {
            return false;
        }

        [$small, $large] = count($ta) <= count($tb) ? [$ta, $tb] : [$tb, $ta];
        $hits = 0;
        foreach ($small as $token) {
            foreach ($large as $i => $other) {
                // Up to 30% of the longer word may differ (ABDULLAHI / ABDALLAH); words under 4 letters must be exact.
                if ($token === $other || (min(strlen($token), strlen($other)) >= 4
                        && levenshtein($token, $other) <= (int) floor(max(strlen($token), strlen($other)) * 0.3))) {
                    $hits++;
                    unset($large[$i]);
                    break;
                }
            }
        }

        $needed = count($small) === 1 ? 1 : max(2, (int) ceil(count($small) * 0.6));

        return $hits >= $needed;
    }

    /** @return array<string> */
    private function nameTokens(?string $name): array
    {
        $name = str_replace(["'", '’', '`'], '', strtoupper((string) $name));
        $tokens = preg_split('/[^A-Z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($tokens, fn ($t) => strlen($t) > 1 && !in_array($t, self::NAME_NOISE, true)));
    }

    /**
     * ST Assignment registrations, which are made unit by unit: deed_registrations.fileno is the
     * unit's file number (e.g. ST-RES-2026-4-001), parent_fileno the mother's (user, 2026-11-02).
     *
     * @param  array<string>  $unitFileNumbers
     * @return array<string, object> keyed by the upper-cased unit file number
     */
    public function forStUnits(array $unitFileNumbers): array
    {
        $files = array_values(array_unique(array_filter(array_map(fn ($f) => strtoupper(trim((string) $f)), $unitFileNumbers))));
        $map = [];
        foreach (array_chunk($files, 1000) as $chunk) {
            $rows = DB::connection('sqlsrv')->table('deed_registrations')
                ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(fileno)))'), $chunk)
                ->where('instrument_type', 'like', 'ST Assignment%')
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderBy('id')
                ->get(['fileno', 'registration_number', 'instrument_type', 'deeds_date']);
            foreach ($rows as $row) {
                $map[strtoupper(trim((string) $row->fileno))] = $row; // latest wins
            }
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
