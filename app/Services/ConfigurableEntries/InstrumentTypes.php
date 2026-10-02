<?php

namespace App\Services\ConfigurableEntries;

use App\Models\NewInstrumentType;
use Illuminate\Support\Facades\DB;

// Same intake catalogue as dev, without installing the unrelated intake controller.
class InstrumentTypes
{
    public static function intakeInstrumentTypes(): array
    {
        $excluded = array_map('mb_strtolower', (array) config('instrument_workflow.intake.excluded_instrument_types', []));


        foreach ([
            fn () => NewInstrumentType::query()->orderBy('name')->pluck('name'),
            fn () => DB::connection('sqlsrv')->table('instrument_number_vaults')->orderBy('instrument_type')->pluck('instrument_type'),
        ] as $source) {
            try {
                $types = collect($source())
                    ->map(fn ($name) => trim(preg_replace('/\s+/', ' ', strtok((string) $name, "\r\n") ?: '')))
                    ->filter()
                    ->filter(fn ($name) => self::appliesTo($name) && !in_array(mb_strtolower($name), $excluded, true))
                    ->unique()
                    ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                $types = [];
            }

            if ($types !== []) {
                return $types;
            }
        }

        return ['Deed of Assignment', 'Deed of Gift', 'Deed of Lease', 'Deed of Mortgage', 'Deed of Sub-Lease', 'Power of Attorney'];
    }
    private static function appliesTo(?string $instrumentType): bool
    {
        if (!config('instrument_workflow.bir.gate_enabled', true)) {
            return false;
        }
        foreach ((array) config('instrument_workflow.bir.exempt_instrument_types', []) as $exempt) {
            if ($exempt !== '' && stripos((string) $instrumentType, $exempt) !== false) {
                return false;
            }
        }
        return true;
    }
}

