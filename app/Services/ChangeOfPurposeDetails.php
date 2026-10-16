<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ChangeOfPurposeDetails
{
    public function indexing(string $file): array
    {
        $row = DB::connection('sqlsrv')->table('file_indexings')
            ->where('file_number', trim($file))->orderByDesc('id')->first();
        return self::indexingFields((array) $row);
    }

    public static function indexingFields(array $row): array
    {
        $result = [];
        foreach (['applicant_name' => 'file_title', 'plot_no' => 'plot_number',
            'tp_no' => 'tp_no', 'street_name' => 'street_name', 'house_no' => 'house_no',
            'district' => 'district', 'lga' => 'lga', 'location' => 'location',
            'phone' => 'phone', 'residential_address' => 'residence_address',
            'latitude' => 'latitude', 'longitude' => 'longitude', 'land_size' => 'plot_size'] as $key => $column) {
            $result[$key] = $row[$column] ?? null;
        }
        return $result;
    }

    /**
     * The file's current purpose (e.g. HIGH DENSITY, COMMERCIAL (SHOPS)). Commissioning's
     * purposes row is the specific answer; indexing mostly holds only the land use
     * (RESIDENTIAL), so it is the fallback. Null when neither records one.
     */
    public static function currentPurpose(string $fileNo): ?string
    {
        $fileNo = strtoupper(trim($fileNo));
        if ($fileNo === '') return null;

        $db = DB::connection('sqlsrv');
        $name = $db->table('mls_file_no as m')
            ->join('purposes as p', 'p.id', '=', 'm.purpose_id')
            ->where('m.full_file_number', $fileNo)
            ->orderByDesc('m.id')
            ->value('p.name');

        if (trim((string) $name) === '') {
            $name = $db->table('file_indexings')
                ->where('file_number', $fileNo)
                ->orderByDesc('id')
                ->value('land_use_type');
        }

        $name = trim((string) $name);
        return $name !== '' ? $name : null;
    }

    /** Purpose names compared case- and whitespace-insensitively. */
    public static function samePurpose(?string $a, ?string $b): bool
    {
        $norm = fn ($v) => preg_replace('/\s+/', ' ', strtoupper(trim((string) $v)));
        return $norm($a) !== '' && $norm($a) === $norm($b);
    }

    public static function fillBlanks(array $values, array $fallback): array
    {
        foreach ($fallback as $key => $value) {
            if (trim((string) ($values[$key] ?? '')) === '' && trim((string) ($value ?? '')) !== '') {
                $values[$key] = $value;
            }
        }
        return $values;
    }

    public function application($record): array
    {
        $values = method_exists($record, 'toArray') ? $record->toArray() : (array) $record;
        $values = self::fillBlanks($values, ['tp_no' => $values['plan_no'] ?? null]);
        $values = self::fillBlanks($values, $this->indexing($record->file_no));
        $values['address'] = $values['residential_address'] ?? null;
        $values['phone_no'] = $values['phone'] ?? null;
        return $values;
    }

    /** Recover blanks for a commissioned successor using its recorded lineage only. */
    public function editFallback(object $record): object
    {
        $db = DB::connection('sqlsrv');
        $file = trim((string) ($record->mlsfNo ?? ''));
        if ($file === '') return $record;
        $application = $db->table('change_of_purpose_applications as cop')
            ->join('decommissioned_files as old', 'old.file_no', '=', 'cop.file_no')
            ->where('old.successor_file_no', $file)
            ->where('old.decommissioning_reason', 'like', 'Change of Purpose%')
            ->where('cop.status', 'commissioned')
            ->whereRaw('ISNULL(cop.is_deleted, 0) = 0')
            ->orderByDesc('cop.id')->select('cop.*')->first();
        $fallback = $application ? $this->application($application) : $this->indexing($file);
        $fallback['address'] = $fallback['address'] ?? $fallback['residential_address'] ?? null;
        foreach (['address', 'tp_no', 'plot_no', 'house_no', 'street_name', 'district', 'lga'] as $key) {
            if (trim((string) ($record->$key ?? '')) === '') $record->$key = $fallback[$key] ?? null;
        }
        return $record;
    }
}
