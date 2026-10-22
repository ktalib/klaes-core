<?php

namespace App\Services;

use App\Support\OpSerial;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * System-generated OP serials for an OP that has none (user, 2026-10-16).
 *
 * The serial is six digits, zero-padded (000001, 000002, ...): only the system writes a
 * leading zero, so the number says where it came from. pra.op_serial_generated marks the
 * row, and pra.op_serial_generated_replaced keeps the system serial a real one later
 * replaced. Used by the PRA card, the File Indexing OP section and the Property
 * Transaction card -- the three screens that capture OPs.
 */
class OpSerialGenerator
{
    private const SEQUENCE = 'op_serial';

    /**
     * The next system serial. MUST run inside the caller's sqlsrv transaction: the
     * counter row is bumped under an update lock, so concurrent saves queue for it, and
     * a save that rolls back gives its number back.
     */
    public function next(): string
    {
        $db = DB::connection('sqlsrv');
        if ($db->transactionLevel() < 1) {
            throw new \LogicException('OpSerialGenerator::next() must run inside the save transaction.');
        }

        // A typed serial can never take this shape (they carry no leading zero), but the
        // check is cheap and keeps a hand-made clash from ever being duplicated.
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $row = $db->selectOne(
                'UPDATE op_serial_sequences WITH (UPDLOCK, ROWLOCK)
                    SET last_value = last_value + 1, updated_at = SYSDATETIME()
                 OUTPUT inserted.last_value
                  WHERE name = ?',
                [self::SEQUENCE]
            );
            if (!$row) {
                throw new \RuntimeException('OP serial counter row is missing (op_serial_sequences).');
            }
            $serial = OpSerial::formatGenerated((int) $row->last_value);
            if (!OpSerial::isGeneratedFormat($serial)) {
                throw new \RuntimeException('OP serial counter is exhausted (999999 reached).');
            }
            if (!$this->inUse($serial)) {
                return $serial;
            }
        }

        throw new \RuntimeException('Could not find a free system OP serial.');
    }

    /**
     * The serial the next save would most likely receive, for the screens' preview.
     * Read-only: takes nothing from the counter, so it can be wrong by one or two when
     * other officers save at the same moment -- the real number is set on save.
     */
    public function peekNext(): string
    {
        $last = (int) DB::connection('sqlsrv')->table('op_serial_sequences')->where('name', self::SEQUENCE)->value('last_value');
        for ($n = $last + 1, $tries = 0; $tries < 50; $n++, $tries++) {
            $serial = OpSerial::formatGenerated($n);
            if (!$this->inUse($serial)) {
                return $serial;
            }
        }
        return OpSerial::formatGenerated($last + 1);
    }

    private function inUse(string $serial): bool
    {
        $db = DB::connection('sqlsrv');
        foreach (['pra', 'instrument_capture', 'oss_applications'] as $table) {
            if ($db->table($table)->where('op_serial_number', $serial)->exists()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Work out what to store for one OP being saved.
     *
     * @param bool        $noSerial  the officer ticked "This OP has no serial number"
     * @param mixed       $typed     the serial typed in the box (ignored when $noSerial)
     * @param object|null $existing  the pra row being updated, or null for a new OP
     * @param string      $field     the input name to report errors on
     * @return array{op_serial_number:string, op_serial_generated:int, op_serial_generated_replaced:?string}
     */
    public function resolve(bool $noSerial, $typed, ?object $existing, string $field = 'op_serial_number'): array
    {
        $stored = trim((string) ($existing->op_serial_number ?? ''));
        $storedIsGenerated = $existing && !empty($existing->op_serial_generated) && OpSerial::isGeneratedFormat($stored);
        $replacedBefore = $existing->op_serial_generated_replaced ?? null;

        if ($noSerial) {
            // A real serial on the record is never thrown away by ticking the box.
            if (!$storedIsGenerated && OpSerial::manualValid($stored)) {
                throw ValidationException::withMessages([
                    $field => "This OP already has serial number {$stored}. Untick \"This OP has no serial number\" to keep it.",
                ]);
            }
            // Already has one: keep it. Re-saving must never burn a new number.
            return [
                'op_serial_number' => $storedIsGenerated ? $stored : $this->next(),
                'op_serial_generated' => 1,
                'op_serial_generated_replaced' => $replacedBefore,
            ];
        }

        $typed = trim((string) $typed);
        // Unchanged values are always fine (including an older serial the record holds).
        $unchanged = $existing && $typed !== '' && $typed === $stored;
        if (!$unchanged && !OpSerial::manualValid($typed)) {
            throw ValidationException::withMessages([
                $field => OpSerial::isGeneratedFormat($typed)
                    ? 'Serials starting with 0 are made by the system only. Type the serial without leading zeros, or tick "This OP has no serial number".'
                    : 'OP Serial Number is required and must contain positive digits without leading zeros (for example, 109), or tick "This OP has no serial number".',
            ]);
        }

        // A real serial replacing a system one: keep the system one in its own column.
        return [
            'op_serial_number' => $typed,
            'op_serial_generated' => 0,
            'op_serial_generated_replaced' => $storedIsGenerated && $typed !== $stored ? $stored : $replacedBefore,
        ];
    }
}
