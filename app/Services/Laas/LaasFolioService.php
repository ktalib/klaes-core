<?php

namespace App\Services\Laas;

use App\Http\Controllers\CommissioningSheetController;
use App\Http\Controllers\FileIndexController;
use App\Http\Controllers\FileNumberController;
use App\Http\Controllers\LandRecommendationController;
use App\Http\Controllers\LandRofoController;
use App\Http\Controllers\LandsOneStopShop\ApplicationController as OssApplicationController;
use App\Models\Laas\LaasApplication;
use App\Support\HolderName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The applicant's folio: every document on their file, in one list.
 *
 * Two kinds, shown side by side and never merged — a scanned RoFO and the
 * system's own RoFO can be two different documents (an older grant, a
 * re-issue), so neither is allowed to hide the other:
 *
 *  SCANNED   rows in `scannings` for the file's EDMS folder (passport, and
 *            anything scanned and uploaded since).
 *  SYSTEM    documents the system already holds the data for and can draw on
 *            demand — commissioning sheet, LGA confirmation, tracking sheet,
 *            recommendation, RoFO, and the OSS acknowledgement / verification /
 *            change of ownership. Nobody has to print and scan these.
 *
 * READ-ONLY RENDERING. The staff print actions are not pure: the LGA sheet
 * issues its serial on first print, the RoFO mints a security code, the tracking
 * sheet bumps a print counter, most write a print log. An applicant opening
 * their folio must change none of that, so every system document is drawn
 * inside a transaction that is always rolled back (see readOnly()). For the
 * same reason a document is only LISTED once the office has actually issued it
 * — a preview of a never-issued LGA sheet would show a serial that was never
 * given out.
 *
 * OWNERSHIP. The OSS acknowledgement, verification and change-of-ownership
 * tables are keyed by a record id that, depending on the screen, is an
 * instrument_capture id, a pra id or an oss_applications id. A bare id match
 * can therefore land on another applicant's row, so those rows are only used
 * when they also agree with this file (holder name or plot), or were reached
 * through the commissioning row's own source id.
 */
class LaasFolioService
{
    public const KIND_SCAN = 'scan';
    public const KIND_SYSTEM = 'system';

    /**
     * @return array<int,array{key:string,title:string,kind:string,group:string,date:?string,available:bool,note:?string,is_image:bool}>
     */
    public function documents(LaasApplication $application): array
    {
        $fileNumber = trim((string) $application->file_number);
        if ($fileNumber === '') {
            return [];
        }

        $docs = [];

        try {
            $docs = array_merge($docs, $this->systemDocuments($application, $fileNumber));
        } catch (\Throwable $e) {
            Log::warning('LAAS folio: system documents failed', ['file' => $fileNumber, 'error' => $e->getMessage()]);
        }

        try {
            $docs = array_merge($docs, $this->scannedDocuments($fileNumber));
        } catch (\Throwable $e) {
            Log::warning('LAAS folio: scanned documents failed', ['file' => $fileNumber, 'error' => $e->getMessage()]);
        }

        $hidden = (array) config('laas.folio.hidden', []);

        return array_values(array_filter($docs, fn ($doc) => !in_array($doc['key'], $hidden, true)));
    }

    /**
     * Render one folio entry. Returns null when the key names nothing on this
     * file — the caller answers 404, never another file's document.
     */
    public function render(LaasApplication $application, string $key): ?Response
    {
        $fileNumber = trim((string) $application->file_number);
        if ($fileNumber === '' || in_array($key, (array) config('laas.folio.hidden', []), true)) {
            return null;
        }

        if (preg_match('/^scan-(\d+)$/', $key, $m)) {
            return $this->streamScan($fileNumber, (int) $m[1]);
        }

        $html = match ($key) {
            'commissioning-sheet'     => $this->commissioningSheet($fileNumber),
            'lga-confirmation'        => $this->lgaConfirmation($fileNumber),
            'tracking-sheet'          => $this->trackingSheet($fileNumber),
            'recommendation'          => $this->recommendation($fileNumber),
            'rofo'                    => $this->rofo($fileNumber),
            'oss-acknowledgement'     => $this->ossAcknowledgement($fileNumber),
            'oss-verification'        => $this->ossVerification($fileNumber),
            'oss-change-of-ownership' => $this->ossChangeOfOwnership($fileNumber),
            default                   => null,
        };

        if ($html === null) {
            return null;
        }

        return response($this->forEmbedding($html), 200, [
            'Content-Type'  => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            // A system copy is for reading on the portal; keep it out of frames
            // and search engines.
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Robots-Tag'    => 'noindex',
        ]);
    }

    /**
     * Make a staff print page fit to sit inside the folio's card preview and
     * viewer. Every one of these templates calls window.print() on load (the
     * office opens them to print), and several also carry a Print button or a
     * "back to the list" link into staff screens. The scripts themselves have
     * to keep running — QR codes and the Tailwind build are drawn by them — so
     * printing is stubbed out and the toolbars hidden, rather than scripts
     * being switched off. Injected first in <head> so it is in place before
     * any template script runs.
     */
    private function forEmbedding(string $html): string
    {
        $shim = '<script>window.print=function(){};window.close=function(){};</script>'
              . '<style>.no-print,.print-btn,.print-btn-container,.print-button{display:none!important}'
              . 'html,body{-webkit-user-select:none;user-select:none}</style>';

        $patched = preg_replace('/<head(\s[^>]*)?>/i', '$0' . $shim, $html, 1, $count);

        return $count ? $patched : $shim . $html;
    }

    // ------------------------------------------------------------- listing

    private function systemDocuments(LaasApplication $application, string $fileNumber): array
    {
        $db = DB::connection('sqlsrv');
        $mls = $this->mlsRow($fileNumber);
        $docs = [];

        if ($mls) {
            $docs[] = $this->entry('commissioning-sheet', 'Commissioning Sheet', 'Commissioning',
                $mls->commissioning_date ?? $mls->created_at ?? null);
        }

        $isConversion = $mls && (stripos((string) $mls->source, 'conversion') !== false
            || str_starts_with(strtoupper($fileNumber), 'CON-'));
        $lga = $this->issuedConversionApplication($fileNumber);
        if ($lga) {
            $docs[] = $this->entry('lga-confirmation', 'LGA Confirmation Sheet', 'Commissioning', $lga->created_at ?? null);
        } elseif ($isConversion) {
            $docs[] = $this->entry('lga-confirmation', 'LGA Confirmation Sheet', 'Commissioning', null,
                false, 'Not yet issued by the Lands office.');
        }

        $indexing = $this->indexingRow($fileNumber);
        if ($indexing) {
            $docs[] = $this->entry('tracking-sheet', 'File Tracking Sheet', 'Commissioning', $indexing->created_at ?? null);
        }

        if ($this->acknowledgementRow($fileNumber, $mls)) {
            $docs[] = $this->entry('oss-acknowledgement', 'OSS Acknowledgement', 'One Stop Shop', null);
        }
        if ($row = $this->ossLinkedRow('oss_verifications', $fileNumber, $mls)) {
            $docs[] = $this->entry('oss-verification', 'OSS Verification (LN-03A)', 'One Stop Shop', $row->created_at ?? null);
        }
        if ($row = $this->ossLinkedRow('oss_change_of_ownership', $fileNumber, $mls)) {
            $docs[] = $this->entry('oss-change-of-ownership', 'OSS Change of Ownership', 'One Stop Shop', $row->created_at ?? null);
        }

        $rec = $this->recommendationRow($fileNumber);
        if ($rec && $this->recommendationPrintable($rec)) {
            $docs[] = $this->entry('recommendation', 'Recommendation', 'Grant', $rec->approved_at ?? $rec->created_at ?? null);
        }
        if ($rec && ($rofo = $this->rofoState($rec))) {
            $docs[] = $this->entry('rofo', $rofo === 'issued' ? 'Right of Occupancy (RoFO)' : 'Right of Occupancy (RoFO) — white copy',
                'Grant', $rec->rofo_generated_at ?? null);
        }

        return $docs;
    }

    private function scannedDocuments(string $fileNumber): array
    {
        $ids = DB::connection('sqlsrv')->table('file_indexings')
            ->where('file_number', $fileNumber)
            ->pluck('id')
            ->all();

        if (!$ids) {
            return [];
        }

        return DB::connection('sqlsrv')->table('scannings')
            ->whereIn('file_indexing_id', $ids)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get(['id', 'document_path', 'document_type', 'original_filename', 'created_at'])
            ->map(function ($scan) {
                $ext = strtolower(pathinfo((string) $scan->document_path, PATHINFO_EXTENSION));
                $title = trim((string) $scan->document_type) ?: (trim((string) $scan->original_filename) ?: 'Scanned page');

                return [
                    'key'       => 'scan-' . $scan->id,
                    'title'     => $title,
                    'kind'      => self::KIND_SCAN,
                    'group'     => 'Scanned documents',
                    'date'      => $scan->created_at ? (string) $scan->created_at : null,
                    'available' => $this->scanPath((string) $scan->document_path) !== null,
                    'note'      => null,
                    'is_image'  => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
                    'is_pdf'    => $ext === 'pdf',
                ];
            })
            ->all();
    }

    private function entry(string $key, string $title, string $group, $date, bool $available = true, ?string $note = null): array
    {
        return [
            'key'       => $key,
            'title'     => $title,
            'kind'      => self::KIND_SYSTEM,
            'group'     => $group,
            'date'      => $date ? (string) $date : null,
            'available' => $available,
            'note'      => $note,
            'is_image'  => false,
            'is_pdf'    => false,
        ];
    }

    // ----------------------------------------------------------- renderers

    private function commissioningSheet(string $fileNumber): ?string
    {
        $mls = $this->mlsRow($fileNumber);
        if (!$mls) {
            return null;
        }

        $controller = app(CommissioningSheetController::class);
        $sheetId = DB::connection('sqlsrv')->table('file_commissioning_sheets')
            ->where('file_number', $fileNumber)
            ->orderByDesc('id')
            ->value('id');

        // The template picks its department (and Plot No vs Reason) from the
        // caller's ?source=: 'oss' prints DEPARTMENT OF LAND — what the Land and
        // OSS commissioning screens both print — 'st' Sectional Titling, and
        // anything else falls through to DCIV. The folio sends no source of its
        // own, so set the one this file was commissioned under for the render.
        $query = request()->query;
        $previousSource = $query->get('source');
        $query->set('source', stripos($fileNumber, 'ST-') === 0 ? 'st' : 'oss');

        try {
            return $this->drawCommissioningSheet($controller, $sheetId, $mls, $fileNumber);
        } finally {
            $previousSource === null ? $query->remove('source') : $query->set('source', $previousSource);
        }
    }

    private function drawCommissioningSheet(CommissioningSheetController $controller, $sheetId, object $mls, string $fileNumber): ?string
    {
        return $this->readOnly(function () use ($controller, $sheetId, $mls, $fileNumber) {
            if (!$sheetId) {
                // Never printed by the office: draw it from the commissioning
                // record, through the same save-then-print path the office uses.
                // The row it saves is rolled back with everything else.
                $response = $controller->generateAndPrint(Request::create('/', 'POST', [
                    'file_number'      => $fileNumber,
                    'file_name'        => $mls->file_name,
                    'name_or_allottee' => $mls->file_name,
                    'plot_number'      => $mls->plot_no,
                    'tp_number'        => $mls->tp_no,
                    'location'         => $mls->location,
                    'lga'              => $mls->lga,
                    'date_created'     => (string) ($mls->commissioning_date ?? $mls->created_at ?? ''),
                    'created_by'       => $mls->created_by,
                ]));
                $sheetId = $response->getData(true)['data']['id'] ?? null;
                if (!$sheetId) {
                    return null;
                }
            }

            return $controller->print($sheetId);
        });
    }

    private function lgaConfirmation(string $fileNumber): ?string
    {
        if (!$this->issuedConversionApplication($fileNumber)) {
            return null;
        }

        $fileNumberId = DB::connection('sqlsrv')->table('fileNumber')
            ->where('mlsfNo', $fileNumber)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderByDesc('id')
            ->value('id');

        return $this->readOnly(fn () => app(FileNumberController::class)
            ->generateConversionApplication(new Request(), $fileNumberId ?: $fileNumber));
    }

    private function trackingSheet(string $fileNumber): ?string
    {
        $indexing = $this->indexingRow($fileNumber);

        return $indexing
            ? $this->readOnly(fn () => app(FileIndexController::class)->printTrackingSheet($indexing->id))
            : null;
    }

    private function recommendation(string $fileNumber): ?string
    {
        $rec = $this->recommendationRow($fileNumber);
        if (!$rec || !$this->recommendationPrintable($rec)) {
            return null;
        }

        return $this->readOnly(fn () => app(LandRecommendationController::class)->print(new Request(), $rec->id));
    }

    private function rofo(string $fileNumber): ?string
    {
        $rec = $this->recommendationRow($fileNumber);
        $state = $rec ? $this->rofoState($rec) : null;
        if (!$state) {
            return null;
        }

        $controller = app(LandRofoController::class);

        return $this->readOnly(fn () => $state === 'issued'
            ? $controller->print(new Request(), $rec->id)
            : $controller->printWhiteCopy(new Request(), $rec->id));
    }

    /**
     * Drawn here rather than through OssApplicationController::printAcknowledgement,
     * whose id is tried as an instrument_capture id first and a fileNumber id
     * second — two id spaces that overlap. Same template, same fields.
     */
    private function ossAcknowledgement(string $fileNumber): ?string
    {
        $mls = $this->mlsRow($fileNumber);
        if (!$this->acknowledgementRow($fileNumber, $mls)) {
            return null;
        }

        $db = DB::connection('sqlsrv');
        $ic = !empty($mls->source_instrument_capture_id)
            ? $db->table('instrument_capture')->where('id', $mls->source_instrument_capture_id)->first()
            : null;
        $oss = $this->ossApplications($fileNumber)->first();

        $holder = $ic
            ? collect([$ic->party_1_name ?? null, $ic->party_2_name ?? null])->filter()->implode(' / ')
            : ($oss->applicant_name ?? $mls->file_name ?? '');
        $capturedAt = $ic->created_at ?? $oss->created_at ?? $mls->created_at ?? null;
        $capturedAt = $capturedAt ? \Carbon\Carbon::parse($capturedAt) : null;

        $record = (object) [
            'applicant_name' => strtoupper((string) $holder) ?: '—',
            'plot_no'        => strtoupper((string) ($ic->plot_number ?? $oss->plot_no ?? $mls->plot_no ?? '')),
            'plan_no'        => strtoupper((string) ($ic->survey_plan_no ?? $oss->plan_no ?? $mls->tp_no ?? '')),
            'location'       => strtoupper((string) ($ic->property_description ?? $oss->location ?? $mls->location ?? '')),
            'date_captured'  => $capturedAt ? strtoupper($capturedAt->format('M d, Y')) : '',
            'time_captured'  => $capturedAt ? strtoupper($capturedAt->format('g:i A')) : '',
        ];

        return $this->readOnly(fn () => view('lands_one_stop_shop.partials.print_acknowledgement', compact('record')));
    }

    private function ossVerification(string $fileNumber): ?string
    {
        $row = $this->ossLinkedRow('oss_verifications', $fileNumber, $this->mlsRow($fileNumber));

        return $row
            ? $this->readOnly(fn () => app(OssApplicationController::class)->printVerificationByRecord((int) $row->instrument_capture_id))
            : null;
    }

    private function ossChangeOfOwnership(string $fileNumber): ?string
    {
        $row = $this->ossLinkedRow('oss_change_of_ownership', $fileNumber, $this->mlsRow($fileNumber));
        if (!$row) {
            return null;
        }

        // The same fields OssApplicationController::printChangeOfOwnership() reads
        // from the modal, taken from the saved row instead.
        $data = [
            'file_no'          => strtoupper($fileNumber),
            'op_number'        => strtoupper((string) ($row->op_number ?? '')),
            'location'         => strtoupper((string) ($row->location ?? '')),
            'plot_no'          => strtoupper((string) ($row->plot_no ?? '')),
            'plan_no'          => strtoupper((string) ($row->plan_no ?? '')),
            'date_of_issuance' => (string) ($row->date_of_issuance ?? ''),
            'original_name'    => strtoupper((string) ($row->original_name ?? '')),
            'original_address' => (string) ($row->original_address ?? ''),
            'original_phone'   => (string) ($row->original_phone ?? ''),
            'current_name'     => strtoupper((string) ($row->current_name ?? '')),
            'current_address'  => (string) ($row->current_address ?? ''),
            'current_phone'    => (string) ($row->current_phone ?? ''),
            'ownership_method' => (string) ($row->ownership_method ?? ''),
        ];

        return $this->readOnly(fn () => view('lands_one_stop_shop.print.print_change', compact('data')));
    }

    private function streamScan(string $fileNumber, int $scanId): ?Response
    {
        $scan = DB::connection('sqlsrv')->table('scannings as s')
            ->join('file_indexings as fi', 'fi.id', '=', 's.file_indexing_id')
            ->where('s.id', $scanId)
            ->where('fi.file_number', $fileNumber)
            ->first(['s.document_path', 's.original_filename']);

        if (!$scan) {
            return null;
        }

        $path = $this->scanPath((string) $scan->document_path);
        if ($path === null) {
            return null;
        }

        return response()->file($path, [
            'Cache-Control'       => 'private, max-age=600',
            'Content-Disposition' => 'inline; filename="' . addslashes(basename($path)) . '"',
        ]);
    }

    // ------------------------------------------------------------- lookups

    private function mlsRow(string $fileNumber): ?object
    {
        return DB::connection('sqlsrv')->table('mls_file_no')
            ->where('full_file_number', $fileNumber)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderByDesc('id')
            ->first();
    }

    private function indexingRow(string $fileNumber): ?object
    {
        return DB::connection('sqlsrv')->table('file_indexings')
            ->where('file_number', $fileNumber)
            ->orderByDesc('id')
            ->first(['id', 'created_at']);
    }

    /** The LGA sheet as the office issued it — only ever one with a serial. */
    private function issuedConversionApplication(string $fileNumber): ?object
    {
        $db = DB::connection('sqlsrv');
        $fileNumberIds = $db->table('fileNumber')->where('mlsfNo', $fileNumber)->pluck('id')->all();

        return $db->table('conversion_applications')
            ->whereNotNull('serial_no')
            ->where(function ($q) use ($fileNumber, $fileNumberIds) {
                $q->where('full_file_number', $fileNumber);
                if ($fileNumberIds) {
                    $q->orWhereIn('mls_file_no_id', $fileNumberIds);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    private function recommendationRow(string $fileNumber): ?object
    {
        return DB::connection('sqlsrv')->table('land_recommendations')
            ->where('file_number', $fileNumber)
            ->orderByDesc('id')
            ->first();
    }

    /** Mirrors the gates in LandRecommendationController::print(). */
    private function recommendationPrintable(object $rec): bool
    {
        return strtoupper((string) ($rec->type ?? '')) === 'OSS'
            || (string) ($rec->status ?? '') === \App\Models\LandRecommendation::STATUS_APPROVED;
    }

    /** 'issued' (printed, carries its serial), 'white' (generated, not issued) or null. */
    private function rofoState(object $rec): ?string
    {
        $issued = (int) ($rec->rofo_print_count ?? 0) > 0
            && DB::connection('sqlsrv')->table('security_codes')
                ->where('document_id', $rec->id)
                ->where('document_type', 'Land ROFO')
                ->exists();

        if ($issued) {
            return 'issued';
        }

        return (string) ($rec->rofo_status ?? '') === \App\Models\LandRecommendation::ROFO_GENERATED ? 'white' : null;
    }

    private function ossApplications(string $fileNumber)
    {
        return DB::connection('sqlsrv')->table('oss_applications')
            ->where('file_no', $fileNumber)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderByDesc('id')
            ->get();
    }

    private function acknowledgementRow(string $fileNumber, ?object $mls): ?object
    {
        $query = DB::connection('sqlsrv')->table('oss_acknowledgements')
            ->where(function ($q) use ($fileNumber, $mls) {
                $q->where('file_no', $fileNumber);
                if (!empty($mls->source_instrument_capture_id)) {
                    $q->orWhere('instrument_capture_id', $mls->source_instrument_capture_id);
                }
            });

        return $query->orderByDesc('id')->first();
    }

    /**
     * A row of an OSS table keyed by instrument_capture_id, that belongs to this
     * file. See the class note on why an id match alone is not trusted.
     */
    private function ossLinkedRow(string $table, string $fileNumber, ?object $mls): ?object
    {
        $db = DB::connection('sqlsrv');
        $oss = $this->ossApplications($fileNumber);

        $strong = array_filter([(int) ($mls->source_instrument_capture_id ?? 0)]);
        $weak = array_filter(array_merge(
            $oss->pluck('instrument_capture_id')->map(fn ($v) => (int) $v)->all(),
            $oss->pluck('id')->map(fn ($v) => (int) $v)->all(),
            [(int) ($mls->source_pra_id ?? 0)]
        ));

        $candidates = array_values(array_unique(array_merge($strong, $weak)));
        if (!$candidates) {
            return null;
        }

        $rows = $db->table($table)->whereIn('instrument_capture_id', $candidates)->orderByDesc('id')->get();
        $holders = array_filter(array_merge(
            [$mls->file_name ?? null],
            $oss->pluck('applicant_name')->all()
        ));
        $plots = array_filter(array_map(
            fn ($p) => strtoupper(trim((string) $p)),
            array_merge([$mls->plot_no ?? null], $oss->pluck('plot_no')->all())
        ));

        foreach ($rows as $row) {
            if (in_array((int) $row->instrument_capture_id, $strong, true)) {
                return $row;
            }

            $names = array_filter([
                $row->applicant_name ?? null,
                $row->current_name ?? null,
                $row->original_allottee ?? null,
                $row->original_name ?? null,
            ]);
            foreach ($names as $name) {
                foreach ($holders as $holder) {
                    if (HolderName::same($name, $holder)) {
                        return $row;
                    }
                }
            }

            $plot = strtoupper(trim((string) ($row->plot_no ?? '')));
            if ($plot !== '' && in_array($plot, $plots, true)) {
                return $row;
            }
        }

        return null;
    }

    /** The scan on disk, by the same ladder the commissioning sheet uses. */
    private function scanPath(string $documentPath): ?string
    {
        $relative = ltrim(str_replace('\\', '/', trim($documentPath)), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $candidates = [
            Storage::disk('public')->path($relative),
            public_path($relative),
            public_path('storage/' . $relative),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Draw a staff document without letting it write anything.
     *
     * Opens a transaction on every connection a print action might touch,
     * renders the view to a string INSIDE it (Blade can query lazily), then
     * rolls back to where it started. Any abort / exception inside means "not
     * available" rather than an error page.
     */
    private function readOnly(callable $render): ?string
    {
        $connections = array_values(array_unique(['sqlsrv', (string) config('database.default')]));
        $started = [];

        try {
            foreach ($connections as $name) {
                $connection = DB::connection($name);
                $started[$name] = $connection->transactionLevel();
                $connection->beginTransaction();
            }

            $out = $render();

            if ($out instanceof \Illuminate\Contracts\View\View) {
                return $out->render();
            }
            if ($out instanceof Response) {
                return $out->getStatusCode() < 400 ? (string) $out->getContent() : null;
            }

            return $out === null ? null : (string) $out;
        } catch (\Throwable $e) {
            Log::info('LAAS folio: document could not be drawn', ['error' => $e->getMessage()]);

            return null;
        } finally {
            foreach (array_reverse($started, true) as $name => $level) {
                try {
                    DB::connection($name)->rollBack($level);
                } catch (\Throwable $e) {
                    Log::error('LAAS folio: rollback failed', ['connection' => $name, 'error' => $e->getMessage()]);
                }
            }
        }
    }
}
