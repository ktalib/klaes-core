<?php

namespace App\Http\Controllers;

use App\Models\DuplicateFileno;
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

        if ($fileNumber !== '') {
            $indexed = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->select('file_number', 'file_title', 'plot_number', 'location', 'created_at')
                ->where('file_number', $fileNumber)
                ->orderBy('id')
                ->get();

            $duplicates = DuplicateFileno::on('sqlsrv')
                ->where('file_number', $fileNumber)
                ->orderBy('id')
                ->get();

            foreach ($indexed as $record) {
                $files[] = $this->normalize($record, 'indexed');
            }

            foreach ($duplicates as $record) {
                $files[] = $this->normalize($record, 'duplicate');
            }
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
    private function normalize($record, string $kind): array
    {
        $dateLabel = $kind === 'indexed' ? 'Date Indexed' : 'Date Captured';

        if (!$record) {
            return [
                'found' => false,
                'kind' => $kind,
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
            'date_label' => $dateLabel,
            'file_no' => $value($record->file_number ?? null),
            'file_title' => $value($record->file_title ?? null),
            'plot_number' => $value($record->plot_number ?? null),
            'location' => $value($record->location ?? null),
            'date_indexed' => $date,
        ];
    }
}
