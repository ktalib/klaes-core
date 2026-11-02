<?php

namespace App\Services\FileTracking;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Units inside a department, for File Movement's "Send to" (CR 30102026 item 34).
 * A unit is optional on a send; "Other" takes a typed name until the unit is added
 * in Configurable Entities > Units. A send between two offices of the same
 * department is In-Unit; between departments it is In Transit, as before.
 */
class FileMovementUnits
{
    public const IN_UNIT = 'in_unit';
    public const IN_TRANSIT = 'in_transit';

    public static function installed(): bool
    {
        static $installed = null;

        return $installed ??= Schema::connection('sqlsrv')->hasTable('file_movement_units');
    }

    /** Every unit, active or not, department then name (Configurable Entities). */
    public static function all(): Collection
    {
        if (!self::installed()) {
            return collect();
        }

        return DB::connection('sqlsrv')->table('file_movement_units')
            ->orderBy('department')->orderBy('name')->get();
    }

    /** Active units for the Send forms: [{id, department, name}]. */
    public static function active(): Collection
    {
        if (!self::installed()) {
            return collect();
        }

        return DB::connection('sqlsrv')->table('file_movement_units')->where('is_active', 1)
            ->orderBy('department')->orderBy('name')->get(['id', 'department', 'name']);
    }

    /**
     * The unit chosen on a send, checked against the department of the office the
     * file goes to. Null when no unit was chosen (it is optional).
     *
     * @return array{id:?int, name:string, other:bool}|null
     */
    public static function choice(?int $id, ?string $other, ?string $department): ?array
    {
        $other = trim(preg_replace('/\s+/', ' ', (string) $other));

        if ($id) {
            $unit = self::installed()
                ? DB::connection('sqlsrv')->table('file_movement_units')->where('id', $id)->where('is_active', 1)->first()
                : null;
            if (!$unit) {
                throw new RuntimeException('That unit is no longer available. Choose another, or Other and specify it.');
            }
            if (mb_strtolower(trim((string) $unit->department)) !== mb_strtolower(trim((string) $department))) {
                throw new RuntimeException("{$unit->name} is a unit of {$unit->department}, not of the department the file is going to.");
            }

            return ['id' => (int) $unit->id, 'name' => $unit->name, 'other' => false];
        }

        return $other !== '' ? ['id' => null, 'name' => $other, 'other' => true] : null;
    }

    /** In-Unit inside one department, In Transit between departments. */
    public static function category(?string $fromDepartment, ?string $toDepartment): string
    {
        $from = mb_strtolower(trim((string) $fromDepartment));

        return $from !== '' && $from === mb_strtolower(trim((string) $toDepartment)) ? self::IN_UNIT : self::IN_TRANSIT;
    }
}
