<?php

namespace App\Http\Controllers;

use App\Services\PrintLabel\QrCodeExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * "Export Generated QR Codes" for the Land, KANGIS, SLTR and ST
 * Print File Label pages: file numbers in a date range (optionally one file
 * prefix), sorted by prefix then 1 to N, as CSV or PDF.
 */
class PrintLabelQrExportController extends Controller
{
    public function __construct(private QrCodeExportService $exports)
    {
    }

    public function summary(Request $request, string $module)
    {
        [$from, $to, $prefix] = $this->range($request);

        return response()->json([
            'success'   => true,
            'count'     => $this->exports->count($module, $from, $to, $prefix),
            'prefixes'  => $this->exports->prefixes($module, $from, $to),
            'pdf_limit' => QrCodeExportService::PDF_LIMIT,
        ]);
    }

    public function export(Request $request, string $module)
    {
        [$from, $to, $prefix] = $this->range($request);
        $label = QrCodeExportService::moduleConfig($module)['label'];
        $name = "{$label}_File_Numbers_"
            . ($prefix ? preg_replace('/[^A-Za-z0-9]+/', '-', $prefix) . '_' : '')
            . now()->format('Ymd_His');

        if ($request->input('format') !== 'pdf') {
            @set_time_limit(300);

            return response()->streamDownload(function () use ($module, $from, $to, $prefix) {
                $out = fopen('php://output', 'w');
                $this->exports->writeCsv($module, $from, $to, $out, $prefix);
                fclose($out);
            }, "{$name}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $count = $this->exports->count($module, $from, $to, $prefix);
        if ($count === 0) {
            return back()->with('error', 'No file numbers in the selected range.');
        }
        if ($count > QrCodeExportService::PDF_LIMIT) {
            return back()->with('error', sprintf(
                '%s file numbers are in this range. The PDF holds at most %s; narrow the range, pick a file prefix, or export CSV.',
                number_format($count),
                number_format(QrCodeExportService::PDF_LIMIT)
            ));
        }

        @set_time_limit(300);

        return Pdf::loadView('printlabel.qr-export-pdf', [
            'label'  => $label,
            'from'   => $from,
            'to'     => $to,
            'prefix' => $prefix,
            'rows'   => $this->exports->pdfRows($module, $from, $to, $prefix),
        ])->setPaper('a4')->download("{$name}.pdf");
    }

    private function range(Request $request): array
    {
        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'prefix'    => ['nullable', 'string', 'max:30'],
        ]);

        return [
            $request->input('date_from') ?: null,
            $request->input('date_to') ?: null,
            strtoupper(trim((string) $request->input('prefix'))) ?: null,
        ];
    }
}
