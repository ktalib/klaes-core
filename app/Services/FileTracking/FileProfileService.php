<?php

namespace App\Services\FileTracking;

use App\Http\Controllers\Api\FileTrackerApiController;
use App\Models\FileTracker;
use App\Models\PageTyping;
use App\Services\Edms\EdmsDocumentPathResolver;
use App\Services\FilePassportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The file profile shown on File Movement (Department) after a scan: indexing
 * details, the applicant's passport, the full movement history and the EDMS
 * pages. Read-only.
 */
class FileProfileService
{
    /** EDMS pages returned per profile; the rest are counted, not listed. */
    private const PAGE_LIMIT = 60;

    public function __construct(private EdmsDocumentPathResolver $paths, private FilePassportService $passports)
    {
    }

    public function build(?string $fileNumber, ?int $trackerId = null): array
    {
        $fileNumber = trim((string) $fileNumber) ?: null;
        $db = DB::connection('sqlsrv');

        $tracker = null;
        if ($trackerId) {
            $tracker = FileTracker::find($trackerId);
            $fileNumber = $fileNumber ?: $tracker?->file_number;
        } elseif ($fileNumber) {
            $tracker = FileTracker::where('file_number', $fileNumber)
                ->whereRaw("UPPER(ISNULL(status,'')) NOT IN ('CANCELLED')")
                ->orderByDesc('id')->first();
        }

        $indexing = $fileNumber
            ? $db->table('file_indexings')->where('file_number', $fileNumber)
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderByDesc('id')
                ->first(['id', 'file_number', 'file_title', 'plot_number', 'district', 'lga', 'land_use_type',
                         'registry', 'shelf_location', 'batch_no', 'plot_size', 'tp_no', 'title_status',
                         'tracking_id', 'is_decommissioned', 'successor_file_no', 'edms_file_type', 'created_at'])
            : null;

        $meta = $tracker ? json_decode((string) $tracker->module_meta, true) : null;
        $meta = is_array($meta) ? ($meta['secretariat'] ?? []) : [];

        $passport = null;
        $passportPath = null;
        if ($fileNumber) {
            try {
                $resolved = $this->passports->resolve($fileNumber);
                $passport = $resolved['url'] ?? null;
                $passportPath = $resolved['path'] ?? null;
            } catch (\Throwable $e) {
                Log::warning('File profile passport lookup failed', ['file' => $fileNumber, 'error' => $e->getMessage()]);
            }
        }

        return [
            'file' => [
                'file_number'     => $fileNumber,
                'file_title'      => $indexing->file_title ?? $tracker?->file_title,
                'indexed'         => (bool) $indexing,
                'non_file'        => $tracker && $tracker->file_type === 'NON_FILE',
                'plot_number'     => $indexing->plot_number ?? null,
                'district'        => $indexing->district ?? null,
                'lga'             => $indexing->lga ?? null,
                'land_use'        => $indexing->land_use_type ?? null,
                'plot_size'       => $indexing->plot_size ?? null,
                'tp_no'           => $indexing->tp_no ?? null,
                'registry'        => $indexing->registry ?? null,
                'shelf_location'  => $indexing->shelf_location ?? null,
                'batch_no'        => $indexing->batch_no ?? null,
                'title_status'    => $indexing->title_status ?? null,
                'decommissioned'  => !empty($indexing->is_decommissioned),
                'successor'       => $indexing->successor_file_no ?? null,
                'sender'          => $meta['sender'] ?? null,
                'reference'       => $meta['reference'] ?? null,
                'passport_url'    => $passport,
            ],
            'tracker' => $tracker ? [
                'id'             => $tracker->id,
                'tracking_id'    => $tracker->tracking_id,
                'status'         => $tracker->status,
                'module'         => $tracker->module,
                'current_office' => $tracker->current_office_name ?: $tracker->current_office_code,
                'current_code'   => $tracker->current_office_code,
                'holder'         => $tracker->current_holder,
                'created_by'     => $tracker->created_by_name,
                'created_at'     => optional($tracker->created_at)->toDateTimeString(),
                'last_status'    => strtolower((string) (collect($tracker->movement_log ?: [])->last()['status'] ?? '')),
            ] : null,
            'history' => $this->history($tracker, $fileNumber),
            'edms'    => $indexing ? $this->edms($indexing, $passportPath) : ['total' => 0, 'pages' => []],
        ];
    }

    /**
     * Every tracking cycle for the file, exactly as Quick Search's Movement Timeline
     * gets it: FileTrackerApiController::track() — earlier trackers, the
     * commissioning line, then the current tracker. Entries are returned raw; the
     * page sorts and labels them with the same rules Quick Search uses, so both
     * screens show the same timeline.
     */
    /**
     * Tag each history entry with where it is stored — {tracker_id, index, key} —
     * so an admin can delete that one log. Entries that are not stored on this
     * file's trackers (the commissioning line, related parcel files) or that
     * match more than one stored log get no tag.
     */
    private function withLogRefs(array $entries, ?FileTracker $tracker, ?string $fileNumber): array
    {
        $number = trim((string) ($fileNumber ?: $tracker?->file_number));
        $trackers = $number !== ''
            ? FileTracker::where('file_number', $number)->orderBy('id')->get(['id', 'movement_log'])
            : collect($tracker ? [$tracker] : []);

        $refs = [];
        foreach ($trackers as $row) {
            foreach (array_values($row->movement_log ?: []) as $i => $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $key = SecretariatFileLogService::logKey($entry);
                $refs[$key] = isset($refs[$key]) ? false : ['tracker_id' => (int) $row->id, 'index' => $i, 'key' => $key];
            }
        }

        foreach ($entries as &$entry) {
            $ref = $refs[SecretariatFileLogService::logKey($entry)] ?? false;
            if ($ref) {
                $entry['_ref'] = $ref;
            }
        }
        unset($entry);

        return $entries;
    }

    private function history(?FileTracker $tracker, ?string $fileNumber): array
    {
        $empty = ['entries' => [], 'meta' => null];
        $identifier = $fileNumber ?: $tracker?->tracking_id;
        if (!$identifier) {
            return $empty;
        }

        try {
            $data = app(FileTrackerApiController::class)->track(request(), $identifier)->getData(true)['data'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('File profile history lookup failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);
            $data = null;
        }

        if (!is_array($data)) {
            return $tracker ? ['entries' => array_values($tracker->movement_log ?: []), 'meta' => null] : $empty;
        }

        return [
            'entries' => $this->withLogRefs(array_values(array_filter(
                array_merge($data['prior_movements'] ?? [], $data['movement_history'] ?? []),
                'is_array'
            )), $tracker, $fileNumber),
            'meta' => [
                'request_purpose_name' => $data['request_purpose_name'] ?? null,
                'timeline_status'      => $data['timeline_status'] ?? null,
                'days_until_deadline'  => $data['days_until_deadline'] ?? null,
                'deadline'             => $data['deadline'] ?? null,
                'is_commissioned'      => (bool) ($data['is_commissioned'] ?? false),
                'officer_photos'       => $data['officer_photos'] ?? [],
            ],
        ];
    }

    /**
     * Page-typed pages, plus documents uploaded into EDMS (SCAN_UPLOAD) that have
     * not been page-typed yet — e.g. a passport photo filed from the OSS. Without
     * the second group a file with uploads would show an empty EDMS tab.
     */
    private function edms(object $indexing, ?string $passportPath = null): array
    {
        $db = DB::connection('sqlsrv');
        $fileContext = (object) [
            'file_number'    => $indexing->file_number,
            'registry'       => $indexing->registry,
            'edms_file_type' => $indexing->edms_file_type,
        ];

        $typedQuery = PageTyping::where('file_indexing_id', $indexing->id)->whereNull('deleted_at');
        $typedTotal = (clone $typedQuery)->count();

        $untypedQuery = $db->table('scannings as s')
            ->where('s.file_indexing_id', $indexing->id)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('pagetypings as p')
                    ->whereColumn('p.scanning_id', 's.id')
                    ->whereNull('p.deleted_at');
            });

        // Replacing a passport files a new upload and keeps the old one (records are
        // never deleted), so a file can hold several. Show only the current one: the
        // upload the file's record points at, or failing that the newest.
        $passportScans = (clone $untypedQuery)->where('s.document_type', 'like', '%passport%')
            ->orderByDesc('s.created_at')->orderByDesc('s.id')
            ->get(['s.id', 's.document_path']);
        if ($passportScans->count() > 1) {
            $current = $passportScans->first(fn ($scan) => $passportPath && $this->samePath($scan->document_path, $passportPath))
                ?? $passportScans->first();
            $untypedQuery->whereNotIn('s.id', $passportScans->pluck('id')->reject(fn ($id) => $id == $current->id)->all());
        }

        $untypedTotal = (clone $untypedQuery)->count();

        $uploads = $untypedQuery
            ->orderByDesc('s.created_at')
            ->limit(self::PAGE_LIMIT)
            ->get(['s.id', 's.document_path', 's.document_type', 's.original_filename', 's.paper_size',
                   's.registry', 's.edms_file_type', 's.created_at'])
            ->map(function ($scan) use ($fileContext) {
                $url = $this->paths->resolveUrl($scan->document_path, $this->paths->contextFromScanning($scan, $fileContext));
                $row = $this->pageRow('scan-' . $scan->id, null, null, $scan->document_type ?: 'Uploaded document',
                    null, $url, $scan->document_path, true, $scan->created_at);
                $row['passport'] = stripos((string) $scan->document_type, 'passport') !== false;

                return $row;
            });

        $remaining = max(0, self::PAGE_LIMIT - $uploads->count());
        $typed = $remaining === 0 ? collect() : $typedQuery
            ->with(['scanning', 'pageType:id,PageType', 'pageSubType:id,PageSubType'])
            ->orderBy('page_number')->orderBy('id')
            ->limit($remaining)
            ->get()
            ->map(function (PageTyping $p) use ($fileContext) {
                $context = $p->scanning
                    ? $this->paths->contextFromScanning($p->scanning, $fileContext)
                    : ['file_number' => $fileContext->file_number, 'registry' => $p->registry ?? $fileContext->registry,
                       'file_type' => $p->edms_file_type ?? $fileContext->edms_file_type];

                $url = null;
                foreach ([optional($p->scanning)->document_path, $p->file_path] as $candidate) {
                    if ($candidate && ($url = $this->paths->resolveUrl($candidate, $context))) {
                        break;
                    }
                }

                return $this->pageRow($p->id, $p->page_number, $p->page_code,
                    optional($p->pageType)->PageType ?: ($p->page_type_others ?: null),
                    optional($p->pageSubType)->PageSubType ?: ($p->page_subtype_others ?: null),
                    $url, $p->file_path, false, null);
            });

        return [
            'total'   => $typedTotal + $untypedTotal,
            'typed'   => $typedTotal,
            'untyped' => $untypedTotal,
            'pages'   => $uploads->concat($typed)->values()->all(),
        ];
    }

    private function samePath(?string $a, ?string $b): bool
    {
        $norm = fn ($p) => strtolower(ltrim(preg_replace('#^public/#i', '', str_replace('\\', '/', trim((string) $p))), '/'));

        return $norm($a) !== '' && $norm($a) === $norm($b);
    }

    private function pageRow($id, $page, $code, $type, $subtype, ?string $url, ?string $storedPath, bool $untyped, $uploadedAt): array
    {
        $ext = strtolower(pathinfo(parse_url((string) $url, PHP_URL_PATH) ?: (string) $storedPath, PATHINFO_EXTENSION));

        return [
            'id'          => $id,
            'page'        => $page,
            'code'        => $code,
            'type'        => $type,
            'subtype'     => $subtype,
            'url'         => $url,
            'is_image'    => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true),
            'is_pdf'      => $ext === 'pdf',
            'untyped'     => $untyped,
            'uploaded_at' => $uploadedAt ? (string) $uploadedAt : null,
        ];
    }
}
