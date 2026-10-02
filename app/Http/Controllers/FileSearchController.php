<?php

namespace App\Http\Controllers;

use App\Models\DuplicateFileno;
use App\Services\CsvImporter\Support\CsvValueNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileSearchController extends Controller
{
    private const TYPE_DUPLICATE = 'duplicate';
    private const TYPE_TEMPORARY = 'temporary';
    private const TYPE_WRC = 'wrc';
    private const TYPE_COFO_READY = 'cofo_ready';
    private const TYPE_COFO_COLLECTED = 'cofo_collected';
    private const TYPE_DCIV_INVESTIGATION = 'dciv_investigation';

    private const IMPORT_CHUNK_SIZE = 150;

    private const REGISTRY_OPTIONS = [
        'Registry 1 - Land',
        'Registry 2 - Land',
        'Registry 3 - Land',
        'Registry 3_2 - Land',
        'Registry 1 - Deeds',
        'Registry 2 - Deeds',
        'Registry 1 - Cadastral',
        'Registry 2 - Cadastral',
        'KANGIS Registry',
        'SLTR Registry',
        'ST Registry',
        'DCIV Registry',
        'Other',
    ];

    private const TYPE_LABELS = [
        self::TYPE_DUPLICATE => 'Duplicate Files',
        self::TYPE_TEMPORARY => 'Temp Files',
        self::TYPE_WRC => 'W/C/R Files',
        self::TYPE_COFO_READY => 'CofO Ready for Collection',
        self::TYPE_COFO_COLLECTED => 'CofO Already Collected',
        self::TYPE_DCIV_INVESTIGATION => 'For DCIV Investigation',
    ];
 
    private const CATEGORY_LABELS = [
        self::TYPE_DUPLICATE => 'Duplicate Files',
        self::TYPE_TEMPORARY => 'Temp Files',
        self::TYPE_WRC => 'W/C/R Files',
        self::TYPE_COFO_READY => 'CofO Ready for Collection',
        self::TYPE_COFO_COLLECTED => 'CofO Already Collected',
        self::TYPE_DCIV_INVESTIGATION => 'For DCIV Investigation',
    ];

    public function __construct(private CsvValueNormalizer $normalizer)
    {
        $this->middleware(['auth', 'XSS']);
    }

    public function index(): View
    {
        $registryOptions = DuplicateFileno::on('sqlsrv')
            ->select('registry')
            ->whereNotNull('registry')
            ->distinct()
            ->pluck('registry')
            ->filter()
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $this->isValidRegistryOption($value))
            ->unique()
            ->values();

        $registrySelectOptions = Collection::make(self::REGISTRY_OPTIONS)
            ->merge($registryOptions)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $this->isValidRegistryOption($value))
            ->unique()
            ->sort()
            ->values();

        return view('file_search_db.index', [
            'pageTitle' => 'Duplicate File Search',
            'registryOptions' => $registrySelectOptions,
            'registrySelectOptions' => $registrySelectOptions,
            'recordTypeLabels' => self::TYPE_LABELS,
        ]);
    }

    public function apiList(Request $request): JsonResponse
    {
        $perPage = $this->clampPerPage((int) $request->input('per_page', 25));
        $page = max(1, (int) $request->input('page', 1));
        $search = $this->normalizeSearch($request->input('search'));
        $registryFilter = $this->normalizeRegistry($request->input('registry'));
        $typeFilter = $this->normalizeType($request->input('type'));
        $startDate = $this->parseDate($request->input('start_date'));
        $endDate = $this->parseDate($request->input('end_date'), true);

        if ($startDate && $endDate && $startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $query = DuplicateFileno::on('sqlsrv')
            ->select(['id', 'registry', 'file_number', 'file_title', 'plot_number', 'location', 'comment', 'category', 'created_at'])
            ->orderByDesc('created_at')
            ->orderBy('registry')
            ->orderBy('file_number');

        if ($startDate !== null) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->where('created_at', '<=', $endDate);
        }

        if ($registryFilter !== null) {
            $query->where('registry', $registryFilter);
        }

        if ($search !== null) {
            $escapedSearch = $this->escapeLike($search);
            $normalizedSearch = $this->normalizer->normalizeFileNumberForMatch($search);
            $normalizedEscaped = $normalizedSearch ? $this->escapeLike($normalizedSearch) : null;

            $query->where(function ($builder) use ($escapedSearch, $normalizedEscaped) {
                $like = '%' . $escapedSearch . '%';

                $builder->where('file_number', 'like', $like)
                    ->orWhere('file_title', 'like', $like)
                    ->orWhere('plot_number', 'like', $like)
                    ->orWhere('location', 'like', $like)
                    ->orWhere('comment', 'like', $like);

                if ($normalizedEscaped !== null) {
                    $builder->orWhereRaw(
                        "REPLACE(REPLACE(file_number, '-', ''), ' ', '') LIKE ?",
                        ['%' . $normalizedEscaped . '%']
                    );
                }
            });
        }

        if ($typeFilter !== null) {
            $this->applyTypeFilter($query, $typeFilter);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $serialOffset = ($paginator->currentPage() - 1) * $paginator->perPage();

        $timezone = config('app.timezone');
        if (!is_string($timezone) || trim($timezone) === '') {
            $timezone = 'UTC';
        }

        $items = Collection::make($paginator->items())
            ->map(function ($row, int $index) use ($serialOffset, $timezone) {
                [$recordType, $note] = $this->splitComment($row->comment);

                $category = $row->category ?? $this->categoryLabel($recordType);

                $createdAt = $row->created_at ? Carbon::parse($row->created_at) : null;
                if ($createdAt) {
                    $createdAtLocal = $createdAt->copy()->timezone($timezone);
                    $createdAtDisplay = $createdAtLocal->format('Y-m-d H:i:s') . '.' . substr($createdAtLocal->format('u'), 0, 3);
                } else {
                    $createdAtDisplay = null;
                }

                return [
                    'id' => (int) $row->id,
                    'serial' => $serialOffset + $index + 1,
                    'registry' => $row->registry ?? '-',
                    'file_number' => $row->file_number ?? '-',
                    'file_title' => $row->file_title ?? '-',
                    'plot_number' => $row->plot_number ?? '-',
                    'location' => $row->location ?? '-',
                    'comment' => $note,
                    'comment_label' => $recordType,
                    'comment_prefixed' => $row->comment,
                    'record_type' => $recordType,
                    'record_type_label' => $this->typeLabel($recordType),
                    'category' => $category,
                    'comment_display' => $this->commentDisplay($category, $note),
                    'created_at' => $createdAt ? $createdAt->toIso8601String() : null,
                    'created_at_display' => $createdAtDisplay,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'File search results retrieved.',
            'data' => [
                'items' => $items,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'required|string|in:duplicate,temporary,wrc,dciv_investigation',
                'mode' => 'nullable|string|in:append,replace',
                'file' => 'required|file|mimes:csv,txt,tsv|max:20480',
            ]);

            $type = $this->normalizeType($validated['type']) ?? self::TYPE_DUPLICATE;
            $mode = strtolower($validated['mode'] ?? 'append');
            $replace = $mode === 'replace';

            $uploadedFile = $request->file('file');
            if (! $uploadedFile instanceof UploadedFile || ! $uploadedFile->isValid()) {
                throw ValidationException::withMessages([
                    'file' => 'Uploaded file is invalid or unreadable.',
                ]);
            }

            $result = $this->processImportFile($uploadedFile, $type, $replace);

            Log::info('Duplicate file records imported via UI', [
                'user_id' => Auth::id(),
                'type' => $type,
                'mode' => $replace ? 'replace' : 'append',
                'processed' => $result['processed'] ?? 0,
                'affected' => $result['affected'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
                'deleted' => $result['deleted'] ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Import completed successfully.',
                'data' => [
                    'type' => $type,
                    'mode' => $replace ? 'replace' : 'append',
                    'processed' => $result['processed'] ?? 0,
                    'affected' => $result['affected'] ?? 0,
                    'skipped' => $result['skipped'] ?? 0,
                    'deleted' => $result['deleted'] ?? 0,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to import duplicate file records', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed. Please verify the file and try again.',
            ], 500);
        }
    }

    public function downloadTemplate(): StreamedResponse
    {
        $columns = ['registry', 'file_number', 'file_title', 'plot_number', 'location', 'comment'];

        $sampleRow = [
            'KANGIS Registry',
            'KANGIS-RES-2025-0001',
            'Duplicate allocation review',
            'Plot 24A',
            'Tarauni GRA',
            'Original allocation traced to legacy register.',
        ];

        $filename = sprintf('duplicate-file-import-template-%s.csv', Carbon::now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($columns, $sampleRow) {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                throw new \RuntimeException('Unable to open output stream.');
            }

            fputcsv($handle, $columns);
            fputcsv($handle, $sampleRow);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function export(Request $request)
    {
        try {
            $type = $this->normalizeType($request->input('type')) ?? self::TYPE_DUPLICATE;
            $search = $this->normalizeSearch($request->input('search'));
            $registryFilter = $this->normalizeRegistry($request->input('registry'));

            $startInput = $request->input('start_date');
            $endInput   = $request->input('end_date');

            $startDate = (is_string($startInput) && trim($startInput) !== '')
                ? $this->parseDate($startInput)
                : null;
            $endDate = (is_string($endInput) && trim($endInput) !== '')
                ? $this->parseDate($endInput, true)
                : null;

            if ($startDate && $endDate && $startDate->gt($endDate)) {
                throw ValidationException::withMessages([
                    'date_range' => 'Start date must be before end date.',
                ]);
            }

            $query = DuplicateFileno::on('sqlsrv')
                ->select(['id', 'registry', 'file_number', 'file_title', 'plot_number', 'location', 'comment', 'category', 'created_at'])
                ->orderBy('created_at')
                ->orderBy('id');

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()]);
            } elseif ($startDate) {
                $query->where('created_at', '>=', $startDate->toDateTimeString());
            } elseif ($endDate) {
                $query->where('created_at', '<=', $endDate->toDateTimeString());
            }

            if ($registryFilter !== null) {
                $query->where('registry', $registryFilter);
            }

            if ($search !== null) {
                $escapedSearch = $this->escapeLike($search);
                $normalizedSearch = $this->normalizer->normalizeFileNumberForMatch($search);
                $normalizedEscaped = $normalizedSearch ? $this->escapeLike($normalizedSearch) : null;

                $query->where(function ($builder) use ($escapedSearch, $normalizedEscaped) {
                    $like = '%' . $escapedSearch . '%';

                    $builder->where('file_number', 'like', $like)
                        ->orWhere('file_title', 'like', $like)
                        ->orWhere('plot_number', 'like', $like)
                        ->orWhere('location', 'like', $like)
                        ->orWhere('comment', 'like', $like);

                    if ($normalizedEscaped !== null) {
                        $builder->orWhereRaw(
                            "REPLACE(REPLACE(file_number, '-', ''), ' ', '') LIKE ?",
                            ['%' . $normalizedEscaped . '%']
                        );
                    }
                });
            }

            // Apply the same type-based filtering used by apiList so that
            // exports are scoped to the active tab (Duplicate / Temp / W/C/R).
            if ($type !== null) {
                $this->applyTypeFilter($query, $type);
            }

            $label = $this->typeLabel($type);
            $dateRange = ($startDate && $endDate)
                ? sprintf('-%s-to-%s', $startDate->format('Ymd'), $endDate->format('Ymd'))
                : '-all';
            $filename = sprintf('%s-records%s.csv', Str::slug($label), $dateRange);

            $columns = ['SN', 'Registry', 'File Number', 'File Title', 'Plot Number', 'Location', 'Category', 'Comment', 'Created At'];
            $timezone = config('app.timezone');
            if (!is_string($timezone) || trim($timezone) === '') {
                $timezone = 'UTC';
            }

            $matched = $query->toBase()->getCountForPagination();
            Log::info('FileSearch export preparing stream', [
                'user_id' => Auth::id(),
                'type' => $type,
                'registry' => $registryFilter,
                'search' => $search,
                'start' => $startDate?->toDateTimeString(),
                'end' => $endDate?->toDateTimeString(),
                'matched' => $matched,
            ]);

            $response = response()->streamDownload(function () use ($query, $columns, $timezone) {
                $handle = fopen('php://output', 'wb');

                if ($handle === false) {
                    throw new \RuntimeException('Unable to open export stream.');
                }

                fputcsv($handle, $columns);

                $serial = 1;

                $query->chunk(500, function ($rows) use (&$serial, $handle, $timezone) {
                    foreach ($rows as $row) {
                        [$recordType, $note] = $this->splitComment($row->comment);
                        $category = $row->category ?? $this->categoryLabel($recordType);

                        $createdAt = $row->created_at instanceof Carbon
                            ? $row->created_at
                            : ($row->created_at ? Carbon::parse($row->created_at) : null);
                        if ($createdAt) {
                            $createdAtLocal = $createdAt->copy()->timezone($timezone);
                            $createdAtDisplay = $createdAtLocal->format('Y-m-d H:i:s') . '.' . substr($createdAtLocal->format('u'), 0, 3);
                        } else {
                            $createdAtDisplay = '';
                        }

                        $fileTitle = $row->file_title ?? '';
                        $normalizedTitle = str_replace(["\r\n", "\r"], "\n", $fileTitle);

                        fputcsv($handle, [
                            $serial++,
                            $row->registry ?? '',
                            $row->file_number ?? '',
                            $normalizedTitle,
                            $row->plot_number ?? '',
                            $row->location ?? '',
                            $category,
                            $note ?? '',
                            $createdAtDisplay,
                        ]);
                    }

                    fflush($handle);
                });

                fclose($handle);
            }, $filename, [
                'Content-Type' => 'text/csv',
            ]);

            Log::info('Duplicate file records exported via UI', [
                'user_id' => Auth::id(),
                'type' => $type,
                'start_date' => $startDate?->toDateString(),
                'end_date' => $endDate?->toDateString(),
                'registry' => $registryFilter,
                'search' => $search,
            ]);

            return $response;
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to export duplicate file records', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed. Please try again.',
            ], 500);
        }
    }

    public function debugExport(Request $request): JsonResponse
    {
        try {
            $type = $this->normalizeType($request->input('type')) ?? self::TYPE_DUPLICATE;
            $search = $this->normalizeSearch($request->input('search'));
            $registryFilter = $this->normalizeRegistry($request->input('registry'));

            $startInput = $request->input('start_date');
            $endInput = $request->input('end_date');

            if ($startInput === null || trim((string) $startInput) === '') {
                return response()->json(['error' => 'Start date required'], 422);
            }

            if ($endInput === null || trim((string) $endInput) === '') {
                return response()->json(['error' => 'End date required'], 422);
            }

            $startDate = $this->parseDate($startInput);
            $endDate = $this->parseDate($endInput, true);

            if (! $startDate || ! $endDate) {
                return response()->json(['error' => 'Invalid dates'], 422);
            }

            $query = DuplicateFileno::on('sqlsrv')
                ->select(['id', 'registry', 'file_number', 'created_at'])
                ->whereBetween('created_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
                ->orderBy('created_at');

            if ($registryFilter !== null) {
                $query->where('registry', $registryFilter);
            }

            if ($search !== null) {
                $escapedSearch = $this->escapeLike($search);
                $query->where(function ($builder) use ($escapedSearch) {
                    $like = '%' . $escapedSearch . '%';
                    $builder->where('file_number', 'like', $like)
                        ->orWhere('file_title', 'like', $like);
                });
            }

            if ($type !== null) {
                $this->applyTypeFilter($query, $type);
            }

            $sql = $query->toSql();
            $bindings = $query->getBindings();
            $count = $query->count();

            return response()->json([
                'success' => true,
                'debug' => [
                    'type' => $type,
                    'start_date' => $startDate->toDateTimeString(),
                    'end_date' => $endDate->toDateTimeString(),
                    'registry' => $registryFilter,
                    'search' => $search,
                    'sql' => $sql,
                    'bindings' => $bindings,
                    'matched_count' => $count,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    private function applyTypeFilter($query, string $type): void
    {
        $query->where(function ($builder) use ($type) {
            $prefix = $this->commentPrefix($type);
            $escapedPrefix = str_replace('[', '[[]', $prefix) . '%';

            if ($type === self::TYPE_DUPLICATE) {
                $builder->where('comment', 'like', $escapedPrefix)
                    ->orWhereNull('comment')
                    ->orWhere('comment', '=', '')
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('comment')
                            ->whereRaw("LEFT(comment, 1) <> '['");
                    });
            } else {
                $builder->where('comment', 'like', $escapedPrefix);
            }
        });
    }

    private function parseDate($value, bool $asEndOfDay = false): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        try {
            $date = Carbon::parse($trimmed);
        } catch (\Throwable $e) {
            return null;
        }

        return $asEndOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    private function extractAssignRoles($rawRoles): array
    {
        if (is_array($rawRoles)) {
            return array_filter(array_map('trim', $rawRoles));
        }

        if ($rawRoles === null) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', (string) $rawRoles)));
    }

    private function normalizeSearch($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeRegistry($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '' || strtolower($trimmed) === 'all') {
            return null;
        }

        return $trimmed;
    }

    private function isValidRegistryOption(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return false;
        }

        return preg_match('/^\d+$/', $trimmed) !== 1;
    }

    private function clampPerPage(int $value): int
    {
        if ($value <= 0) {
            return 25;
        }

        return min(max($value, 10), 100);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_[]');
    }

    private function normalizeType($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $type = strtolower(trim((string) $value));

        return array_key_exists($type, self::TYPE_LABELS) ? $type : null;
    }

    private function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? self::TYPE_LABELS[self::TYPE_DUPLICATE];
    }

    private function categoryLabel(string $type): string
    {
        return self::CATEGORY_LABELS[$type] ?? self::CATEGORY_LABELS[self::TYPE_DUPLICATE];
    }

    private function commentPrefix(string $type): string
    {
        return '[' . strtoupper($type) . ']';
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitComment(?string $comment): array
    {
        if ($comment === null) {
            return [self::TYPE_DUPLICATE, null];
        }

        $trimmed = trim($comment);

        if ($trimmed === '') {
            return [self::TYPE_DUPLICATE, null];
        }

        if (preg_match('/^\[(?P<type>[A-Z_]+)\]\s*(?P<note>.*)$/', $trimmed, $matches) === 1) {
            $type = strtolower($matches['type']);
            if (! array_key_exists($type, self::TYPE_LABELS)) {
                $type = self::TYPE_DUPLICATE;
            }

            $note = $matches['note'] !== '' ? $matches['note'] : null;

            return [$type, $note];
        }

        return [self::TYPE_DUPLICATE, $trimmed];
    }

    private function combineComment(string $type, ?string $note): string
    {
        $prefix = $this->commentPrefix($type);

        if ($note === null) {
            return $prefix;
        }

        $trimmed = trim($note);

        if ($trimmed === '' || strcasecmp($trimmed, $prefix) === 0) {
            return $prefix;
        }

        if (str_starts_with($trimmed, '[')) {
            $trimmed = preg_replace('/^\[[^\]]*\]\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        if ($trimmed === '') {
            return $prefix;
        }

        return $prefix . ' ' . $trimmed;
    }

    private function commentDisplay(string $category, ?string $note): string
    {
        $trimmedNote = $note === null ? '' : trim($note);

        if ($trimmedNote === '') {
            return $category;
        }

        return $category . ' — ' . $trimmedNote;
    }

    private function normalizeNote($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function processImportFile(UploadedFile $file, string $type, bool $replace): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new \RuntimeException('Unable to access uploaded file.');
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open uploaded file.');
        }

        $headers = null;
        $chunk = [];
        $processed = 0;
        $skipped = 0;
        $affected = 0;
        $deleted = $replace ? $this->deleteRecordsByType($type) : 0;
        $userId = Auth::id();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if ($headers === null) {
                    $headers = $this->normalizeCsvHeaders($row);
                    continue;
                }

                if ($this->csvRowEmpty($row)) {
                    $skipped++;
                    continue;
                }

                $record = $this->mapCsvRow($headers, $row);

                $registry = $this->cleanImportValue($record['registry'] ?? null);
                $fileNumber = $this->normalizer->standardizeFileNumber($record['file_number'] ?? null);

                if ($registry === null || $fileNumber === null) {
                    $skipped++;
                    continue;
                }

                $noteSource = $this->cleanImportValue($record['comment'] ?? $record['comments'] ?? null);
                $note = null;

                if ($noteSource !== null) {
                    [, $inlineNote] = $this->splitComment($noteSource);
                    $note = $inlineNote;
                }

                $chunk[] = [
                    'registry' => $registry,
                    'file_number' => $fileNumber,
                    'file_title' => $this->normalizeFileTitles($record['file_title'] ?? null),
                    'plot_number' => $this->cleanImportValue($record['plot_number'] ?? null),
                    'location' => $this->cleanImportValue($record['location'] ?? null),
                    'comment' => $this->combineComment($type, $note),
                    'category' => $this->categoryLabel($type),
                ];

                $processed++;

                if (count($chunk) >= self::IMPORT_CHUNK_SIZE) {
                    $affected += $this->insertImportChunk($chunk, $type, $userId);
                    $chunk = [];
                }
            }
        } finally {
            fclose($handle);
        }

        if (! empty($chunk)) {
            $affected += $this->insertImportChunk($chunk, $type, $userId);
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'affected' => $affected,
            'deleted' => $deleted,
        ];
    }

    private function insertImportChunk(array $chunk, string $type, ?int $userId): int
    {
        if (empty($chunk)) {
            return 0;
        }

        $timestamp = Carbon::now();

        $payload = array_map(function (array $row) use ($timestamp, $type, $userId) {
            return [
                'registry' => $row['registry'],
                'file_number' => $row['file_number'],
                'file_title' => $row['file_title'],
                'plot_number' => $row['plot_number'],
                'location' => $row['location'],
                'comment' => $row['comment'],
                'category' => $row['category'] ?? $this->categoryLabel($type),
                'source' => sprintf('upload:%s', $type),
                'created_by' => $userId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }, $chunk);

        return DuplicateFileno::on('sqlsrv')->upsert(
            $payload,
            ['registry', 'file_number'],
            ['file_title', 'plot_number', 'location', 'comment', 'category', 'source', 'updated_at', 'created_by']
        );
    }

    private function deleteRecordsByType(string $type): int
    {
        $pattern = '[[]' . strtoupper($type) . ']%';

        return DuplicateFileno::on('sqlsrv')
            ->where(function ($query) use ($type, $pattern) {
                if ($type === self::TYPE_DUPLICATE) {
                    $query->whereNull('comment')
                        ->orWhere('comment', '=', '')
                        ->orWhereRaw("LEFT(comment, 1) <> '['")
                        ->orWhere('comment', 'like', $pattern);
                } else {
                    $query->where('comment', 'like', $pattern);
                }
            })
            ->where(function ($query) {
                $query->whereNull('source')
                    ->orWhere('source', 'like', 'csv:%')
                    ->orWhere('source', 'like', 'upload:%');
            })
            ->delete();
    }

    private function normalizeCsvHeaders(array $row): array
    {
        return array_map(function ($value) {
            $trimmed = $this->cleanImportValue($value);

            if ($trimmed === null) {
                return null;
            }

            $normalized = Str::lower($trimmed);

            return str_replace(' ', '_', $normalized);
        }, $row);
    }

    private function mapCsvRow(array $headers, array $row): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            if ($header === null || $header === '') {
                continue;
            }

            $mapped[$header] = Arr::get($row, $index);
        }

        return $mapped;
    }

    private function csvRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->cleanImportValue($value) !== null) {
                return false;
            }
        }

        return true;
    }

    private function cleanImportValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = is_string($value) ? trim($value) : trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    private function extractFileTitleList($value): array
    {
        if ($value === null) {
            return [];
        }

        $raw = is_string($value) ? $value : (string) $value;

        if (trim($raw) === '') {
            return [];
        }

        $segments = preg_split('/\r\n|\r|\n/', $raw) ?: [$raw];

        $titles = [];

        foreach ($segments as $segment) {
            if (! is_string($segment)) {
                continue;
            }

            $trimmed = trim($segment);

            if ($trimmed === '') {
                continue;
            }

            $pieces = preg_split('/[|;]/', $trimmed) ?: [$trimmed];

            foreach ($pieces as $piece) {
                $candidate = trim($piece);

                if ($candidate === '') {
                    continue;
                }

                $titles[] = mb_substr($candidate, 0, 255);
            }
        }

        if (empty($titles)) {
            return [];
        }

        return array_values(array_unique($titles));
    }

    private function normalizeFileTitles($value): ?string
    {
        $titles = $this->extractFileTitleList($value);

        if (empty($titles)) {
            return null;
        }

        $normalized = implode("\n", $titles);

        return mb_substr($normalized, 0, 255);
    }

    /**
     * Store a new duplicate file record (manual capture).
     */
    public function store(Request $request): JsonResponse
    {
        try {
            /*
             | Two accepted shapes.
             |
             | MANY FILES (`files[]`) is what the Add Record card now posts: one entry per
             | captured file, sharing the registry and category above them. The card used
             | to collect a list of TITLES that all hung off a single file number, so
             | recording two genuinely different files -- a duplicate pair, which is most
             | of what this register is for -- meant opening the card once per file.
             |
             | ONE FILE (the flat fields) is kept because the edit screen still posts it,
             | as does the CSV import and anything else that has ever called this.
             */
            $isMultiFile = is_array($request->input('files'));

            if ($isMultiFile) {
                $validated = $request->validate([
                    'registry' => 'required|string|max:255',
                    'files' => 'required|array|min:1|max:50',
                    'files.*.file_number' => 'required|string|max:255',
                    'files.*.file_title' => 'nullable|string|max:255',
                    'files.*.plot_number' => 'nullable|string|max:255',
                    'files.*.location' => 'nullable|string|max:255',
                    'comment' => 'nullable|string|max:2000',
                    'comment_label' => 'required|string|in:duplicate,temporary,wrc,dciv_investigation',
                    'record_type' => 'required|string|in:duplicate,temporary,wrc,dciv_investigation',
                ], [
                    'files.required' => 'Add at least one file before saving.',
                    'files.max' => 'Up to 50 files can be captured at once.',
                    'files.*.file_number.required' => 'Every file needs a file number.',
                ]);
            } else {
                $validated = $request->validate([
                    'registry' => 'required|string|max:255',
                    'file_number' => 'required|string|max:255',
                    'file_title' => 'nullable|string|max:255',
                    'plot_number' => 'nullable|string|max:255',
                    'location' => 'nullable|string|max:255',
                    'comment' => 'nullable|string|max:2000',
                    'comment_label' => 'required|string|in:duplicate,temporary,wrc,dciv_investigation',
                    'record_type' => 'required|string|in:duplicate,temporary,wrc,dciv_investigation',
                ]);
            }

            $recordType = $this->normalizeType($validated['comment_label']) ?? self::TYPE_DUPLICATE;

            $providedRecordType = $this->normalizeType($validated['record_type']) ?? null;
            if ($providedRecordType !== null && $providedRecordType !== $recordType) {
                throw ValidationException::withMessages([
                    'record_type' => 'Record type must match the selected comment category.',
                ]);
            }

            $note = $this->normalizeNote($validated['comment'] ?? null);

            /*
             | Normalise both shapes to one list of files, so only the reading of the
             | request differs and the writing below is single-path.
             |
             | The single-file shape keeps its old behaviour of splitting a newline-
             | separated file_title into one record per title -- the CSV import and any
             | older caller still rely on it.
             */
            if ($isMultiFile) {
                $files = array_map(fn (array $file) => [
                    'file_number' => trim($file['file_number']),
                    'file_title' => isset($file['file_title']) ? trim($file['file_title']) : null,
                    'plot_number' => isset($file['plot_number']) ? trim($file['plot_number']) : null,
                    'location' => isset($file['location']) ? trim($file['location']) : null,
                ], $validated['files']);
            } else {
                $titles = $this->extractFileTitleList($validated['file_title'] ?? null);

                if (empty($titles)) {
                    $titles = [null];
                }

                $files = array_map(fn ($title) => [
                    'file_number' => trim($validated['file_number']),
                    'file_title' => $title,
                    'plot_number' => isset($validated['plot_number']) ? trim($validated['plot_number']) : null,
                    'location' => isset($validated['location']) ? trim($validated['location']) : null,
                ], $titles);
            }

            $createdRecords = [];

            DB::connection('sqlsrv')->transaction(function () use (&$createdRecords, $files, $validated, $recordType, $note) {
                foreach ($files as $file) {
                    $createdRecords[] = DuplicateFileno::create([
                        'registry' => trim($validated['registry']),
                        'file_number' => $file['file_number'],
                        'file_title' => ($file['file_title'] === '' ? null : $file['file_title']),
                        'plot_number' => ($file['plot_number'] === '' ? null : $file['plot_number']),
                        'location' => ($file['location'] === '' ? null : $file['location']),
                        'comment' => $this->combineComment($recordType, $note),
                        'category' => $this->categoryLabel($recordType),
                        'created_by' => Auth::id(),
                        'source' => sprintf('manual:%s', $recordType),
                    ]);
                }
            });

            $primaryRecord = $createdRecords[0];

            Log::info('Duplicate file records created manually', [
                'primary_id' => $primaryRecord->id,
                'file_number' => $primaryRecord->file_number,
                'created_count' => count($createdRecords),
                'created_by' => Auth::id(),
            ]);

            $message = count($createdRecords) > 1
                ? sprintf('%d records created successfully.', count($createdRecords))
                : 'Duplicate file record created successfully.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'id' => $primaryRecord->id,
                    'registry' => $primaryRecord->registry,
                    'file_number' => $primaryRecord->file_number,
                    'record_type' => $recordType,
                    'record_type_label' => $this->typeLabel($recordType),
                    'category' => $primaryRecord->category,
                    'comment' => $note,
                    'comment_label' => $recordType,
                    'comment_prefixed' => $primaryRecord->comment,
                    'comment_display' => $this->commentDisplay($primaryRecord->category, $note),
                    'created_count' => count($createdRecords),
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create duplicate file record', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create record. Please try again.',
            ], 500);
        }
    }

    /**
     * Update an existing duplicate file record.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $record = DuplicateFileno::findOrFail($id);

            $validated = $request->validate([
                'registry' => 'required|string|max:255',
                'file_number' => 'required|string|max:255',
                'file_title' => 'nullable|string|max:255',
                'plot_number' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:255',
                'comment' => 'nullable|string|max:2000',
                'comment_label' => 'required|string|in:duplicate,temporary,wrc,dciv_investigation',
                'record_type' => 'required|string|in:duplicate,temporary,wrc,dciv_investigation',
            ]);

            $recordType = $this->normalizeType($validated['comment_label']) ?? self::TYPE_DUPLICATE;

            $providedRecordType = $this->normalizeType($validated['record_type']) ?? null;
            if ($providedRecordType !== null && $providedRecordType !== $recordType) {
                throw ValidationException::withMessages([
                    'record_type' => 'Record type must match the selected comment category.',
                ]);
            }
            $note = $this->normalizeNote($validated['comment'] ?? null);

            $titles = $this->extractFileTitleList($validated['file_title'] ?? null);

            if (empty($titles)) {
                $titles = [null];
            }

            $primaryTitle = array_shift($titles);
            $additionalRecords = [];

            DB::connection('sqlsrv')->transaction(function () use (
                $record,
                $validated,
                $recordType,
                $note,
                $primaryTitle,
                $titles,
                &$additionalRecords
            ) {
                $record->update([
                    'registry' => trim($validated['registry']),
                    'file_number' => trim($validated['file_number']),
                    'file_title' => $primaryTitle,
                    'plot_number' => isset($validated['plot_number']) ? trim($validated['plot_number']) : null,
                    'location' => isset($validated['location']) ? trim($validated['location']) : null,
                    'comment' => $this->combineComment($recordType, $note),
                    'category' => $this->categoryLabel($recordType),
                    'source' => sprintf('manual:%s', $recordType),
                ]);

                foreach ($titles as $title) {
                    $additionalRecords[] = DuplicateFileno::create([
                        'registry' => trim($validated['registry']),
                        'file_number' => trim($validated['file_number']),
                        'file_title' => $title,
                        'plot_number' => isset($validated['plot_number']) ? trim($validated['plot_number']) : null,
                        'location' => isset($validated['location']) ? trim($validated['location']) : null,
                        'comment' => $this->combineComment($recordType, $note),
                        'category' => $this->categoryLabel($recordType),
                        'created_by' => Auth::id(),
                        'source' => sprintf('manual:%s', $recordType),
                    ]);
                }
            });

            Log::info('Duplicate file record updated', [
                'id' => $record->id,
                'file_number' => $record->file_number,
                'updated_by' => Auth::id(),
                'additional_records_created' => count($additionalRecords),
            ]);

            return response()->json([
                'success' => true,
                'message' => count($additionalRecords) > 0
                    ? sprintf('Record updated. %d additional record(s) created.', count($additionalRecords))
                    : 'Record updated successfully.',
                'data' => [
                    'id' => $record->id,
                    'registry' => $record->registry,
                    'file_number' => $record->file_number,
                    'record_type' => $recordType,
                    'record_type_label' => $this->typeLabel($recordType),
                    'category' => $record->category,
                    'comment' => $note,
                    'comment_label' => $recordType,
                    'comment_prefixed' => $record->comment,
                    'comment_display' => $this->commentDisplay($record->category, $note),
                    'additional_records_created' => count($additionalRecords),
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update duplicate file record', [
                'id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update record. Please try again.',
            ], 500);
        }
    }

    /**
     * Delete a duplicate file record.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $record = DuplicateFileno::findOrFail($id);

            $fileNumber = $record->file_number;
            $record->delete();

            Log::info('Duplicate file record deleted', [
                'id' => $id,
                'file_number' => $fileNumber,
                'deleted_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Record deleted successfully.',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete duplicate file record', [
                'id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete record. Please try again.',
            ], 500);
        }
    }

    /**
     * Get a single record for editing.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $record = DuplicateFileno::findOrFail($id);

            [$recordType, $note] = $this->splitComment($record->comment);

            return response()->json([
                'success' => true,
                'message' => 'Record retrieved successfully.',
                'data' => [
                    'id' => $record->id,
                    'registry' => $record->registry,
                    'file_number' => $record->file_number,
                    'file_title' => $record->file_title,
                    'plot_number' => $record->plot_number,
                    'location' => $record->location,
                    'comment' => $note,
                    'comment_label' => $recordType,
                    'record_type' => $recordType,
                    'record_type_label' => $this->typeLabel($recordType),
                    'comment_prefixed' => $record->comment,
                    'category' => $record->category ?? $this->categoryLabel($recordType),
                    'comment_display' => $this->commentDisplay(
                        $record->category ?? $this->categoryLabel($recordType),
                        $note
                    ),
                    'source' => $record->source ?? 'csv',
                    'created_by' => $record->created_by,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found.',
            ], 404);
        }
    }
}
