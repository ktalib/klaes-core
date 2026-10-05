<?php

namespace App\Services;

use App\Support\OpSerial;

/** Pure, conservative planner. No writes and no prop_id-only matching. */
class OpSerialRepairPlanner
{
    private array $rows = [];
    private array $mothersByFile = [];
    private array $reverseCapture = [];
    private array $commissions = [];

    public function __construct(array $tables, array $commissions = [])
    {
        foreach ($tables as $table => $rows) {
            foreach ($rows as $row) {
                $key = $table.':'.$row['id'];
                $this->rows[$key] = ['table' => $table, 'id' => $row['id'], 'row' => $row];
                if (!empty($row['is_deleted']) || !OpSerial::isMother($row)) continue;
                foreach (self::files($row) as $file) $this->mothersByFile[$file][$key] = $this->rows[$key];
                if ($table === 'pra' && !empty($row['instrument_capture_id'])) {
                    $this->reverseCapture[(string) $row['instrument_capture_id']][$key] = $this->rows[$key];
                }
            }
        }
        foreach ($commissions as $row) {
            $file = strtoupper(trim((string) ($row['full_file_number'] ?? '')));
            if ($file !== '') $this->commissions[$file][] = $row;
        }
    }

    public static function files(array $row): array
    {
        $values = [];
        foreach (['file_no', 'mlsFNo', 'fileno', 'temp_fileno', 'resolved_fileno', 'kangisFileNo', 'NewKANGISFileno'] as $field) {
            $file = strtoupper(trim((string) ($row[$field] ?? '')));
            if ($file !== '') $values[$file] = $file;
        }
        return array_values($values);
    }

    private function linked(array $row, array $visited = []): array
    {
        $links = [];
        if (!empty($row['source_op_id'])) $links[] = [$row['source_op_table'] ?? '', $row['source_op_id']];
        foreach (['source_pra_id' => 'pra', 'source_instrument_capture_id' => 'instrument_capture', 'instrument_capture_id' => 'instrument_capture'] as $field => $table) {
            if (!empty($row[$field])) $links[] = [$table, $row[$field]];
        }
        $sources = [];
        foreach ($links as [$table, $id]) {
            $key = $table.':'.$id;
            if (isset($visited[$key])) continue;
            $visited[$key] = true;
            $source = $this->rows[$key] ?? null;
            if (!$source || !empty($source['row']['is_deleted'])) continue;
            if (OpSerial::isMother($source['row'])) $sources[$key] = $source;
            else $sources += $this->linked($source['row'], $visited);
        }
        return $sources;
    }

    private static function sameParticulars(array $left, array $right): bool
    {
        foreach ([['serialNo', 'serial_no'], ['pageNo', 'page_no'], ['volumeNo', 'volume_no'], ['Grantee', 'party_2', 'party_2_name']] as $fields) {
            $pick = static function (array $row) use ($fields): string {
                foreach ($fields as $field) {
                    $value = strtoupper(trim((string) ($row[$field] ?? '')));
                    if ($value !== '' && $value !== '0') return $value;
                }
                return '';
            };
            if ($pick($left) === '' || $pick($left) !== $pick($right)) return false;
        }
        return true;
    }

    public function plan(string $table, array $target): array
    {
        $current = $target['op_serial_number'] ?? null;
        $result = ['table' => $table, 'id' => $target['id'], 'files' => self::files($target),
            'before' => $current, 'proposed' => null, 'source' => null, 'basis' => null, 'status' => 'no_source'];
        if (!OpSerial::isMother($target) && !empty($target['merger_group_id'])) {
            $result['status'] = 'merger_requires_review';
            return $result;
        }
        $sources = $this->linked($target);
        $basis = 'explicit_source_link';
        $hasLink = !empty($target['source_op_id']) || !empty($target['source_pra_id'])
            || !empty($target['source_instrument_capture_id']) || !empty($target['instrument_capture_id']);
        if (!$sources && $table === 'oss_applications') {
            foreach (self::files($target) as $file) {
                foreach ($this->commissions[$file] ?? [] as $commission) $sources += $this->linked($commission);
            }
            $basis = 'commission_source_link';
        }
        if (!$sources && $table === 'instrument_capture') {
            $sources = $this->reverseCapture[(string) $target['id']] ?? [];
            $basis = 'pra_capture_link';
        }
        if (!$sources && !$hasLink) {
            foreach (self::files($target) as $file) {
                foreach ($this->mothersByFile[$file] ?? [] as $key => $source) {
                    if ($key === $table.':'.$target['id']) continue;
                    if ($table === 'oss_applications' && $source['table'] === 'pra') {
                        $sources[$key] = $source;
                    } elseif (OpSerial::isMother($target) && self::sameParticulars($target, $source['row'])) {
                        $sources[$key] = $source;
                    } elseif (!OpSerial::isMother($target) && $table === 'pra' && $source['table'] === 'pra') {
                        $holder = strtoupper(trim((string) ($target['Grantor'] ?? $target['party_1'] ?? '')));
                        $allottee = strtoupper(trim((string) ($source['row']['Grantee'] ?? $source['row']['party_2'] ?? '')));
                        if ($holder !== '' && $holder === $allottee && !empty($target['prop_id'])
                            && (string) $target['prop_id'] === (string) ($source['row']['prop_id'] ?? '')) $sources[$key] = $source;
                    }
                }
            }
            $basis = $table === 'oss_applications' ? 'unique_pra_op_for_exact_file' : 'exact_file_and_particulars';
        }
        if (!$sources) {
            $result['status'] = OpSerial::valid($current) ? 'valid_unlinked' : ($hasLink ? 'broken_source_link' : 'no_source');
            return $result;
        }
        $serials = array_unique(array_map(fn ($source) => trim((string) ($source['row']['op_serial_number'] ?? '')), $sources));
        if (count($sources) > 1 && ($basis !== 'explicit_source_link' && $basis !== 'commission_source_link' || count($serials) !== 1)) {
            $result['status'] = 'ambiguous';
            return $result;
        }
        $source = reset($sources);
        $serial = reset($serials);
        $result['source'] = ['table' => $source['table'], 'id' => $source['id']];
        $result['basis'] = $basis;
        if (!OpSerial::valid($serial)) { $result['status'] = 'source_missing_or_invalid'; return $result; }
        $result['proposed'] = $serial;
        if (OpSerial::valid($current)) $result['status'] = trim((string) $current) === $serial ? 'consistent' : 'conflict';
        elseif ($current === null || trim((string) $current) === '' || trim((string) $current) === '0') $result['status'] = 'safe';
        else $result['status'] = 'invalid_requires_review';
        return $result;
    }
}
