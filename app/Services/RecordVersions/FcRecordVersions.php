<?php

namespace App\Services\RecordVersions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Record State Versions for the FC (File Commissioning) table.
 *
 * The FC Edit modal overwrites the record in place, so nothing kept what it said
 * before. capture() reads the row's fields as the FC list shows them before the
 * save; record() runs after the save has committed and writes a version when a
 * listed field actually changed. Version 1 is the record before its first edit,
 * so the first edit after go-live writes two rows.
 *
 * Best effort, like FileSnapshotService: a failure here is logged and never
 * turns a successful edit into an error.
 */
class FcRecordVersions
{
    public const TABLE = 'fc_record_versions';

    public const FILE_NUMBER = 'file_number';
    public const TEMPORARY = 'temporary';
    public const PLOT_EXTENSION = 'plot_extension';

    /** Bookkeeping fields: kept on each version but never a change on their own. */
    private const STAMP_FIELDS = ['updated_by', 'updated_at'];

    private ?bool $tableExists = null;

    public function available(): bool
    {
        if ($this->tableExists === null) {
            try {
                $this->tableExists = Schema::connection('sqlsrv')->hasTable(self::TABLE);
            } catch (\Throwable $e) {
                $this->tableExists = false;
            }
        }

        return $this->tableExists;
    }

    /**
     * The record's fields, keyed and formatted like the FC list row (FileNumberController::getData).
     */
    public function capture(string $entity, int $id): ?array
    {
        try {
            $db = DB::connection('sqlsrv');

            if ($entity === self::FILE_NUMBER) {
                $fn = $db->table('fileNumber')->where('id', $id)->first();
                if (!$fn) {
                    return null;
                }
                $mls = trim((string) ($fn->mlsfNo ?? '')) === '' ? null : $db->table('mls_file_no')
                    ->where('full_file_number', $fn->mlsfNo)
                    ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                    ->orderByDesc('id')
                    ->first();

                return [
                    'mlsfNo' => $this->text($fn->mlsfNo ?? null),
                    'kangisFileNo' => $this->text($fn->kangisFileNo ?? null),
                    'NewKANGISFileNo' => $this->text($fn->NewKANGISFileNo ?? null),
                    'FileName' => $this->text($fn->FileName ?? null),
                    'land_use' => $this->text($mls->land_use ?? null),
                    'customer_type' => $this->text($mls->customer_type ?? null),
                    'purpose_name' => $this->purposeName($mls->purpose_id ?? null),
                    'plot_no' => $this->text($fn->plot_no ?? null),
                    'tp_no' => $this->text($fn->tp_no ?? null),
                    'location' => $this->text($fn->location ?? null),
                    'lga' => $this->text(($fn->lga ?? null) ?: ($mls->lga ?? null)),
                    'district' => $this->text(($fn->district ?? null) ?: ($mls->district ?? null)),
                    'updated_by' => $this->text($fn->updated_by ?? null),
                    'updated_at' => $this->stamp($fn->updated_at ?? null),
                    '_created_by' => trim((string) (($mls->created_by ?? null) ?: ($fn->created_by ?? ''))),
                    '_created_at' => ($mls->created_at ?? null) ?: ($fn->created_at ?? null),
                ];
            }

            $table = $entity === self::TEMPORARY ? 'mls_file_no' : ($entity === self::PLOT_EXTENSION ? 'plot_extensions' : null);
            if ($table === null) {
                return null;
            }

            $row = $db->table($table)->where('id', $id)->first();
            if (!$row) {
                return null;
            }
            $fileNo = $entity === self::TEMPORARY
                ? ($row->full_file_number ?? null)
                : (($row->new_file_no ?? null) ?: ($row->original_file_no ?? null));

            return [
                'mlsfNo' => $this->text($fileNo),
                'FileName' => $this->text($row->file_name ?? null),
                'land_use' => $this->text($row->land_use ?? null),
                'customer_type' => $this->text($row->customer_type ?? null),
                'purpose_name' => $this->purposeName($row->purpose_id ?? null),
                'plot_no' => $this->text($row->plot_no ?? null),
                'tp_no' => $this->text($row->tp_no ?? null),
                'location' => $this->text($row->location ?? null),
                'lga' => $this->text($row->lga ?? null),
                'district' => $this->text($row->district ?? null),
                'updated_by' => $this->text($row->updated_by ?? null),
                'updated_at' => $this->stamp($row->updated_at ?? null),
                '_created_by' => trim((string) ($row->created_by ?? '')),
                '_created_at' => $row->created_at ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('FcRecordVersions::capture failed', ['entity' => $entity, 'id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Write the version(s) for an edit that has committed. $before is capture() from before the save.
     */
    public function record(string $entity, int $id, ?array $before): void
    {
        if (!$this->available() || $before === null) {
            return;
        }

        try {
            $after = $this->capture($entity, $id);
            if ($after === null) {
                return;
            }

            $db = DB::connection('sqlsrv');
            $latest = $db->table(self::TABLE)
                ->where('entity', $entity)->where('record_id', $id)
                ->orderByDesc('version')->first();

            $previous = $latest ? (json_decode($latest->state, true) ?: []) : $this->stateOnly($before);
            $changed = $this->changedFields($previous, $after);
            if (empty($changed)) {
                return;
            }

            $version = $latest ? (int) $latest->version : 0;
            $fileNumber = $after['mlsfNo'] !== '' ? $after['mlsfNo'] : ($before['mlsfNo'] ?? null);

            if (!$latest) {
                // Version 1: the record as it stood before its first edit, dated from
                // when it was commissioned and credited to whoever commissioned it.
                $db->table(self::TABLE)->insert([
                    'entity' => $entity,
                    'record_id' => $id,
                    'file_number' => $this->clip($fileNumber),
                    'version' => ++$version,
                    'state' => json_encode($this->stateOnly($before), JSON_UNESCAPED_UNICODE),
                    'changed_fields' => null,
                    'edited_by' => null,
                    'edited_by_name' => $this->clip($this->creatorName($before['_created_by'] ?? '')),
                    'created_at' => $this->date($before['_created_at'] ?? null),
                ]);
            }

            $user = Auth::user();
            $db->table(self::TABLE)->insert([
                'entity' => $entity,
                'record_id' => $id,
                'file_number' => $this->clip($fileNumber),
                'version' => $version + 1,
                'state' => json_encode($this->stateOnly($after), JSON_UNESCAPED_UNICODE),
                'changed_fields' => json_encode($changed),
                'edited_by' => $user && is_numeric($user->id) ? (int) $user->id : null,
                'edited_by_name' => $this->clip($user ? $this->userName($user) : null),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('FcRecordVersions::record failed', ['entity' => $entity, 'id' => $id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Latest version number per record, for the rows on one page of the list.
     *
     * @param  array<string, int[]> $idsByEntity
     * @return array<string, array<int, int>> entity => [record_id => version]
     */
    public function counts(array $idsByEntity): array
    {
        $out = [];
        if (!$this->available()) {
            return $out;
        }

        try {
            foreach ($idsByEntity as $entity => $ids) {
                $ids = array_values(array_unique(array_map('intval', $ids)));
                if (empty($ids)) {
                    continue;
                }
                $out[$entity] = DB::connection('sqlsrv')->table(self::TABLE)
                    ->where('entity', $entity)
                    ->whereIn('record_id', $ids)
                    ->groupBy('record_id')
                    ->selectRaw('record_id, MAX(version) AS v')
                    ->pluck('v', 'record_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
            }
        } catch (\Throwable $e) {
            Log::warning('FcRecordVersions::counts failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    /** Every version of one record, oldest first. */
    public function versions(string $entity, int $id): array
    {
        if (!$this->available()) {
            return [];
        }

        return DB::connection('sqlsrv')->table(self::TABLE)
            ->where('entity', $entity)->where('record_id', $id)
            ->orderBy('version')
            ->get()
            ->map(fn ($row) => [
                'version' => (int) $row->version,
                'state' => $this->display(json_decode($row->state, true) ?: []),
                'changed_fields' => json_decode((string) $row->changed_fields, true) ?: [],
                'is_original' => (int) $row->version === 1,
                'by' => $row->edited_by_name ?: null,
                'at' => $row->created_at ? Carbon::parse($row->created_at)->format('Y-m-d H:i') : null,
            ])
            ->all();
    }

    /** Stored text to what the FC list prints: blanks become 'N/A', as in getData(). */
    private function display(array $state): array
    {
        foreach ($state as $key => $value) {
            $state[$key] = ($value === null || $value === '') ? 'N/A' : $value;
        }

        return $state;
    }

    private function changedFields(array $previous, array $current): array
    {
        $changed = [];
        foreach ($this->stateOnly($current) as $key => $value) {
            if (in_array($key, self::STAMP_FIELDS, true)) {
                continue;
            }
            if ((string) ($previous[$key] ?? '') !== (string) $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /** Drop the underscore-prefixed working fields (who/when it was created). */
    private function stateOnly(array $state): array
    {
        return array_filter($state, fn ($key) => $key[0] !== '_', ARRAY_FILTER_USE_KEY);
    }

    private function purposeName($purposeId): string
    {
        if (!is_numeric($purposeId)) {
            return '';
        }

        return $this->text(DB::connection('sqlsrv')->table('purposes')->where('id', (int) $purposeId)->value('name'));
    }

    /** created_by holds a name on most rows and a users.id on some. */
    private function creatorName(string $createdBy): ?string
    {
        if ($createdBy === '') {
            return null;
        }
        if (ctype_digit($createdBy)) {
            $user = DB::connection('sqlsrv')->table('users')->where('id', (int) $createdBy)->first(['first_name', 'last_name', 'username']);
            if ($user) {
                return trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->username ?? $createdBy);
            }
        }

        return $createdBy;
    }

    private function userName($user): ?string
    {
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $name !== '' ? $name : ($user->name ?? $user->email ?? null);
    }

    private function text($value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function stamp($value): string
    {
        if (empty($value)) {
            return '';
        }
        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function date($value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function clip(?string $value, int $max = 255): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $max);
    }
}
