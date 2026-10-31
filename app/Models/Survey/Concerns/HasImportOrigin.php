<?php

namespace App\Models\Survey\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Rows loaded by survey:import-compensation carry the batch in import_ref
 * (e.g. COMP-20261030-1); rows entered in KLAES leave it null. The listings
 * flag imported rows and filter on ?origin=imported|entered.
 */
trait HasImportOrigin
{
    public static function origins(): array
    {
        return ['imported' => 'Imported', 'entered' => 'Entered in KLAES'];
    }

    public function scopeOrigin(Builder $q, ?string $origin): Builder
    {
        return match ($origin) {
            'imported' => $q->whereNotNull($this->qualifyColumn('import_ref')),
            'entered'  => $q->whereNull($this->qualifyColumn('import_ref')),
            default    => $q,
        };
    }

    public function isImported(): bool
    {
        return (string) $this->import_ref !== '';
    }
}
