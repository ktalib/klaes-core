<?php

namespace App\Services\FileTracking;

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
                ->orderByDesc('updated_at')->first();
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
        if ($fileNumber) {
            try {
                $passport = $this->passports->resolve($fileNumber)['url'] ?? null;
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
            'history' => $tracker ? $this->history($tracker) : [],
            'edms'    => $indexing ? $this->edms($indexing) : ['total' => 0, 'pages' => []],
        ];
    }

    /** Movement log, newest first, trimmed to what the timeline shows. */
    private function history(FileTracker $tracker): array
    {
        $entries = [];
        foreach (array_reverse($tracker->movement_log ?: []) as $e) {
            if (!is_array($e)) {
                continue;
            }
            $entries[] = [
                'office'      => $e['office_name'] ?? $e['office_code'] ?? '—',
                'office_code' => $e['office_code'] ?? null,
                'status'      => strtolower((string) ($e['status'] ?? '')),
                'in'          => trim(($e['log_in_date'] ?? '') . ' ' . ($e['log_in_time'] ?? '')) ?: null,
                'out'         => trim(($e['log_out_date'] ?? '') . ' ' . ($e['log_out_time'] ?? '')) ?: null,
                'at'          => $e['timestamp'] ?? null,
                'by'          => $e['user_name'] ?? null,
                'accepted_by' => $e['accepted_by_name'] ?? null,
                'purpose'     => $e['purpose'] ?? null,
                'notes'       => $e['notes'] ?? ($e['completion_notes'] ?? null),
            ];
        }

        return $entries;
    }

    /**
     * Page-typed pages, plus documents uploaded into EDMS (SCAN_UPLOAD) that have
     * not been page-typed yet — e.g. a passport photo filed from the OSS. Without
     * the second group a file with uploads would show an empty EDMS tab.
     */
    private function edms(object $indexing): array
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
        $untypedTotal = (clone $untypedQuery)->count();

        $uploads = $untypedQuery
            ->orderByDesc('s.created_at')
            ->limit(self::PAGE_LIMIT)
            ->get(['s.id', 's.document_path', 's.document_type', 's.original_filename', 's.paper_size',
                   's.registry', 's.edms_file_type', 's.created_at'])
            ->map(function ($scan) use ($fileContext) {
                $url = $this->paths->resolveUrl($scan->document_path, $this->paths->contextFromScanning($scan, $fileContext));
                return $this->pageRow('scan-' . $scan->id, null, null, $scan->document_type ?: 'Uploaded document',
                    'Not yet page-typed', $url, $scan->document_path, true, $scan->created_at);
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
