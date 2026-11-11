<?php

namespace App\Http\Controllers;

use App\Models\DuplicateFileno;
use App\Services\TemporaryFileConditionService;
use App\Services\TemporaryFileNumberResolver;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DuplicateCallupController extends Controller
{
    /**
     * Render a print-ready "Duplicate File Call-up" sheet listing every physical
     * file held under one file number: the indexed records (file_indexings)
     * followed by every flagged duplicate (duplicate_fileno). A file number can
     * carry more than one of either, and the officer has to call up all of them,
     * so the sheet prints one card per record rather than a fixed pair.
     *
     * GET /duplicate-callup?file_number=...&indexed_id=...&duplicate_id=...
     */
    public function sheet(Request $request): View
    {
        $fileNumber = trim((string) $request->query('file_number', ''));
        $indexedId = (int) $request->query('indexed_id', 0);
        $duplicateId = (int) $request->query('duplicate_id', 0);

        // Every entry point passes file_number today; fall back to whichever id
        // we were handed so an id-only link still resolves the whole set.
        if ($fileNumber === '') {
            $fileNumber = $this->resolveFileNumber($indexedId, $duplicateId);
        }

        $files = [];
        $suspectedForgery = false;

        if ($fileNumber !== '') {
            // The main file and its temporary file "X(T)" are called up together,
            // whichever of the two the officer started from (CR 11112026-2, item 1).
            $main = TemporaryFileConditionService::mainNumber($fileNumber) ?? $fileNumber;
            $forms = array_values(array_unique(array_merge([$main], TemporaryFileNumberResolver::tempForms($main))));

            // An indexed temp file may hold its number only in temp_file_no.
            $indexed = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->select('file_number', 'temp_file_no', 'file_title', 'plot_number', 'location', 'created_at')
                ->where(function ($q) use ($forms) {
                    $q->whereIn('file_number', $forms)
                        ->orWhereIn('temp_file_no', array_slice($forms, 1));
                })
                ->orderBy('id')
                ->get();

            $duplicates = DuplicateFileno::on('sqlsrv')
                ->whereIn('file_number', $forms)
                ->orderBy('id')
                ->get();

            $mainBase = TemporaryFileNumberResolver::baseKey($main);
            foreach ($indexed as $record) {
                $own = trim((string) $record->file_number);
                $isTemp = $own === '' || TemporaryFileNumberResolver::isTemp($own);
                if ($own === '') {
                    $record->file_number = $record->temp_file_no;
                }
                $files[] = $this->normalize($record, 'indexed', $isTemp ? 'Temporary File (Indexed)' : 'Main File (Indexed)');
            }

            foreach ($duplicates as $record) {
                $isTemp = TemporaryFileNumberResolver::isTemp($record->file_number);
                $files[] = $this->normalize($record, 'duplicate', $isTemp ? 'Temporary File' : 'Duplicate File');
            }

            // Suspected forgery: a temporary file whose title names a different holder
            // from the indexed main file.
            $mainTitles = $indexed
                ->filter(fn ($r) => TemporaryFileNumberResolver::baseKey($r->file_number) === $mainBase
                    && !TemporaryFileNumberResolver::isTemp($r->file_number))
                ->pluck('file_title');
            foreach ($files as &$file) {
                if (!$file['is_temp']) {
                    continue;
                }
                foreach ($mainTitles as $mainTitle) {
                    if (TemporaryFileConditionService::titlesDiffer($file['raw_title'], $mainTitle)) {
                        $file['suspected_forgery'] = true;
                        $suspectedForgery = true;
                    }
                }
            }
            unset($file);
        }

        // Nothing matched at all: keep the old two-card "not found" sheet so a
        // failed lookup still prints a page showing what was searched for.
        if ($files === []) {
            $files = [
                $this->normalize(null, 'indexed'),
                $this->normalize(null, 'duplicate'),
            ];
        }

        return view('duplicate_callup.sheet', [
            'fileNumber' => $fileNumber !== '' ? $fileNumber : '—',
            'generatedAt' => Carbon::now('Africa/Lagos')->format('Y-m-d H:i'),
            'files' => $files,
            'recordCount' => count(array_filter($files, fn ($f) => $f['found'])),
            'suspectedForgery' => $suspectedForgery,
        ]);
    }

    /**
     * Recover the file number from an indexed_id / duplicate_id when the caller
     * did not send one, so every link lands on the same full record set.
     */
    private function resolveFileNumber(int $indexedId, int $duplicateId): string
    {
        if ($indexedId > 0) {
            $value = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $indexedId)
                ->value('file_number');

            if (trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        if ($duplicateId > 0) {
            $value = DuplicateFileno::on('sqlsrv')
                ->where('id', $duplicateId)
                ->value('file_number');

            if (trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * Reduce an indexed/duplicate record to the five sheet fields, mapping
     * empties to an em dash and a missing record to "— not found —". $kind
     * drives the date caption, which differs between the two sources.
     *
     * @param  object|null  $record
     * @return array<string,mixed>
     */
    private function normalize($record, string $kind, ?string $role = null): array
    {
        $dateLabel = $kind === 'indexed' ? 'Date Indexed' : 'Date Captured';

        if (!$record) {
            return [
                'found' => false,
                'kind' => $kind,
                'role' => $role,
                'is_temp' => false,
                'raw_title' => null,
                'suspected_forgery' => false,
                'date_label' => $dateLabel,
                'file_no' => '— not found —',
                'file_title' => '— not found —',
                'plot_number' => '— not found —',
                'location' => '— not found —',
                'date_indexed' => '— not found —',
            ];
        }

        $value = function ($raw) {
            $trimmed = trim((string) ($raw ?? ''));
            return $trimmed === '' ? '—' : $trimmed;
        };

        $date = '—';
        if (!empty($record->created_at)) {
            try {
                $date = Carbon::parse($record->created_at)->format('Y-m-d');
            } catch (\Throwable $e) {
                $date = (string) $record->created_at;
            }
        }

        return [
            'found' => true,
            'kind' => $kind,
            'role' => $role,
            'is_temp' => $role !== null && str_starts_with($role, 'Temporary'),
            'raw_title' => $record->file_title ?? null,
            'suspected_forgery' => false,
            'date_label' => $dateLabel,
            'file_no' => $value($record->file_number ?? null),
            'file_title' => $value($record->file_title ?? null),
            'plot_number' => $value($record->plot_number ?? null),
            'location' => $value($record->location ?? null),
            'date_indexed' => $date,
        ];
    }
}
