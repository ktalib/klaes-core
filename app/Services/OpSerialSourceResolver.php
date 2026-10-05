<?php

namespace App\Services;

use App\Support\OpSerial;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpSerialSourceResolver
{
    public function guard(array $input, array $existing = []): array
    {
        $effective = array_replace($existing, $input);
        if (OpSerial::isOp($effective) && !OpSerial::isMother($effective) && !empty($effective['merger_group_id'])) {
            $serial = $this->mergerSerial((string) $effective['merger_group_id']);
            if (array_key_exists('op_serial_number', $input)) {
                $parts = array_map('trim', explode(',', (string) $input['op_serial_number']));
                $expected = array_map('trim', explode(',', $serial));
                sort($parts, SORT_NATURAL); sort($expected, SORT_NATURAL);
                if ($parts !== $expected) throw ValidationException::withMessages(['op_serial_number' => 'The merger serials must agree with every source OP. Correct the individual OP cards.']);
            }
            $input['op_serial_number'] = $serial;
            return $input;
        }
        if (OpSerial::isOp($effective) && !array_key_exists('op_serial_number', $input)
            && !OpSerial::valid($effective['op_serial_number'] ?? null)) {
            $source = $this->source($effective);
            if ($source) $input['op_serial_number'] = $source['row']['op_serial_number'];
        }
        $input = OpSerial::guard($input, $existing);
        if (OpSerial::isOp($effective) && !OpSerial::isMother($effective)
            && (!empty($effective['source_op_id']) || !empty($effective['source_pra_id']) || !empty($effective['instrument_capture_id']))) {
            $input['op_serial_number'] = $this->serial($effective, $input['op_serial_number']);
        }
        return $input;
    }

    public function mergerSerial(string $group, ?array $priorSource = null): string
    {
        $sources = DB::connection('sqlsrv')->table('pra')->where('merger_group_id', $group)->where('is_merger_op', 1)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))->lockForUpdate()->get();
        if ($sources->count() < 2) throw ValidationException::withMessages(['op_serial_number' => 'The merger must identify all originating OP records.']);
        $serials = [];
        foreach ($sources as $source) {
            if (!OpSerial::isMother((array) $source)) throw ValidationException::withMessages(['op_serial_number' => 'A merger source is not an Occupancy Permit.']);
            $value = $priorSource && (string) $source->id === (string) $priorSource['id'] ? $priorSource['serial'] : $source->op_serial_number;
            $serials[] = OpSerial::require($value);
        }
        $serials = array_unique($serials); sort($serials, SORT_NATURAL);
        return implode(', ', $serials);
    }

    public function source(array $row): ?array
    {
        $links = [];
        if (!empty($row['source_op_id']) && in_array($row['source_op_table'] ?? '', ['pra', 'instrument_capture'], true)) {
            $links[] = [$row['source_op_table'], $row['source_op_id']];
        }
        foreach (['source_pra_id' => 'pra', 'source_instrument_capture_id' => 'instrument_capture', 'instrument_capture_id' => 'instrument_capture'] as $key => $table) {
            if (!empty($row[$key])) $links[] = [$table, $row[$key]];
        }
        $found = [];
        foreach ($links as [$table, $id]) {
            $source = DB::connection('sqlsrv')->table($table)->where('id', $id)->first();
            if ($source && empty($source->is_deleted) && !OpSerial::isMother((array) $source) && OpSerial::isOp((array) $source)) {
                if (!empty($source->merger_group_id)) throw ValidationException::withMessages(['op_serial_number' => 'Select the individual source OP; this transfer combines several permits.']);
                // Commissioning may select an existing ToT. Follow its pinned mother,
                // rather than using the ToT's reused prop_id or its own serial.
                $motherTable = $source->source_op_table ?? null;
                $motherId = $source->source_op_id ?? null;
                if (in_array($motherTable, ['pra', 'instrument_capture'], true) && $motherId) {
                    $table = $motherTable;
                    $id = $motherId;
                    $source = DB::connection('sqlsrv')->table($table)->where('id', $id)->first();
                }
            }
            if (!$source || !empty($source->is_deleted) || !OpSerial::isMother((array) $source)) {
                throw ValidationException::withMessages(['op_serial_number' => 'The selected source is not an active Occupancy Permit. Select its exact OP record.']);
            }
            $found[$table.':'.$id] = ['table' => $table, 'id' => $id, 'row' => (array) $source];
        }
        if (!$found) return null;
        $serials = array_unique(array_map(fn ($source) => OpSerial::require($source['row']['op_serial_number'] ?? null), $found));
        if (count($serials) !== 1) {
            throw ValidationException::withMessages(['op_serial_number' => 'The linked OP records have conflicting serial numbers. Correct the source links first.']);
        }
        return reset($found);
    }

    public function serial(array $row, $submitted = null): string
    {
        $source = $this->source($row);
        if ($source) {
            $serial = OpSerial::require($source['row']['op_serial_number'] ?? null);
            if ($submitted !== null && trim((string) $submitted) !== $serial) {
                throw ValidationException::withMessages(['op_serial_number' => 'OP Serial Number does not match the selected source OP. Correct the source OP before commissioning.']);
            }
            return $serial;
        }
        return OpSerial::require($submitted ?? $row['op_serial_number'] ?? null);
    }
}
